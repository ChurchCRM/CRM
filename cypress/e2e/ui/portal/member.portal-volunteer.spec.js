/// <reference types="cypress" />

/**
 * Member Portal (MP6, #9867) — volunteering inside the portal.
 *
 * `volunteer-v2.member.spec.js` is the deep spec for what the two pages DO; it
 * moved wholesale to the new URLs. This one is about the move itself: that a
 * member lands in the portal, finds volunteering in the portal's own navigation,
 * and can complete both workflows without the admin shell ever appearing —
 * respond to an assignment, sign up for an open slot, and offer to help a
 * ministry that is advertising. Plus the two retired URLs still forward.
 *
 * Persona: person 100, Lena Black — `usr_EditSelf=1`, no admin flag, so
 * `User::isEditSelfExclusive()` is true and this login has no admin shell to
 * fall back to. `user_usr.usr_UserName` is `VARCHAR(32)` and the seeded address
 * is 37 characters, so the login form must be given the truncated form.
 *
 * Order inside every hook is API setup → login → `cy.visit()`, because
 * `cy.request()` rotates the PHP session cookie (cypress-testing.md). Fixtures
 * are removed in `before` as well as `after`: an `after` hook does not run when
 * the runner crashes mid-spec.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";

const SCHEDULE_URL = "/portal/volunteer/schedule";
const OPPORTUNITIES_URL = "/portal/volunteer/opportunities";
const LEGACY_SCHEDULE_URL = "/volunteer/my-schedule";
const LEGACY_OPPORTUNITIES_URL = "/volunteer/opportunities";

const MEMBER_USERNAME = "lena.black.editself.notes@exampl";
const MEMBER_PASSWORD = "changeme";
const PERSON_MEMBER = 100;
const POOL_MEMBER_A = 8;

const CHURCH_SERVICE_TYPE = 1;
const PREFIX = "MP9867";
const EVENT_TITLE = `${PREFIX} Portal Service`;
const HELP_WANTED_TEXT = `${PREFIX} we would love more hands on a Sunday`;

let ministryId = 0;
let teamId = 0;
let posDoor = 0; // Min 1 / Max 2 — the assignment target
let posCoffee = 0; // Min 1 / Max 1 — the sign-up target
let scheduleId = 0;
let occurrenceId = 0;
let seriesStart = "";
let seriesEnd = "";

// ── helpers (local functions, NOT cy.* commands — cypress-testing.md) ───────

function freshMemberLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(MEMBER_USERNAME);
    cy.get("input[name=Password]").type(MEMBER_PASSWORD + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

function setVersion(value) {
    adminApi("POST", SETTING_URL, { value }, 200);
}

function dbOk(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(
                `Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`,
            );
        }
        return result.rows;
    });
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function daysToNext(dow) {
    const today = new Date().getDay();
    return ((dow - today + 7) % 7) || 7;
}

/** The admin shell must never appear around a member's page (design §0.2). */
function assertNoAdminShell() {
    cy.get("#sidebar-menu").should("not.exist");
    cy.get(".navbar-vertical").should("not.exist");
    cy.get("#fab-container").should("not.exist");
}

/** Wipe every assignment/response/swap/outbox row of the fixture ministry. */
function clearWorkflowRows() {
    const scoped = `
        JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
        JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
        JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
       WHERE vmin.vmin_Name LIKE ?`;

    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vntf.vntf_vasg_ID
           ${scoped}`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vntf.vntf_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    // D19's help_offer rows hang off no occurrence and no assignment — the
    // ministry is in their dedupe key — so they are cleared by type, as
    // `private.volunteer.ministry-group.spec.js` does.
    dbOk(`DELETE FROM volunteer_notification_vntf WHERE vntf_Type = 'help_offer'`);
    dbOk(
        `DELETE vswp FROM volunteer_swap_vswp vswp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vswp.vswp_vasg_ID
           ${scoped}`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vrsp FROM volunteer_response_vrsp vrsp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vrsp.vrsp_vasg_ID
           ${scoped}`,
        [`${PREFIX}%`],
    );
    dbOk(
        `UPDATE volunteer_assignment_vasg vasg ${scoped.replace("WHERE", "SET vasg.vasg_Replaces_vasg_ID = NULL WHERE")}`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE vasg FROM volunteer_assignment_vasg vasg ${scoped}`, [`${PREFIX}%`]);
}

function cleanupFixtures() {
    clearWorkflowRows();

    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vreq.vreq_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vocc FROM volunteer_occurrence_vocc vocc
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vsch FROM volunteer_schedule_vsch vsch
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vqal FROM volunteer_qualification_vqal vqal
           JOIN volunteer_position_vpos vpos ON vpos.vpos_ID = vqal.vqal_vpos_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vpos FROM volunteer_position_vpos vpos
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    // D19: the pool is the ministry's own Group and `grp_ministry_id` is ON DELETE
    // SET NULL, so the group and its memberships go before the ministry row.
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r
           JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE g FROM group_grp g
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [`${EVENT_TITLE}%`]);
}

/** Put the member back on one PENDING Door assignment and nothing else. */
function seedPendingDoorAssignment() {
    clearWorkflowRows();
    adminApi(
        "POST",
        `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
        { positionId: posDoor, personId: PERSON_MEMBER },
        201,
    );
}

