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
Cypress.Commands.add(
    "makePrivateAdminAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        return cy.makePrivateAPICall(
            Cypress.env("admin.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateUserAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        return cy.makePrivateAPICall(
            Cypress.env("user.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateFinanceOnlyAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        // grace.financeonly (id=904): Finance=1, non-admin.
        // Used to verify Finance-role (not Admin) can access /finance/api/funds CRUD.
        return cy.makePrivateAPICall(
            Cypress.env("finance.only.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateManageGroupsOnlyAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        // kyle.kioskonly (id=905): ManageGroups=1, non-admin.
        // Used to verify ManageGroups-role can access /kiosk/api/* endpoints.
        return cy.makePrivateAPICall(
            Cypress.env("managegroups.only.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateNoFinanceAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        return cy.makePrivateAPICall(
            Cypress.env("nofinance.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateNoManageFundraisersAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        // Backed by a seed user (per_ID=96) with Finance=1 but ManageFundraisers=0.
        // Proves the ManageFundraisers gate fires independently of the Finance role.
        return cy.makePrivateAPICall(
            Cypress.env("nofundraiser.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivatePlainAuthAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        return cy.makePrivateAPICall(
            Cypress.env("plainauth.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateEditSelfAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        return cy.makePrivateAPICall(
            Cypress.env("selfedit.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

/**
 * EditSelf+Notes user — regression sentinel for FamilyReadMiddleware vs FamilyMiddleware.
 *
 * User: lena.black (ID 100, family 20) with usr_EditSelf=1, usr_Notes=1 in DB.
 *
 * Post-PR#9016 (EditSelf exclusive mode): User::isEditSelfExclusive() (checked by AuthMiddleware)
 * returns true for any non-admin user with isEditSelf()=true, regardless of Notes. AuthMiddleware
 * therefore blocks this user (403) before reaching FamilyReadMiddleware or FamilyMiddleware.
 *
 * Future use: if EditSelf exclusivity is ever relaxed to permit EditSelf+Notes, this user
 * should get 200 on avatar/nav/photo (FamilyReadMiddleware, canReadFamily=true) and 403 on
 * full profile/notes for non-own family 1 (FamilyMiddleware, canViewFamily=false). That
 * would make these tests detect a FamilyReadMiddleware→FamilyMiddleware regression.
 */
Cypress.Commands.add(
    "makePrivateEditSelfPlusNotesAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        return cy.makePrivateAPICall(
            Cypress.env("selfedit.plus.notes.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateLimitedAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        // limited.user (id=4): usr_Notes=0, usr_Admin=0, usr_EditRecords=0,
        // usr_EditSelf=1 — an EditSelf-ONLY user (NOT a zero-permission user).
        // EditSelf is exclusive, so AuthMiddleware::isEditSelfExclusive() blocks
        // this user → always returns 403.
        // Use this fixture ONLY to verify that Notes-gated endpoints return 403.
        // Do NOT use for routes that should return 200 for authenticated users
        // (e.g. timeline) — use makePrivateEditRecordsAPICall instead.
        //
        // For a genuinely zero-permission user (all flags 0, EditSelf=0) see
        // noperm.user (id=901), which now passes the gate with read-only access
        // under the read-default policy (#9003).
        return cy.makePrivateAPICall(
            Cypress.env("limited.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateEditRecordsAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        // judith.matthews (id=95): usr_EditRecords=1, usr_Notes=0, usr_Admin=0.
        // Passes AuthMiddleware (has EditRecords permission) but canReadNotes()
        // returns false (no Notes flag). Use for testing routes that should
        // return 200 to authenticated users but strip note items (e.g. timeline).
        return cy.makePrivateAPICall(
            Cypress.env("editrecords.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateMenuOptionsAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        // menuoptions.user (id=902): usr_MenuOptions=1, all other permission flags 0,
        // non-admin, non-EditSelf. Used to verify EditRecords gate on person/family
        // property routes (GHSA-4wmp-3v34-g7q8). Passes MenuOptions middleware but
        // is blocked by EditRecordsRoleAuthMiddleware (expects 403 on record routes).
        return cy.makePrivateAPICall(
            Cypress.env("menuoptions.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

Cypress.Commands.add(
    "makePrivateNoPermAPICall",
    (method, url, body, expectedStatus = 200, timeoutMs) => {
        // noperm.user (id=901): every permission flag 0, usr_EditSelf=0,
        // non-admin. The genuinely zero-permission user — it passes
        // AuthMiddleware under the read-default policy (#9003) and gets
        // read-only access, so every write route must answer 403 for it.
        // Use this to prove a write route carries a role gate at all.
        return cy.makePrivateAPICall(
            Cypress.env("noperm.api.key"),
            method,
            url,
            body,
            expectedStatus,
            timeoutMs,
        );
    },
);

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
    return cy
        .request({
            method,
            url,
            body,
            failOnStatusCode: false,
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
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
        });
}

/** Re-read a record after cleanup and fail unless it is gone. */
function assertGone(label, url) {
    return cy
        .request({
            method: "GET",
            url,
            failOnStatusCode: false,
            headers: { "x-api-key": Cypress.env("admin.api.key") },
            withCredentials: false,
        })
        .then((resp) => {
            expect(
                resp.status,
                `${label} is still in the database after cleanup (GET ${url} returned ${resp.status}, expected 404)`,
            ).to.eq(404);
        });
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
