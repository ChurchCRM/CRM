// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************

// -- Modern API command patterns --

function privateApiCall(keyName) {
    return (method, url, body, expectedStatus = 200, timeoutMs) =>
        cy.readEnv(keyName).then((key) =>
            cy.makePrivateAPICall(key, method, url, body, expectedStatus, timeoutMs),
        );
}

Cypress.Commands.add("makePrivateAdminAPICall", privateApiCall("admin.api.key"));

// POST /api/email/send only queues (#10356). Send the batch through the way the composer does
// and yield the send response with the batch's final progress as `progress`.
const sendEmailBatch = (batch) =>
    cy.makePrivateAdminAPICall("POST", `/api/email/batches/${batch}/send`, null, 200).then((resp) =>
        resp.body.done || resp.body.due === 0 ? resp.body : sendEmailBatch(batch),
    );

Cypress.Commands.add("sendComposerEmailNow", (body) =>
    cy.makePrivateAdminAPICall("POST", "/api/email/send", body, 200).then((resp) => {
        if (!resp.body.batch) {
            return { ...resp.body, progress: null };
        }
        return sendEmailBatch(resp.body.batch).then((progress) => ({ ...resp.body, progress }));
    }),
);

// Effective value of a SystemConfig key (the default when no config_cfg row exists).
Cypress.Commands.add("getSystemConfig", (name) => {
    return cy
        .makePrivateAdminAPICall("GET", `admin/api/system/config/${name}`, null, 200)
        .then((response) => String(response.body.value));
});

Cypress.Commands.add("setSystemConfig", (name, value) =>
    cy.makePrivateAdminAPICall("POST", `admin/api/system/config/${name}`, { value: String(value) }, 200),
);

// Put a SystemConfig key back to a value captured with getSystemConfig, then
// confirm it. Restore the captured value, never a literal: setValue() deletes
// the config_cfg row when the value equals the default, so writing the default
// over a seeded non-default value removes the row (#9799).
Cypress.Commands.add("restoreSystemConfig", (name, value) => {
    if (value === undefined) {
        cy.log(`restoreSystemConfig: no captured value for ${name}; leaving it unchanged`);
        return;
    }
    cy.makePrivateAdminAPICall("POST", `admin/api/system/config/${name}`, { value }, 200);
    cy.getSystemConfig(name).should("eq", value);
});

// The calendars Admin -> Member Portal -> Calendars shares with members, as {type, id}.
// The portal has a Calendar page while at least one is shared.
Cypress.Commands.add("getMemberPortalCalendars", () =>
    cy
        .makePrivateAdminAPICall("GET", "/admin/api/member-portal/calendars", null, 200)
        .then((response) => response.body.calendars.filter((calendar) => calendar.visible).map(({ type, id }) => ({ type, id }))),
);

Cypress.Commands.add("shareMemberPortalCalendars", (visible) =>
    cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/calendars", { visible }, 200),
);

Cypress.Commands.add("makePrivateUserAPICall", privateApiCall("user.api.key"));

// grace.financeonly (id=904): Finance=1, non-admin.
// Used to verify Finance-role (not Admin) can access /finance/api/funds CRUD.
Cypress.Commands.add("makePrivateFinanceOnlyAPICall", privateApiCall("finance.only.api.key"));

// kyle.kioskonly (id=905): ManageGroups=1, non-admin.
// Used to verify ManageGroups-role can access /kiosk/api/* endpoints.
Cypress.Commands.add("makePrivateManageGroupsOnlyAPICall", privateApiCall("managegroups.only.api.key"));

Cypress.Commands.add("makePrivateNoFinanceAPICall", privateApiCall("nofinance.api.key"));

// Backed by a seed user (per_ID=96) with Finance=1 but ManageFundraisers=0.
// Proves the ManageFundraisers gate fires independently of the Finance role.
Cypress.Commands.add("makePrivateNoManageFundraisersAPICall", privateApiCall("nofundraiser.api.key"));

Cypress.Commands.add("makePrivatePlainAuthAPICall", privateApiCall("plainauth.api.key"));

// judith.matthews (id=95): usr_EditRecords=1, usr_Notes=0, usr_Admin=0.
// Passes AuthMiddleware (has EditRecords permission) but canReadNotes()
// returns false (no Notes flag). Use for testing routes that should
// return 200 to authenticated users but strip note items (e.g. timeline).
Cypress.Commands.add("makePrivateEditRecordsAPICall", privateApiCall("editrecords.api.key"));

