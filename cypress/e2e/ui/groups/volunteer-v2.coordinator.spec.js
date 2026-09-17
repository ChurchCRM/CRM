/// <reference types="cypress" />

/**
 * Volunteer v2 — the coordinator dashboard and the S1 → S4 path (#9711).
 *
 * Design §5.1 (screen inventory), §5.2 (S1, "what needs my attention" — gaps
 * first, then pending responses, proposed swaps, upcoming occurrences and the
 * admin-only settings strip), §5.5 (S4, where a gap is actually filled), §5.7
 * (component reuse), §5.8 (the mandatory loading / empty / error / success
 * states) and §3.5 (the person-view V2 pane and the settings panel).
 *
 * The walk this spec proves is the product principle of §0.3 end to end:
 *
 *     dashboard shows the gap → click through to the occurrence →
 *     assign from the eligible picker → back on the dashboard the gap is gone
 *
 * Everything is built and torn down through the REAL APIs, so the page is
 * exercised against data the rest of the system produced.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(),
 * because cy.request() rotates the PHP session cookie (cypress-testing.md).
 * Fixture rows are removed in `before` as well as `after`: an `after` hook does
 * not run when the runner crashes mid-spec.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const LEAD_SETTING_URL = "/admin/api/system/config/iVolunteerReminderLeadHours";
const VOLUNTEER_URL = "/api/volunteer";
const DASHBOARD_URL = "/ministries/dashboard";
const MINISTRIES_URL = "/ministries";

const POOL_MEMBER_A = 8; // qualified for Espresso
const POOL_MEMBER_B = 9; // qualified for Milk Station — the one the picker offers
const CHURCH_SERVICE_TYPE = 1;
/** tony.wade — every flag but Admin; made coordinator of the empty ministry. */
const PERSON_COORDINATOR = 3;

const PREFIX = "UI9711";
const MINISTRY_NAME = `${PREFIX} Coffee Bar`;
/** A second, deliberately empty ministry — the fresh-ministry empty state. */
const EMPTY_MINISTRY_NAME = `${PREFIX} Fresh Ministry`;
const TEAM_NAME = `${PREFIX} Bar Team`;
const POSITION_ESPRESSO = `${PREFIX} Espresso`;
const POSITION_MILK = `${PREFIX} Milk Station`;
const EVENT_TITLE = `${PREFIX} Sunday Service`;

let ministryId = 0;
let teamId = 0;
let posEspresso = 0;
let posMilk = 0;
let scheduleId = 0;
let occurrenceId = 0;
let emptyMinistryId = 0;
let originalVersion = "v1";
let originalLeadHours = "48";
let seriesStart = "";
let seriesEnd = "";

// Local helper — NOT a cy.* command (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

