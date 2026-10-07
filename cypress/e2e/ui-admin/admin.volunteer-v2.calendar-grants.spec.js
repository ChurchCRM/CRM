/// <reference types="cypress" />

/**
 * Volunteer v2 D25 — "Ministries that may add events" on the admin calendar page, and the event
 * editor offering a coordinator only the calendars they may pin to.
 * Design: volunteer-v2-design.md §0.8 D25, §2.16; member-portal-design.md §5.3.
 *
 * API setup runs before a fresh login: cy.request() rotates the PHP session cookie.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const PREFIX = "UIVCAL";
const PERSON_COORDINATOR = 3;
const PUBLIC_CALENDAR = 1;
const CHURCH_SERVICE_TYPE = 1;

let originalVersion = "v1";
const ministry = {};
const ministryCalendar = {};
let bibleClasses = 0;
let eventId = 0;

function dbOk(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(`Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`);
        }
        return result.rows;
    });
}

function freshLogin(username, password) {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(username);
    cy.get("input[name=Password]").type(`${password}{enter}`);
    cy.url().should("not.include", "/session/begin");
}

function adminLogin() {
    freshLogin(Cypress.testEnv("admin.username"), Cypress.testEnv("admin.password"));
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

function grantedMinistryIds(calendarId) {
    return dbOk(`SELECT vcal_vmin_ID FROM volunteer_calendar_vcal WHERE vcal_calendar_id = ? ORDER BY vcal_vmin_ID`, [
        calendarId,
    ]).then((rows) => rows.map((r) => r.vcal_vmin_ID));
}

function openSidebar() {
    cy.visit("/event/calendars");
    cy.get('[data-bs-target="#calendarSidebar"]').click();
    cy.get("#calendarSidebar").should("be.visible");
}

/** Set a TomSelect multi-select and wait until it holds exactly these values. */
function tomSelectValues(selector, values) {
    cy.get(selector).should(($select) => {
        const ts = $select[0].tomselect;
        expect(ts, `TomSelect on ${selector}`).to.exist;
        ts.setValue(values.map(String));
        expect([].concat(ts.getValue()).sort()).to.deep.eq(values.map(String).sort());
    });
}

function cleanupFixtures() {
    dbOk(
        `DELETE ce FROM calendar_events ce
           JOIN events_event e ON e.event_id = ce.event_id
          WHERE e.event_title LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [`${PREFIX}%`]);
    dbOk(
        `DELETE vscp FROM volunteer_scope_vscp vscp
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vscp.vscp_ScopeId
          WHERE vscp.vscp_ScopeType = 'ministry' AND vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE p2g2r FROM person2group2role_p2g2r p2g2r
           JOIN group_grp g ON g.grp_ID = p2g2r.p2g2r_grp_ID
          WHERE g.grp_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE FROM group_grp WHERE grp_Name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [`${PREFIX}%`]);
}

before(() => {
    cy.rememberTestEnv(["admin.username", "admin.password", "standard.username", "standard.password"]);
});