// ── fixture ────────────────────────────────────────────────────────────────

before(() => {
    setVersion("v2");
    cleanupFixtures();

    adminApi("POST", `${VOLUNTEER_URL}/ministries`, {
        name: `${PREFIX} Hospitality`,
        description: "member portal volunteering fixture",
    }, 201).then((resp) => {
        ministryId = resp.body.ministry.id;
    });

    // D19: advertise the ministry, so the "I'd like to help" button has a card.
    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}`, {
            helpWanted: true,
            helpWantedText: HELP_WANTED_TEXT,
        }, 200);
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, {
            name: `${PREFIX} Greeters`,
            description: "member portal volunteering fixture",
        }, 201).then((resp) => {
            teamId = resp.body.team.id;
        });
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
            name: `${PREFIX} Door`,
            teamId,
            order: 1,
        }, 201).then((resp) => {
            posDoor = resp.body.position.id;
        });
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
            name: `${PREFIX} Coffee`,
            teamId,
            order: 2,
        }, 201).then((resp) => {
            posCoffee = resp.body.position.id;
        });
    });

    cy.then(() => {
        [PERSON_MEMBER, POOL_MEMBER_A].forEach((personId) => {
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`,
                null,
                [200, 201],
            );
        });
    });

    cy.then(() => {
        [posDoor, posCoffee].forEach((positionId) => {
            [PERSON_MEMBER, POOL_MEMBER_A].forEach((personId) => {
                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/positions/${positionId}/qualifications`,
                    { personId, notes: "" },
                    201,
                );
            });
        });
    });

    cy.then(() => {
        seriesStart = isoDate(daysToNext(0));
        seriesEnd = isoDate(daysToNext(0) + 14);
        adminApi("POST", "/api/events/repeat", {
            Title: EVENT_TITLE,
            Type: CHURCH_SERVICE_TYPE,
            StartTime: "10:30:00",
            EndTime: "11:45:00",
            RecurType: "weekly",
            RecurDOW: "Sunday",
            RangeStart: seriesStart,
            RangeEnd: seriesEnd,
        }, 200);
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`, {
            name: `${PREFIX} Hospitality — Sunday`,
            linkMode: "event_type",
            eventTypeId: CHURCH_SERVICE_TYPE,
            titleFilter: EVENT_TITLE,
            windowStart: seriesStart,
            teamId,
        }, 201).then((resp) => {
            scheduleId = resp.body.schedule.id;
        });
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`, {
            positionId: posDoor,
            minCount: 1,
            maxCount: 2,
        }, [200, 201]);
        adminApi("POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`, {
            positionId: posCoffee,
            minCount: 1,
            maxCount: 1,
        }, [200, 201]);
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`, {
            through: seriesEnd,
        }, 200);
    });

    cy.then(() => {
        adminApi(
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryId}`,
        ).then((resp) => {
            occurrenceId = resp.body.occurrences[0].id;
        });
    });
});

after(() => {
    cleanupFixtures();
    setVersion("v1");
});

// ── getting there ──────────────────────────────────────────────────────────

describe("Member Portal — finding volunteering (#9867)", () => {
    beforeEach(() => {
        seedPendingDoorAssignment();
        freshMemberLogin();
    });

    it("lands the member in the portal with a Volunteering entry in the nav", () => {
        cy.url({ timeout: 10000 }).should("include", "/portal");

        cy.get("#portal-nav").within(() => {
            cy.get(`a[href$="${SCHEDULE_URL}"]`).should("exist").and("contain", "Volunteering");
        });
        assertNoAdminShell();
    });

    it("shows the next commitment on the home page's volunteering card", () => {
        cy.get("#portal-volunteering-card", { timeout: 10000 }).should("exist");
        // Filled from /api/volunteer/me/assignments once the locales are ready.
        cy.get("#portal-volunteering-next", { timeout: 20000 })
            .should("be.visible")
            .and("contain", `${PREFIX} Door`);
        cy.get("#portal-volunteering-pending").should("be.visible");
    });

    it("opens the schedule from the nav, in the portal layout", () => {
        cy.get("#portal-nav").find(`a[href$="${SCHEDULE_URL}"]`).click();

        cy.url().should("include", SCHEDULE_URL);
        cy.get("#volunteer-my-schedule").should("exist");
        cy.get(".volunteer-assignment-card", { timeout: 20000 }).should(
            "have.length.at.least",
            1,
        );
        assertNoAdminShell();

        // The two pages are one nav entry with a tab bar between them.
        cy.get("#portal-volunteer-tab-schedule").should("have.class", "is-active");
        cy.get("#portal-volunteer-tab-opportunities").should("not.have.class", "is-active");
    });

    it("moves between the two pages with the tab bar", () => {
        cy.visit(SCHEDULE_URL);
        cy.get("#portal-volunteer-tab-opportunities").click();

        cy.url().should("include", OPPORTUNITIES_URL);
        cy.get("#volunteer-opportunities").should("exist");
        cy.get("#portal-volunteer-tab-opportunities").should("have.class", "is-active");

        cy.get("#portal-volunteer-tab-schedule").click();
        cy.url().should("include", SCHEDULE_URL);
    });
});