/** Log in as the standard user (person 3), the ministry-coordinator persona. */
function freshCoordinatorLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("standard.username"));
    cy.get("input[name=Password]").type(Cypress.env("standard.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
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

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

function setVersion(value) {
    adminApi("POST", SETTING_URL, { value }, 200);
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

function cleanupFixtures() {
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
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [PERSON_COORDINATOR]);
    // #9869: a ministry created through the API now comes with its own calendar,
    // named after the ministry. `calendars.ministry_id` is ON DELETE SET NULL, so
    // deleting the ministry row directly would leave the calendar behind as an
    // unowned church calendar. It goes first, matched on the same prefix.
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [`${EVENT_TITLE}%`]);
}

/**
 * UC1's Coffee Bar with exactly one gap: Espresso is filled, Milk Station is
 * not. That single gap is what the whole S1 → S4 walk is about.
 */
function buildFixture() {
    adminApi("POST", `${VOLUNTEER_URL}/ministries`, {
        name: MINISTRY_NAME,
        description: "UI fixture",
    }, 201).then((resp) => {
        ministryId = resp.body.ministry.id;
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, {
            name: TEAM_NAME,
            description: "UI fixture",
        }, 201).then((resp) => {
            teamId = resp.body.team.id;
        });
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
            name: POSITION_ESPRESSO,
            description: "",
            teamId,
            order: 1,
        }, 201).then((resp) => {
            posEspresso = resp.body.position.id;
        });
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
            name: POSITION_MILK,
            description: "",
            teamId,
            order: 2,
        }, 201).then((resp) => {
            posMilk = resp.body.position.id;
        });
    });

    // D19: the ministry came with its own pool Group, empty — fill it through the API.
    cy.then(() => {
        [POOL_MEMBER_A, POOL_MEMBER_B].forEach((personId) => {
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`,
                null,
                [200, 201],
            );
        });
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/positions/${posEspresso}/qualifications`, {
            personId: POOL_MEMBER_A,
            notes: "",
        }, 201);
        adminApi("POST", `${VOLUNTEER_URL}/positions/${posMilk}/qualifications`, {
            personId: POOL_MEMBER_B,
            notes: "",
        }, 201);
    });

    cy.then(() => {
        // ONE Sunday, deliberately: the walk below fills the single remaining gap
        // and then asserts the empty state, which only a single-occurrence fixture
        // can reach.
        seriesStart = isoDate(daysToNext(0));
        seriesEnd = seriesStart;
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
            name: `${PREFIX} Coffee Bar — Sunday`,
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
            positionId: posEspresso,
            minCount: 1,
            maxCount: 1,
        }, [200, 201]);
        adminApi("POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`, {
            positionId: posMilk,
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
        dbOk(
            `SELECT vocc_ID AS id FROM volunteer_occurrence_vocc
              WHERE vocc_vsch_ID = ? ORDER BY vocc_OccurrenceDate ASC LIMIT 1`,
            [scheduleId],
        ).then((rows) => {
            occurrenceId = Number(rows[0].id);
        });
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`, {
            positionId: posEspresso,
            personId: POOL_MEMBER_A,
        }, 201);
    });

    // A second ministry with nothing in it at all, coordinated by person 3. It is
    // how the §5.8 empty states are asserted honestly: an ADMIN sees every gap in
    // the church, including whatever other specs left behind, so "no gaps anywhere"
    // is not a state an admin can reliably be put into. A coordinator scoped to one
    // empty ministry can.
    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries`, {
            name: EMPTY_MINISTRY_NAME,
            description: "UI fixture",
        }, 201).then((resp) => {
            emptyMinistryId = resp.body.ministry.id;
        });
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/scopes`, {
            personId: PERSON_COORDINATOR,
            scopeType: "ministry",
            scopeId: emptyMinistryId,
        }, [200, 201]);
    });
}

describe("Volunteer v2 coordinator dashboard (#9711)", () => {
    before(() => {
        adminApi("GET", SETTING_URL, null, 200).then((resp) => {
            originalVersion = resp.body.value ?? resp.body.Value ?? "v1";
        });
        adminApi("GET", LEAD_SETTING_URL, null, 200).then((resp) => {
            originalLeadHours = String(resp.body.value ?? resp.body.Value ?? "48");
        });
        setVersion("v2");
        cleanupFixtures();
        buildFixture();
    });

    after(() => {
        cleanupFixtures();
        adminApi("POST", LEAD_SETTING_URL, { value: originalLeadHours }, 200);
        setVersion(originalVersion);
    });

    beforeEach(() => {
        freshAdminLogin();
    });

    describe("S1 — what needs my attention (§5.2)", () => {
        it("renders the five panels in the documented order", () => {
            cy.visit(DASHBOARD_URL);

            cy.get("#volunteer-dashboard").should("exist");
            cy.get("#volunteer-gaps-card").should("be.visible");
            cy.get("#volunteer-pending-card").should("be.visible");
            cy.get("#volunteer-swaps-card").should("be.visible");
            cy.get("#volunteer-upcoming-card").should("be.visible");
            cy.get("#volunteerSettings").should("exist");
        });

        it("shows the loading block first and hides it once content arrives (§5.8)", () => {
            cy.intercept("GET", "**/api/volunteer/dashboard*", (req) => {
                req.on("response", (res) => {
                    res.setDelay(600);
                });
            }).as("dashboardLoad");

            cy.visit(DASHBOARD_URL);
            cy.get("#volunteer-gaps-loading").should("be.visible");
            cy.wait("@dashboardLoad");
            cy.get("#volunteer-gaps-loading").should("not.be.visible");
            cy.get("#volunteer-gaps-content").should("be.visible");
        });

        it("shows the retry-able error block when the aggregate fails (§5.8)", () => {
            cy.intercept("GET", "**/api/volunteer/dashboard*", {
                statusCode: 500,
                body: { success: false, message: "boom" },
            }).as("dashboardFail");

            cy.visit(DASHBOARD_URL);
            cy.wait("@dashboardFail");
            cy.get("#volunteer-gaps-error").should("be.visible");
            cy.get("#volunteer-gaps-error .volunteer-retry").should("be.visible");
        });

        it("lists the Milk Station gap with a link to the occurrence", () => {
            cy.visit(DASHBOARD_URL);

            cy.get("#volunteer-gaps-content").should("be.visible");
            cy.get("#volunteer-gaps-list").should("contain", POSITION_MILK);
            cy.get(
                `#volunteer-gaps-list a.volunteer-gap-link[data-occurrence-id="${occurrenceId}"]`,
            ).should("exist");
        });

        it("lists the pending Espresso response and the upcoming occurrence", () => {
            cy.visit(DASHBOARD_URL);
            cy.get("#volunteer-pending-content").should("be.visible");
            cy.get("#volunteer-pending-list").should("contain", POSITION_ESPRESSO);

            cy.get("#volunteer-upcoming-content").should("be.visible");
            cy.get("#volunteer-upcoming-table tbody").should("contain", `${PREFIX} Coffee Bar`);
        });

        it("offers the quick actions §5.2 requires", () => {
            cy.visit(DASHBOARD_URL);
            // The guided-setup link is gone; "New ministry" replaced it, and it is
            // a button rather than a link because it opens a modal.
            cy.get('#volunteer-quick-actions a[href$="/volunteer/setup"]').should("not.exist");
            cy.get("#volunteer-quick-actions #ministry-new-btn").should("exist");
            // "My ministries and teams" is no longer a quick action: the
            // sidebar's Ministries heading lists them, and the card in the
            // right-hand column names the teams.
            cy.get('#volunteer-quick-actions a[href$="/ministries"]').should("not.exist");
        });

        it("renders localized strings from the bundle, not raw keys", () => {
            cy.visit(DASHBOARD_URL);
            cy.get("#volunteer-gaps-card").should("contain", "Needs filling");
            cy.get("#volunteer-pending-card").should("contain", "Waiting for a reply");
            cy.get("#volunteer-swaps-card").should("contain", "Substitution requests");
            cy.get("#volunteer-upcoming-card").should("contain", "Upcoming");
        });
    });

    describe("S1 → S4 — filling the gap (§5.2, §5.5)", () => {
        it("clicks through, assigns from the eligible picker, and the gap is gone", () => {
            cy.visit(DASHBOARD_URL);

            cy.get(
                `#volunteer-gaps-list a.volunteer-gap-link[data-occurrence-id="${occurrenceId}"][data-position-id="${posMilk}"]`,
            ).click();

            cy.url().should("include", `/ministries/occurrences/${occurrenceId}`);

            // S4: assign the one qualified person from the eligible picker.
            cy.get(`.volunteer-assign-btn[data-position-id="${posMilk}"]`).click();
            cy.get("#volunteer-assign-modal").should("be.visible");
            cy.get("#assign-person-select").should("exist").then(($el) => {
                const ts = $el[0].tomselect;
                expect(ts, "the eligible picker is a TomSelect").to.exist;
                ts.setValue(String(POOL_MEMBER_B));
            });
            cy.get("#assign-save").click();
            cy.get("#volunteer-assign-modal").should("not.be.visible");

            // Back on the dashboard that gap has gone. Asserted on THIS row rather
            // than on the panel's empty state: an admin sees every gap in the church,
            // so the panel may legitimately still list other ministries' work.
            cy.visit(DASHBOARD_URL);
            cy.get("#volunteer-gaps-content, #volunteer-gaps-empty").should("exist");
            cy.get(
                `#volunteer-gaps-list a.volunteer-gap-link[data-occurrence-id="${occurrenceId}"][data-position-id="${posMilk}"]`,
            ).should("not.exist");
            cy.get("#volunteer-gaps-list").should("not.contain", POSITION_MILK);
        });
    });

    describe("The admin-only settings strip (§5.2 item 5, U8)", () => {
        it("changes the reminder lead time and the change persists", () => {
            cy.visit(DASHBOARD_URL);

            cy.get("#volunteerSettings").should("be.visible");
            // The panel renders its inputs first and fills them from
            // `/admin/api/system/config` a moment later, so typing before that
            // second pass lands prepends to a value that is about to be replaced.
            // Waiting for the fetched value is what makes `clear()` mean anything.
            cy.get('#volunteerSettings input[name="iVolunteerReminderLeadHours"]')
                .should("have.value", originalLeadHours)
                .clear()
                .type("12");
            cy.get("#volunteerSettings #settingsPanelSaveBtn").click();

            cy.then(() => {
                adminApi("GET", LEAD_SETTING_URL, null, 200).then((resp) => {
                    expect(String(resp.body.value ?? resp.body.Value)).to.eq("12");
                });
            });
        });

        it("carries the cron hint §3.6 requires", () => {
            cy.visit(DASHBOARD_URL);
            cy.get("#volunteer-cron-hint").should("exist").and("contain", "timerjobs");
        });
    });

    describe("The person view V2 pane (§3.5, §3.8 surface 2)", () => {
        it("shows the person's qualification and upcoming assignment, linking into /volunteer", () => {
            cy.visit(`/people/view/${POOL_MEMBER_A}`);

            cy.get("#nav-item-volunteer-v2").should("exist").click();
            cy.get("#volunteer-v2").should("be.visible");
            cy.get("#person-volunteer-v2-qualifications").should("contain", POSITION_ESPRESSO);
            cy.get("#person-volunteer-v2-assignments")
                .should("contain", POSITION_ESPRESSO)
                .find(`a[href*="/ministries/occurrences/${occurrenceId}"]`)
                .should("exist");
        });

        it("shows a first-class empty state for a person with nothing (§5.8)", () => {
            // Person 5 is a seeded pool member this fixture never qualifies.
            cy.visit("/people/view/5");
            cy.get("#nav-item-volunteer-v2").click();
            cy.get("#person-volunteer-v2-empty").should("be.visible");
        });

        it("leaves the V1 pane and its ids untouched when both are on", () => {
            setVersion("both");
            freshAdminLogin();
            cy.visit(`/people/view/${POOL_MEMBER_A}`);
            cy.get("#nav-item-volunteer").should("exist");
            cy.get("#nav-item-volunteer-v2").should("exist");
            cy.get("#volunteer").should("exist");
            cy.get("#volunteer-v2").should("exist");
            setVersion("v2");
        });
    });

    describe("A coordinator with a fresh ministry (§5.8 empty states)", () => {
        it("shows every panel's empty state rather than an empty table", () => {
            freshCoordinatorLogin();
            cy.visit(DASHBOARD_URL);

            cy.get("#volunteer-gaps-empty").should("be.visible");
            cy.get("#volunteer-pending-empty").should("be.visible");
            cy.get("#volunteer-swaps-empty").should("be.visible");
            cy.get("#volunteer-upcoming-empty").should("be.visible");

            // The scope panel still names the ministry, so the page is a starting
            // point rather than a dead end.
            cy.get("#volunteer-scope-ministries").should("contain", EMPTY_MINISTRY_NAME);

            // The settings strip is administrator-only (§5.2 item 5).
            cy.get("#volunteerSettings").should("not.exist");
        });
    });

    // The ministries list page that used to answer this is gone: the sidebar's
    // Ministries heading lists the ministries, and the dashboard's own card
    // names the teams under them — which is the half a pure team leader needs,
    // since they get no ministry entry in the menu at all (§4.6).
    describe("The team-leader entry point (§4.6, design gap 1)", () => {
        it("the dashboard names the ministries and the teams the viewer runs", () => {
            cy.visit(DASHBOARD_URL);
            cy.get("#volunteer-scope-ministries").should("contain", MINISTRY_NAME);
            cy.get("#volunteer-scope-teams").should("contain", TEAM_NAME);
        });

        it("the Ministries menu links straight at the ministry", () => {
            cy.visit(DASHBOARD_URL);
            cy.get(`a[href$="${MINISTRIES_URL}/${ministryId}"]`).should("exist");
            cy.get(`a[href$="${MINISTRIES_URL}"]`).should("not.exist");
        });
    });

    describe("The ministry page Schedules tab (§5.4)", () => {
        it("lists the schedule and offers a generate action", () => {
            cy.visit(`/ministries/${ministryId}`);
            cy.get("#nav-item-schedules").should("exist").click();
            cy.get("#schedules").should("be.visible");
            cy.get("#volunteerSchedulesTable tbody").should("contain", `${PREFIX} Coffee Bar`);
            cy.get(`.volunteer-schedule-generate[data-schedule-id="${scheduleId}"]`).should("exist");
        });
    });
});
