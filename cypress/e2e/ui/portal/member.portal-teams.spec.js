/// <reference types="cypress" />

/**
 * Member Portal (MP7, #9868) — My Teams.
 *
 * The end-to-end proof of Member Portal design §5.5 and P17: a team leader who
 * holds an ORDINARY MEMBER LOGIN runs their team from the portal, and never sees
 * the admin shell doing it.
 *
 * Persona: person 100, Lena Black — `usr_EditSelf=1`, no admin flag, so
 * `User::isEditSelfExclusive()` is true and this login has no admin shell to fall
 * back to. `user_usr.usr_UserName` is `VARCHAR(32)` and the seeded address is 37
 * characters, so the login form must be given the truncated form. An
 * administrator grants her a `team` scope through `/api/volunteer/scopes`, which
 * is the only way one is ever made (§4.6), and everything after that is her.
 *
 * The walk is the acceptance criterion of the issue, in order: the nav entry
 * appears, the team opens, a qualification tick saves, a schedule is created,
 * its dates are generated, one of them is staffed — and the two refusals hold:
 * another team's page is the portal's own 403, and `/volunteer/ministries/{id}`
 * never renders the admin shell.
 *
 * `limited.user` is the control: a member with no scope at all, who must not see
 * the entry and must be refused the pages.
 *
 * Order inside every hook is API setup → login → `cy.visit()`, because
 * `cy.request()` rotates the PHP session cookie (cypress-testing.md). Fixtures
 * are removed in `before` as well as `after`: an `after` hook does not run when
 * the runner crashes mid-spec.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";
const TEAMS_URL = "/portal/teams";

/** The team leader (person 100). */
const LEADER_USERNAME = "lena.black.editself.notes@exampl";
const LEADER_PASSWORD = "changeme";
const PERSON_LEADER = 100;

/**
 * A member with no volunteer scope at all: `limited.user` (person 4) — EditSelf
 * only, every other permission flag 0, non-admin. The control for "the nav entry
 * and the pages are for team leaders and nobody else".
 */
const PLAIN_USERNAME = "limited.user";
const PLAIN_PASSWORD = "changeme";

/** Seeded member of group 1 "Angels class" — the person to qualify and assign. */
const POOL_MEMBER = 8;

const PREFIX = "MP9868";

let ministryId = 0;
let teamLed = 0;
let teamOther = 0;
let posDoor = 0;

// ── helpers (local functions, NOT cy.* commands — cypress-testing.md) ───────

function login(username, password) {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(username);
    cy.get("input[name=Password]").type(`${password}{enter}`);
    cy.url({ timeout: 10000 }).should("not.include", "/session/begin");
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

function cleanupFixtures() {
    const like = [`${PREFIX}%`];

    dbOk(
        `DELETE vasg FROM volunteer_assignment_vasg vasg
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vreq.vreq_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vocc FROM volunteer_occurrence_vocc vocc
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vsch FROM volunteer_schedule_vsch vsch
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vqal FROM volunteer_qualification_vqal vqal
           JOIN volunteer_position_vpos vpos ON vpos.vpos_ID = vqal.vqal_vpos_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vpos FROM volunteer_position_vpos vpos
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vscp FROM volunteer_scope_vscp vscp
           JOIN volunteer_team_vtem vtem ON vtem.vtem_ID = vscp.vscp_ScopeId
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vscp.vscp_ScopeType = 'team' AND vmin.vmin_Name LIKE ?`,
        like,
    );
    // D19: `grp_ministry_id` is ON DELETE SET NULL, so the pool Group and its
    // memberships go BEFORE the ministry row or an orphan group is left behind.
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r
           JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE g FROM group_grp g
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    // #9869: a ministry created through the API now comes with its own calendar,
    // named after the ministry. `calendars.ministry_id` is ON DELETE SET NULL, so
    // deleting the ministry row directly would leave the calendar behind as an
    // unowned church calendar. It goes first, matched on the same prefix.
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, like);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, like);
}

// ── fixture ────────────────────────────────────────────────────────────────

before(() => {
    setVersion("v2");
    cleanupFixtures();

    adminApi("POST", `${VOLUNTEER_URL}/ministries`, {
        name: `${PREFIX} Hospitality`,
        description: "My Teams fixture",
    }, 201).then((resp) => {
        ministryId = resp.body.ministry.id;
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, {
            name: `${PREFIX} Greeters`,
            description: "the team Lena leads",
        }, 201).then((resp) => {
            teamLed = resp.body.team.id;
        });
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, {
            name: `${PREFIX} Ushers`,
            description: "somebody else's team",
        }, 201).then((resp) => {
            teamOther = resp.body.team.id;
        });
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
            name: `${PREFIX} Door`,
            description: "",
            teamId: teamLed,
            order: 1,
        }, 201).then((resp) => {
            posDoor = resp.body.position.id;
        });
    });

    // The grant that makes person 100 a team leader, made by an administrator
    // through the real scope API — the whole of the D14 revision (P17).
    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/scopes`, {
            personId: PERSON_LEADER,
            scopeType: "team",
            scopeId: teamLed,
        }, [200, 201]);
    });

    // Somebody for the grid to have a row for.
    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${POOL_MEMBER}`, null, [200, 201]);
    });
});