// noperm.user (id=901): every permission flag 0, usr_EditSelf=0,
// non-admin. The genuinely zero-permission user — it passes
// AuthMiddleware under the read-default policy (#9003) and gets
// read-only access, so every write route must answer 403 for it.
// Use this to prove a write route carries a role gate at all.
Cypress.Commands.add("makePrivateNoPermAPICall", privateApiCall("noperm.api.key"));

Cypress.Commands.add(
    "makePrivateAPICall",
    (key, method, url, body, expectedStatus = 200, timeoutMs) => {
        const requestOptions = {
            method: method,
            failOnStatusCode: false,
            url: url,
            headers: {
                "content-type": "application/json",
                "x-api-key": key,
            },
            body: body,
            // Prevent the browser session cookie from being sent alongside the API key.
            // cy.request() shares the browser cookie jar by default; sending both the
            // session cookie and x-api-key causes PHP's AuthenticationManager to
            // overwrite $_SESSION['AuthenticationProvider'] with APITokenAuthentication,
            // which breaks subsequent browser page loads on the same session.
            withCredentials: false,
        };

        if (typeof timeoutMs === 'number') {
            requestOptions.timeout = timeoutMs;
        }

        return cy.request(requestOptions).then((resp) => {
            // Handle single status code or array of acceptable status codes
            const acceptedStatuses = Array.isArray(expectedStatus) ? expectedStatus : [expectedStatus];
            expect(resp.status).to.be.oneOf(acceptedStatuses);

            // Return the full response object so tests can access resp.body
            return resp;
        });
    },
);

// Modern API testing command with better error handling
Cypress.Commands.add(
    "apiRequest",
    (options) => {
        const defaultOptions = {
            failOnStatusCode: false,
            timeout: 10000,
        };
        
        return cy.request({...defaultOptions, ...options}).then((response) => {
            // Log response for debugging
            cy.log(`API ${options.method} ${options.url} - Status: ${response.status}`);
            return cy.wrap(response);
        });
    },
);

// -- Direct database access (node-side `db:query` task) --
//
// Schema-level guarantees — UNIQUE keys, foreign keys and their ON DELETE
// rules, enum domains — have no HTTP surface, so the Volunteer v2 schema specs
// assert them against the database itself (#9705). The task resolves with
// `{ rows, error }` rather than rejecting, so a spec can assert that a write
// was refused (ER_DUP_ENTRY, ER_NO_REFERENCED_ROW_2, ...) without failing the
// test run. It runs only for the files cypress/db-access.json lets use the
// database (#10377); a fixture in cypress/support/ names its own file.
Cypress.Commands.add("dbQuery", (sql, params = [], { fixture = null } = {}) => {
    return cy.task("db:query", { sql, params, spec: Cypress.spec.relative, fixture }, { log: false });
});

// ---------------------------------------------------------------------------
// Cleanup helpers (#9769)
// ---------------------------------------------------------------------------
//
// Every helper below accepts exactly two outcomes from a delete: the record
// was removed (200) or it was already gone (404, so the helper is safe in an
// after() hook that runs after a failed test). Anything else — a 400, a 403,
// a 409 — is an unsuccessful cleanup and fails the hook, because it is not
// evidence that the record is absent. After deleting, each helper re-reads the
// record and fails unless the read is a 404, so "cleanup ran" always means
// "the row is gone". That per-id check is what closes the gap the aggregate
// row-count guard in cypress/support/e2e.js cannot see.

const CLEANUP_OK_STATUSES = [200, 404];

const numericIds = (ids) => (ids || []).filter((id) => Number.isFinite(Number(id)));

/**
 * One admin-keyed cleanup request. Mirrors makePrivateAPICall's request
 * options (withCredentials: false keeps the browser session cookie out of the
 * request) but asserts the status itself so the failure message says which
 * record and which step failed.
 */