describe("Volunteer v2 D25 — calendar grants in the UI", () => {
    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
            originalVersion = resp.body.value ?? resp.body.data ?? "v1";
        });
        setVersion("v2");
        cleanupFixtures();

        for (const key of ["A", "B"]) {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/ministries/ministries",
                { name: `${PREFIX} Ministry ${key}`, description: "calendar grant UI fixture" },
                201,
            ).then((resp) => {
                ministry[key] = resp.body.ministry.id;
                ministryCalendar[key] = resp.body.calendarId;
            });
        }
        cy.makePrivateAdminAPICall("POST", "/api/calendars", {
            Name: `${PREFIX} Bible Classes`,
            ForegroundColor: "#FFFFFF",
            BackgroundColor: "#1565C0",
        }).then((resp) => {
            bibleClasses = resp.body.Id;
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion(originalVersion);
    });

    it("offers the ministries on the New Calendar dialog and saves the choice", () => {
        const name = `${PREFIX} New ${Date.now()}`;
        adminLogin();
        openSidebar();
        cy.intercept("PUT", "**/api/calendars/*/ministries").as("saveGrants");

        cy.get("#addCalendarBtn").click();
        // The sidebar offcanvas and the dialog both trap focus, so typed keys can land nowhere.
        cy.get("#calendarName").should("be.visible").invoke("val", name).should("have.value", name);
        cy.get("#calendarMinistryGrantsField").should("contain", "Ministries that may add events");
        tomSelectValues("#calendarMinistryGrants", [ministry.A]);
        cy.get(".bootbox .modal-footer .btn-primary").click();

        cy.wait("@saveGrants").then((intercepted) => {
            expect(intercepted.request.body.ministryIds).to.deep.eq([ministry.A]);
            expect(intercepted.response.statusCode).to.eq(200);
        });
        dbOk(`SELECT calendar_id FROM calendars WHERE name = ?`, [name]).then((rows) => {
            expect(rows).to.have.length(1);
            grantedMinistryIds(rows[0].calendar_id).then((ids) => expect(ids).to.deep.eq([ministry.A]));
        });
    });

    it("shows a church calendar's grants in its dialog and replaces them on Save", () => {
        cy.makePrivateAdminAPICall("PUT", `/api/calendars/${bibleClasses}/ministries`, { ministryIds: [ministry.A] });
        adminLogin();
        openSidebar();
        cy.intercept("PUT", `**/api/calendars/${bibleClasses}/ministries`).as("saveGrants");

        cy.get(`.calendarproperties[data-calendarid="${bibleClasses}"]`, { timeout: 20000 }).click();
        cy.get("#calendarMinistryGrantsField", { timeout: 20000 }).should("be.visible");
        cy.get("#calendarMinistryGrants").should(($select) => {
            expect([].concat($select[0].tomselect.getValue())).to.deep.eq([String(ministry.A)]);
        });
        tomSelectValues("#calendarMinistryGrants", [ministry.A, ministry.B]);
        cy.get(".bootbox .modal-footer .btn-primary").click();

        cy.wait("@saveGrants").its("response.statusCode").should("eq", 200);
        grantedMinistryIds(bibleClasses).then((ids) => {
            expect(ids).to.deep.eq([ministry.A, ministry.B].sort((a, b) => a - b));
        });
    });

    it("does not offer the field on a ministry's own calendar", () => {
        adminLogin();
        openSidebar();
        cy.get(`.calendarproperties[data-calendarid="${ministryCalendar.A}"]`, { timeout: 20000 }).click();
        cy.get(".bootbox .modal-body").should("be.visible").and("contain", "Access Token");
        cy.get("#calendarMinistryGrantsField").should("not.exist");
    });

    it("does not offer the field while the rollout is v1", () => {
        setVersion("v1");
        adminLogin();
        openSidebar();
        cy.get("#addCalendarBtn").click();
        cy.get("#calendarName").should("be.visible");
        cy.get("#calendarMinistryGrantsField").should("not.exist");
        setVersion("v2");
    });

    it("says in the New event dialog which calendars it lists, for an administrator and a coordinator (#10374)", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/ministries/scopes",
            { personId: PERSON_COORDINATOR, scopeType: "ministry", scopeId: ministry.A },
            [200, 201],
        );
        const openNewEvent = () => {
            cy.visit(`/ministries/${ministry.A}`);
            cy.get("#nav-item-calendar").click();
            cy.get("#ministry-event-add-btn").click();
            cy.get("#ministryEventModal", { timeout: 15000 }).should("be.visible");
        };

        adminLogin();
        openNewEvent();
        cy.get(`#ministry-event-form-calendars .ministry-event-calendar[value="${PUBLIC_CALENDAR}"]`).should("exist");
        cy.get("#ministry-event-form-calendars-hint").should("contain", "You may add events to every calendar");

        freshLogin(Cypress.testEnv("standard.username"), Cypress.testEnv("standard.password"));
        openNewEvent();
        cy.get(`#ministry-event-form-calendars .ministry-event-calendar[value="${ministryCalendar.A}"]`).should("exist");
        cy.get(`#ministry-event-form-calendars .ministry-event-calendar[value="${PUBLIC_CALENDAR}"]`).should("not.exist");
        cy.get("#ministry-event-form-calendars-hint")
            .should("contain", "Only the calendars this ministry may add events to are listed")
            .and("not.contain", "every calendar");
    });

    it("offers a coordinator only the calendars they may pin to, and keeps a pin they cannot change", () => {
        cy.makePrivateAdminAPICall("PUT", `/api/calendars/${bibleClasses}/ministries`, { ministryIds: [ministry.A] });
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/ministries/scopes",
            { personId: PERSON_COORDINATOR, scopeType: "ministry", scopeId: ministry.A },
            [200, 201],
        );
        cy.makePrivateAdminAPICall("POST", "/api/events", {
            Title: `${PREFIX} Class Meeting`,
            Type: CHURCH_SERVICE_TYPE,
            Start: "2030-03-03T09:30:00",
            End: "2030-03-03T10:15:00",
            MinistryId: ministry.A,
            PinnedCalendars: [PUBLIC_CALENDAR, ministryCalendar.A],
        });
        dbOk(`SELECT event_id FROM events_event WHERE event_title = ?`, [`${PREFIX} Class Meeting`]).then((rows) => {
            eventId = rows[0].event_id;
        });

        freshLogin(Cypress.testEnv("standard.username"), Cypress.testEnv("standard.password"));
        cy.then(() => cy.visit(`/event/editor/${eventId}`));
        cy.intercept("POST", "**/api/events/*").as("saveEvent");

        cy.get("#pinnedCalendarsSelect", { timeout: 20000 }).should(($select) => {
            const offered = Object.keys($select[0].tomselect.options).map(Number);
            expect(offered).to.include.members([ministryCalendar.A, bibleClasses]);
            expect(offered).to.not.include.members([PUBLIC_CALENDAR]);
            expect(offered).to.not.include.members([ministryCalendar.B]);
        });
        cy.get("#calendarsLockedHint", { timeout: 20000 }).should("be.visible").and("contain", "Public Calendar");

        tomSelectValues("#pinnedCalendarsSelect", [ministryCalendar.A, bibleClasses]);
        cy.get("#event-editor-save").should("not.be.disabled").click();

        cy.wait("@saveEvent").then((intercepted) => {
            expect(intercepted.response.statusCode).to.eq(200);
            expect(intercepted.request.body.PinnedCalendars.map(Number).sort((a, b) => a - b)).to.deep.eq(
                [PUBLIC_CALENDAR, ministryCalendar.A, bibleClasses].sort((a, b) => a - b),
            );
        });
        cy.then(() =>
            dbOk(`SELECT calendar_id FROM calendar_events WHERE event_id = ? ORDER BY calendar_id`, [eventId]),
        ).then((rows) => {
            expect(rows.map((r) => Number(r.calendar_id))).to.deep.eq(
                [PUBLIC_CALENDAR, ministryCalendar.A, bibleClasses].sort((a, b) => a - b),
            );
        });
    });
});