// ── the workflows, unchanged by the move ───────────────────────────────────

describe("Member Portal — volunteering workflows (#9867)", () => {
    beforeEach(() => {
        seedPendingDoorAssignment();
        freshMemberLogin();
    });

    it("responds to an assignment from inside the portal", () => {
        cy.visit(SCHEDULE_URL);
        cy.get(".volunteer-assignment-card", { timeout: 20000 })
            .first()
            .find(".volunteer-accept")
            .click();

        cy.get(".volunteer-assignment-card")
            .first()
            .find(".volunteer-card-status")
            .should("contain.text", "Going");

        // …and the server agrees, not just the card.
        adminApi(
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/staffing`,
            null,
            200,
        ).then((resp) => {
            const rows = resp.body.requirements.flatMap((r) => r.assignments ?? []);
            const mine = rows.find((a) => a.personId === PERSON_MEMBER);
            expect(mine.status).to.eq("accepted");
        });
    });

    it("declines behind a bootbox prompt — the dialog works under the portal layout", () => {
        // bootbox is an admin-shell script; the portal layout loads it too, or
        // this button would do nothing at all (#9867).
        cy.visit(SCHEDULE_URL);
        cy.get(".volunteer-assignment-card", { timeout: 20000 })
            .first()
            .find(".volunteer-decline")
            .click();

        cy.get(".bootbox").should("be.visible");
        cy.get(".bootbox input").type("Away that weekend");
        cy.get(".bootbox .btn-primary").click();

        cy.get(".volunteer-assignment-card")
            .first()
            .find(".volunteer-card-status")
            .should("contain.text", "Declined");
    });

    it("signs up for an open slot from the opportunities page", () => {
        cy.visit(OPPORTUNITIES_URL);

        cy.get(`.volunteer-opportunity-card[data-position-id="${posCoffee}"]`, {
            timeout: 20000,
        })
            .first()
            .find(".volunteer-signup")
            .click();

        // The slot is theirs, and the schedule page says so.
        cy.visit(SCHEDULE_URL);
        cy.get("#assignments-content", { timeout: 20000 })
            .should("be.visible")
            .and("contain", `${PREFIX} Coffee`);
    });

    it("offers to help a ministry that is advertising", () => {
        // Start outside the pool, so the first tap takes the "has been added" path.
        adminApi(
            "DELETE",
            `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${PERSON_MEMBER}`,
            null,
            [200, 404],
        );
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get("#help-wanted-section", { timeout: 20000 }).should("be.visible");
        cy.get(`.volunteer-help-wanted-card[data-ministry-id="${ministryId}"]`)
            .should("contain", HELP_WANTED_TEXT)
            .find(".volunteer-offer-help")
            .click();

        // The toast is `window.CRM.notify` — a Notyf from the core bundle, which
        // the portal layout loads exactly as the admin shell does.
        cy.get(".notyf__toast").should("contain", "you'd like to help");

        adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}/pool`, null, 200).then(
            (resp) => {
                expect(resp.body.members.map((m) => m.personId)).to.include(PERSON_MEMBER);
            },
        );
    });
});

// ── the retired URLs ───────────────────────────────────────────────────────

describe("Member Portal — the retired volunteer URLs (#9867)", () => {
    beforeEach(() => {
        freshMemberLogin();
    });

    it("302s /volunteer/my-schedule into the portal", () => {
        cy.request({ url: LEGACY_SCHEDULE_URL, followRedirect: false }).then((resp) => {
            expect(resp.status).to.eq(302);
            expect(resp.headers.location).to.match(/\/portal\//);
        });
    });

    it("302s /volunteer/opportunities into the portal", () => {
        cy.request({ url: LEGACY_OPPORTUNITIES_URL, followRedirect: false }).then((resp) => {
            expect(resp.status).to.eq(302);
            expect(resp.headers.location).to.match(/\/portal\//);
        });
    });

    it("never leaves a member in the admin shell when they follow an old link", () => {
        cy.visit(LEGACY_SCHEDULE_URL, { failOnStatusCode: false });
        cy.url({ timeout: 10000 }).should("include", "/portal");
        assertNoAdminShell();
    });
});