after(() => {
    cleanupFixtures();
});

// ── the spec ───────────────────────────────────────────────────────────────

describe("Member Portal — My Teams", () => {
    describe("A member who leads a team", () => {
        it("Finds My Teams in the portal navigation, between Volunteering and My Family", () => {
            login(LEADER_USERNAME, LEADER_PASSWORD);
            cy.url({ timeout: 10000 }).should("include", "/portal");

            cy.get("#portal-nav .portal-nav-link").then(($links) => {
                const labels = [...$links].map((el) => el.textContent.trim());
                const teams = labels.indexOf("My Teams");
                expect(teams, "My Teams is in the navigation").to.be.greaterThan(-1);
                expect(labels.indexOf("Volunteering")).to.be.lessThan(teams);
                expect(labels.indexOf("My Family")).to.be.greaterThan(teams);
            });

            // And no admin shell anywhere on the way.
            cy.get("#sidebar").should("not.exist");
            cy.get(".navbar-vertical").should("not.exist");
        });

        it("Lists the team, its ministry and its position count", () => {
            login(LEADER_USERNAME, LEADER_PASSWORD);
            cy.visit(TEAMS_URL);

            cy.get(".portal-team-card").should("have.length", 1);
            cy.get(".portal-team-card").should("contain.text", `${PREFIX} Greeters`);
            cy.get(".portal-team-card").should("contain.text", `${PREFIX} Hospitality`);
            cy.get(".portal-team-card").should("not.contain.text", `${PREFIX} Ushers`);
        });

        it("Opens the team on its Positions tab, and hides what a team leader may not do", () => {
            login(LEADER_USERNAME, LEADER_PASSWORD);
            cy.visit(`${TEAMS_URL}/${teamLed}`);

            cy.get("#portal-team").should("exist");
            cy.get("#volunteerPositionsTable tbody tr", { timeout: 15000 })
                .should("contain.text", `${PREFIX} Door`);

            // The four tabs and no more.
            cy.get("#portal-team-tabs .nav-link").should("have.length", 4);

            // Hidden on the portal (design §5.5).
            cy.get("#volunteer-teams-card").should("not.exist");
            cy.get("#volunteer-scope-panel").should("not.exist");
            cy.get("#volunteer-help-wanted").should("not.exist");
            cy.get("#qualification-add-person").should("not.exist");
            cy.get("#qualification-cart-btn").should("not.exist");
            cy.get("#qualification-team-filter").should("not.exist");
            cy.get("#occurrence-team-filter").should("not.exist");
            cy.get("#team-add-btn").should("not.exist");
            cy.get("#teamModal").should("not.exist");

            // And still no admin shell.
            cy.get("#sidebar").should("not.exist");
        });

        it("Ticks a qualification on the Volunteers tab and it saves", () => {
            login(LEADER_USERNAME, LEADER_PASSWORD);
            cy.visit(`${TEAMS_URL}/${teamLed}`);

            cy.get("#nav-item-volunteers").click();
            cy.get("#volunteerQualificationsTable tbody .volunteer-qual-toggle", { timeout: 15000 })
                .should("exist");

            cy.get(`.volunteer-qual-toggle[data-person-id="${POOL_MEMBER}"][data-position-id="${posDoor}"]`)
                .check();

            // The box is disabled for the duration of its own write, so waiting for
            // it to come back is waiting for the request to have finished.
            cy.get(`.volunteer-qual-toggle[data-person-id="${POOL_MEMBER}"][data-position-id="${posDoor}"]`, {
                timeout: 15000,
            }).should("not.be.disabled").and("be.checked");

            // The write is the assertion, not the toast: the row must be in the
            // database and active (§2.7 — revocation is deactivation).
            cy.then(() => {
                dbOk(
                    `SELECT vqal_ID FROM volunteer_qualification_vqal
                      WHERE vqal_vpos_ID = ? AND vqal_per_ID = ? AND vqal_Active = 1`,
                    [posDoor, POOL_MEMBER],
                ).then((rows) => {
                    expect(rows.length, "the qualification was written").to.eq(1);
                });
            });
        });

        it("Creates a schedule for the team, generates its dates and staffs one", () => {
            login(LEADER_USERNAME, LEADER_PASSWORD);
            cy.visit(`${TEAMS_URL}/${teamLed}`);

            // ── create ──
            cy.get("#nav-item-schedules").click();
            cy.get("#schedule-add-btn", { timeout: 15000 }).click();
            cy.get("#scheduleModal", { timeout: 10000 }).should("be.visible");

            cy.get("#schedule-form-name").clear().type(`${PREFIX} Greeters — Sunday`);
            cy.get("#schedule-form-link-mode").select("standalone");
            cy.get("#schedule-form-dow").select("Sunday");
            cy.get("#schedule-form-start-time").clear().type("09:00");
            cy.get("#schedule-form-end-time").clear().type("10:00");
            cy.get("#schedule-form-window-start").clear().type(isoDate(0));
            cy.get("#schedule-form-window-end").clear().type(isoDate(28));
            cy.get("#schedule-form-save").click();

            cy.get("#volunteerSchedulesTable tbody tr", { timeout: 15000 })
                .should("contain.text", `${PREFIX} Greeters — Sunday`);

            // ── generate ──
            cy.get("#volunteerSchedulesTable tbody tr")
                .first()
                .find("[data-bs-toggle='dropdown']")
                .click();
            cy.get(".volunteer-schedule-generate").first().click();

            cy.then(() => {
                dbOk(
                    `SELECT vocc.vocc_ID FROM volunteer_occurrence_vocc vocc
                       JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
                      WHERE vsch.vsch_vtem_ID = ?`,
                    [teamLed],
                ).then((rows) => {
                    expect(rows.length, "dates were generated").to.be.greaterThan(0);
                });
            });

            // ── staff one ──
            cy.get("#nav-item-occurrences").click();
            cy.get("#volunteerOccurrencesTable tbody tr td a", { timeout: 15000 })
                .first()
                .click();

            cy.url({ timeout: 10000 }).should("include", `${TEAMS_URL}/${teamLed}/occurrences/`);
            cy.get("#volunteer-occurrence").should("exist");
            cy.get(".portal-breadcrumb").should("contain.text", `${PREFIX} Greeters`);
            cy.get("#sidebar").should("not.exist");

            // A new schedule starts with every position needed once, so this date
            // has a Door card with an Assign control on it.
            cy.get("#requirements-content .volunteer-assign-btn", { timeout: 15000 })
                .first()
                .click();

            cy.get("#volunteer-assign-modal", { timeout: 10000 }).should("be.visible");
            // The picker is a TomSelect over a hidden `<select>`; the bundle reads the
            // underlying control's value, so setting it is what the coordinator's click
            // ends up doing.
            cy.get("#assign-person-select option", { timeout: 10000 }).should("have.length.greaterThan", 1);
            cy.get("#assign-person-select").select(String(POOL_MEMBER), { force: true });
            cy.get("#assign-save").click();

            cy.get("#requirements-content", { timeout: 15000 }).should("contain.text", "Herminia");

            cy.then(() => {
                dbOk(
                    `SELECT vasg.vasg_ID FROM volunteer_assignment_vasg vasg
                       JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
                       JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
                      WHERE vsch.vsch_vtem_ID = ? AND vasg.vasg_per_ID = ?`,
                    [teamLed, POOL_MEMBER],
                ).then((rows) => {
                    expect(rows.length, "the assignment was written").to.eq(1);
                });
            });
        });
    });

    describe("Refusals", () => {
        it("Another team's page is the portal's own 403, not the admin access-denied page", () => {
            login(LEADER_USERNAME, LEADER_PASSWORD);
            cy.visit(`${TEAMS_URL}/${teamOther}`, { failOnStatusCode: false });

            cy.get(".portal-error-403").should("exist");
            cy.contains("You cannot open this page").should("exist");
            cy.url().should("not.include", "/v2/access-denied");
            cy.get("#sidebar").should("not.exist");
        });

        it("The admin ministry page never renders for a member login", () => {
            login(LEADER_USERNAME, LEADER_PASSWORD);
            cy.visit(`/volunteer/ministries/${ministryId}`, { failOnStatusCode: false });

            cy.get("#volunteer-ministry").should("not.exist");
            cy.get("#sidebar").should("not.exist");
            cy.url().should("not.include", `/volunteer/ministries/${ministryId}`);
        });

        it("A member who leads nothing has no My Teams entry and cannot open the pages", () => {
            login(PLAIN_USERNAME, PLAIN_PASSWORD);
            cy.url({ timeout: 10000 }).should("include", "/portal");

            cy.get("#portal-nav").should("not.contain.text", "My Teams");

            cy.visit(TEAMS_URL, { failOnStatusCode: false });
            cy.get(".portal-error-403").should("exist");

            cy.visit(`${TEAMS_URL}/${teamLed}`, { failOnStatusCode: false });
            cy.get(".portal-error-403").should("exist");
        });
    });
});