function cleanupRequest(label, method, url, body) {
    return cy.readEnv("admin.api.key").then((apiKey) =>
        cy
        .request({
            method,
            url,
            body,
            failOnStatusCode: false,
            headers: {
                "content-type": "application/json",
                "x-api-key": apiKey,
            },
            withCredentials: false,
        })
        .then((resp) => {
            expect(
                resp.status,
                `${label}: ${method} ${url} must delete the record (200) or find it already gone (404); ` +
                    `got ${resp.status} ${JSON.stringify(resp.body)}`,
            ).to.be.oneOf(CLEANUP_OK_STATUSES);
            return resp;
        }),
    );
}

/** Re-read a record after cleanup and fail unless it is gone. */
function assertGone(label, url) {
    return cy.readEnv("admin.api.key").then((apiKey) =>
        cy
        .request({
            method: "GET",
            url,
            failOnStatusCode: false,
            headers: { "x-api-key": apiKey },
            withCredentials: false,
        })
        .then((resp) => {
            expect(
                resp.status,
                `${label} is still in the database after cleanup (GET ${url} returned ${resp.status}, expected 404)`,
            ).to.eq(404);
        }),
    );
}

/**
 * Delete every event id in `eventIds`, so a spec can undo what it created.
 *
 * Deactivates first: DELETE /api/events/{id} refuses (409) to remove an active
 * event that still has people checked in, which is exactly the state the
 * check-in specs leave their events in. Deleting an event cascades its
 * calendar_events, event_attend and event_audience rows via Event::preDelete().
 * A 409 that survives deactivation (the event is assigned to a kiosk) fails
 * the hook — the event is still there.
 *
 * @param {Array<number|string>} eventIds
 */
Cypress.Commands.add("cleanupEvents", (eventIds) => {
    numericIds(eventIds).forEach((eventId) => {
        const label = `event ${eventId}`;
        cleanupRequest(label, "POST", `/api/events/${eventId}/status`, { active: false });
        cleanupRequest(label, "DELETE", `/api/events/${eventId}`);
        assertGone(label, `/api/events/${eventId}`);
    });
});

/**
 * Delete every note id in `noteIds`.
 *
 * NOTE: DELETE /api/note/{id} writes a `delete-note` audit row for the
 * timeline, so removing N notes leaves N audit rows behind — the note table
 * cannot be returned to its exact seed count through the API. Specs that call
 * this still declare the residue with cy.allowRowDrift("note_nte", …). The
 * point of calling it is to take the spec's test *content* back off the
 * person/family timelines, not to zero the row count — which is also why the
 * helper re-reads each note: note_nte growth is informational only, so this
 * check is the only thing that proves the notes themselves are gone.
 *
 * @param {Array<number|string>} noteIds
 */
Cypress.Commands.add("cleanupNotes", (noteIds) => {
    numericIds(noteIds).forEach((noteId) => {
        const label = `note ${noteId}`;
        cleanupRequest(label, "DELETE", `/api/note/${noteId}`);
        assertGone(label, `/api/note/${noteId}`);
    });
});

/**
 * Delete every person id in `personIds`.
 *
 * Ids that are already gone (404) are ignored, so the helper is safe to call
 * from an after() hook that runs after a failed test.
 *
 * @param {Array<number|string>} personIds
 */
Cypress.Commands.add("cleanupPeople", (personIds) => {
    numericIds(personIds).forEach((personId) => {
        const label = `person ${personId}`;
        cleanupRequest(label, "DELETE", `/api/person/${personId}`);
        assertGone(label, `/api/person/${personId}`);
    });
});

/**
 * Delete every family id in `familyIds`, together with its members.
 *
 * @param {Array<number|string>} familyIds
 */
Cypress.Commands.add("cleanupFamilies", (familyIds) => {
    numericIds(familyIds).forEach((familyId) => {
        const label = `family ${familyId}`;
        cleanupRequest(label, "DELETE", `/api/family/${familyId}?deleteMembers=true`);
        assertGone(label, `/api/family/${familyId}`);
    });
});

/**
 * Record the person id out of the /people/view/{id} URL the legacy
 * PersonEditor redirects to after a save, pushing it onto `collector` so an
 * after() hook can clean it up (#9769).
 *
 * @param {Array<number>} collector
 */
Cypress.Commands.add("trackPersonFromUrl", (collector) => {
    return cy.url().then((url) => {
        const match = url.match(/\/people\/view\/(\d+)/);
        const personId = match ? Number.parseInt(match[1], 10) : null;
        if (personId) {
            collector.push(personId);
        }
        return personId;
    });
});
