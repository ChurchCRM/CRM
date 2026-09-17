/// <reference types="cypress" />

/**
 * Member Portal — #9869 scenario 2, "a team leader on a member login", as ONE
 * end-to-end run.
 *
 * Epic #8977, issue #9869, design `.agents/skills/churchcrm/member-portal-design.md`
 * §5.5 and P17; `volunteer-v2-design.md` §4.4, §4.6 and the D14 revision.
 *
 * `member.portal-teams.spec.js` (MP7) proves each control on the team page works. This
 * run proves the **claim the epic makes about people**: that a church can hand the
 * Sunday greeters to a volunteer — somebody with an ordinary self-service login and no
 * staff account at all — and that person can set their team up from nothing and run
 * it, without anybody giving them a key to the building.
 *
 * So this one starts the team EMPTY. MP7's spec seeds the position through the API
 * before the leader arrives; here the leader creates it, which is the step that
 * decides whether "run their team" is true or just "tick boxes on somebody else's
 * team".
 *
 * The run, in order:
 *
 *     granted a team scope ──► My Teams appears in the portal nav
 *        │
 *        ├─ open the team ───► four tabs, and none of the ministry-level controls
 *        ├─ add a position ──► from nothing; it lands in the table
 *        ├─ tick a qualification ► somebody is now allowed to do it
 *        ├─ create a schedule ──► standalone, weekly
 *        ├─ generate its dates ─► occurrences appear
 *        └─ staff one ──────────► the qualified volunteer is on it
 *
 *     …and the refusals, which are half the point:
 *        another team's page ──► the PORTAL's 403, never the admin shell's
 *        the admin ministry page ► never renders for this login
 *        a member with no scope ► no nav entry and no pages
 *
 * Personas (cypress/data/seed.sql):
 *
 *   person 100  Lena Black    `usr_EditSelf=1`, no admin flag. The leader. Her
 *               `usr_UserName` is stored truncated to the column's 32 characters.
 *   person 4    limited.user  EditSelf only, no volunteer scope — the control.
 *   person 8    a seeded member to qualify and assign.
 *
 * Order inside every hook is API setup → login → `cy.visit()`: `cy.request()` rotates
 * the PHP session cookie (cypress-testing.md). Cleanup runs in `before` as well as
 * `after` — an `after` hook does not run when the runner crashes mid-spec.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";
const TEAMS_URL = "/portal/teams";

const LEADER_USERNAME = "lena.black.editself.notes@example.com";
const LEADER_PASSWORD = "changeme";
const PERSON_LEADER = 100;

const PLAIN_USERNAME = "limited.user";
const PLAIN_PASSWORD = "changeme";

/** Seeded member of group 1 "Angels class" — the person to qualify and assign. */
const POOL_MEMBER = 8;

const PREFIX = "E2E9869TL";
const POSITION_NAME = `${PREFIX} Welcome Desk`;
const SCHEDULE_NAME = `${PREFIX} Greeters — Sunday`;

let originalVersion = "v1";
let ministryId = 0;
let teamLed = 0;
let teamOther = 0;

// ── helpers (local functions, NOT cy.* commands — cypress-testing.md) ───────

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

function login(username, password) {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(username);
    cy.get("input[name=Password]").type(`${password}{enter}`);
    cy.url({ timeout: 10000 }).should("not.include", "/session/begin");
}

function leaderLogin() {
    login(LEADER_USERNAME, LEADER_PASSWORD);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** No admin shell, ever, on any page this login reaches (design P10). */
function assertNoAdminShell() {
    cy.get("#sidebar").should("not.exist");
    cy.get("#sidebar-menu").should("not.exist");
    cy.get(".navbar-vertical").should("not.exist");
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
    // The ministry's own calendar before the ministry: the FK is ON DELETE SET
    // NULL, so one left behind survives as an unowned church calendar (#9869).
    dbOk(
        `DELETE c FROM calendars c
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = c.ministry_id
          WHERE m.vmin_Name LIKE ?`,
        like,
    );
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, like);
}

// ── the church, the day before the volunteer was asked ─────────────────────

before(() => {
    adminApi("GET", SETTING_URL, null, 200).then((resp) => {
        originalVersion = resp.body.value ?? resp.body.data ?? "v1";
    });
    setVersion("v2");

    cleanupFixtures();

    adminApi(
        "POST",
        `${VOLUNTEER_URL}/ministries`,
        { name: `${PREFIX} Hospitality`, description: "team leader scenario" },
        201,
    ).then((resp) => {
        ministryId = resp.body.ministry.id;
    });

    cy.then(() => {
        // The ministry came with one team (D18); that is the one she gets.
        adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`).then(
            (resp) => {
                teamLed = resp.body.teams[0].id;
            },
        );

        // A second team she was NOT given, so "refused elsewhere" has somewhere
        // real to be refused.
        adminApi(
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}/teams`,
            { name: `${PREFIX} Ushers`, description: "team leader scenario" },
            201,
        ).then((resp) => {
            teamOther = resp.body.team.id;
        });
    });

    cy.then(() => {
        // Two people in the pool: the leader herself, and somebody to staff.
        for (const personId of [PERSON_LEADER, POOL_MEMBER]) {
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`,
                null,
                [200, 201],
            );
        }

        // The grant — the only way a team scope is ever made (§4.6). Everything
        // after this line is hers.
        adminApi(
            "POST",
            `${VOLUNTEER_URL}/scopes`,
            { personId: PERSON_LEADER, scopeType: "team", scopeId: teamLed },
            [200, 201],
        );
    });
});

after(() => {
    cleanupFixtures();
    setVersion(originalVersion);
});

describe("Member Portal e2e — #9869 scenario 2, a team leader on a member login", () => {
    it("is offered My Teams the moment the scope is granted", () => {
        leaderLogin();
        cy.url().should("include", "/portal");
        assertNoAdminShell();

        cy.get("#portal-nav .portal-nav-link").then(($links) => {
            const labels = [...$links].map((el) => el.textContent.trim());
            const teams = labels.indexOf("My Teams");
            expect(teams, "My Teams is in the navigation").to.be.greaterThan(-1);
            // Design §5 fixes where it sits, not just that it is there.
            expect(labels.indexOf("Volunteering")).to.be.lessThan(teams);
            expect(labels.indexOf("My Family")).to.be.greaterThan(teams);
        });

        cy.visit(TEAMS_URL);
        cy.get(".portal-team-card")
            .should("have.length", 1)
            .and("contain.text", `${PREFIX} Hospitality`);
        // The team she was not given is not on her list.
        cy.get(".portal-team-card").should("not.contain.text", `${PREFIX} Ushers`);
    });

    it("opens the team, and is shown only what a team leader may do", () => {
        leaderLogin();
        cy.visit(`${TEAMS_URL}/${teamLed}`);

        cy.get("#portal-team", { timeout: 15000 }).should("exist");
        cy.get("#portal-team-tabs .nav-link").should("have.length", 4);

        // Everything above a team belongs to a coordinator, and is absent here
        // (design §5.5). The API refuses these too — that is the API spec's job;
        // this asserts she is not offered them in the first place.
        for (const hidden of [
            "#volunteer-teams-card",
            "#volunteer-scope-panel",
            "#volunteer-help-wanted",
            "#qualification-add-person",
            "#qualification-team-filter",
            "#occurrence-team-filter",
            "#team-add-btn",
        ]) {
            cy.get(hidden).should("not.exist");
        }
        assertNoAdminShell();
    });

    it("adds the first position to a team that had none", () => {
        leaderLogin();
        cy.visit(`${TEAMS_URL}/${teamLed}`);

        // Wait for the team to have LOADED, not just for the static table markup:
        // the modal's team select is filled from the loaded team when it opens, so
        // clicking Add before the fetch lands gives an empty select that never
        // refills. With no positions yet, "loaded" is the empty state.
        cy.get("#positions-loading", { timeout: 15000 }).should("not.be.visible");
        cy.get("#positions-empty", { timeout: 15000 }).should("be.visible");

        cy.get("#position-add-btn").should("be.visible").click();
        cy.get("#positionModal", { timeout: 10000 }).should("be.visible");
        cy.get("#position-form-team option", { timeout: 10000 }).should(
            "have.length",
            1,
        );
        cy.get("#position-form-name").clear().type(POSITION_NAME);
        cy.get("#position-form-save").click();
        cy.get("#positionModal", { timeout: 10000 }).should("not.be.visible");

        cy.get("#volunteerPositionsTable tbody tr", { timeout: 15000 }).should(
            "contain.text",
            POSITION_NAME,
        );

        // The row is real, not just drawn.
        adminApi(
            "GET",
            `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
            null,
            200,
        ).then((resp) => {
            const mine = resp.body.positions.find((p) => p.name === POSITION_NAME);
            expect(mine, "the position was created").to.exist;
            expect(mine.teamId, "…on her team, not somewhere else").to.eq(teamLed);
        });
    });

    it("ticks a qualification, so somebody is allowed to stand there", () => {
        leaderLogin();
        cy.visit(`${TEAMS_URL}/${teamLed}`);

        cy.get("#nav-item-volunteers").click();
        cy.get("#volunteerQualificationsTable tbody .volunteer-qual-toggle", {
            timeout: 15000,
        })
            .first()
            .click();

        // The tick is a write; read it back from the database rather than the cell
        // it was made in. The position is this team's only one, so any row against
        // it is the one that was just granted.
        cy.then(() => {
            dbOk(
                `SELECT vqal.vqal_ID
                   FROM volunteer_qualification_vqal vqal
                   JOIN volunteer_position_vpos vpos ON vpos.vpos_ID = vqal.vqal_vpos_ID
                  WHERE vpos.vpos_Name = ? AND vqal.vqal_Active = 1`,
                [POSITION_NAME],
            ).then((rows) => {
                expect(rows.length, "a qualification was granted").to.be.at.least(1);
            });
        });
    });

    it("creates a schedule, generates its dates, and staffs one of them", () => {
        leaderLogin();
        cy.visit(`${TEAMS_URL}/${teamLed}`);

        // ── the schedule ────────────────────────────────────────────────────
        cy.get("#nav-item-schedules").click();
        cy.get("#schedule-add-btn", { timeout: 15000 }).click();
        cy.get("#scheduleModal", { timeout: 10000 }).should("be.visible");

        cy.get("#schedule-form-name").clear().type(SCHEDULE_NAME);
        cy.get("#schedule-form-link-mode").select("standalone");
        cy.get("#schedule-form-dow").select("Sunday");
        cy.get("#schedule-form-start-time").clear().type("09:00");
        cy.get("#schedule-form-end-time").clear().type("10:00");
        cy.get("#schedule-form-window-start").clear().type(isoDate(0));
        cy.get("#schedule-form-window-end").clear().type(isoDate(28));
        cy.get("#schedule-form-save").click();

        cy.get("#volunteerSchedulesTable tbody tr", { timeout: 15000 }).should(
            "contain.text",
            SCHEDULE_NAME,
        );

        // ── its dates ───────────────────────────────────────────────────────
        // The generate action lives behind the row's dropdown menu.
        cy.get("#volunteerSchedulesTable tbody tr")
            .contains(SCHEDULE_NAME)
            .closest("tr")
            .find("[data-bs-toggle='dropdown']")
            .click();
        cy.get(".volunteer-schedule-generate").first().click();

        cy.get("#nav-item-occurrences").click();
        cy.get("#volunteerOccurrencesTable tbody tr", { timeout: 15000 }).should(
            "have.length.at.least",
            1,
        );

        // ── one of them, staffed ────────────────────────────────────────────
        cy.get("#volunteerOccurrencesTable tbody tr td a", { timeout: 15000 })
            .first()
            .click();

        cy.get("#volunteer-occurrence", { timeout: 15000 }).should("exist");
        assertNoAdminShell();

        cy.get("#requirements-content .volunteer-assign-btn", { timeout: 15000 })
            .first()
            .click();
        cy.get("#volunteer-assign-modal", { timeout: 10000 }).should("be.visible");
        cy.get("#assign-person-select option", { timeout: 10000 }).should(
            "have.length.greaterThan",
            1,
        );
        cy.get("#assign-person-select").select(String(POOL_MEMBER), { force: true });
        cy.get("#assign-save").click();

        cy.get("#requirements-content", { timeout: 15000 }).should("not.be.empty");

        // The assignment exists, on her team, for the person she picked.
        dbOk(
            `SELECT vasg.vasg_per_ID
               FROM volunteer_assignment_vasg vasg
               JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
               JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
              WHERE vsch.vsch_Name = ?`,
            [SCHEDULE_NAME],
        ).then((rows) => {
            expect(rows.length, "one assignment was made").to.eq(1);
            expect(Number(rows[0].vasg_per_ID)).to.eq(POOL_MEMBER);
        });
    });

    it("is refused the team she was not given — in the portal's own words", () => {
        leaderLogin();
        cy.visit(`${TEAMS_URL}/${teamOther}`, { failOnStatusCode: false });

        // The PORTAL's 403, drawn in the member's theme, never the admin shell's
        // access-denied page (design P10).
        cy.get(".portal-shell", { timeout: 10000 }).should("exist");
        cy.get("#portal-team").should("not.exist");
        assertNoAdminShell();
        cy.url().should("not.include", "/v2/access-denied");
    });

    it("is refused the admin ministry page the coordinator uses", () => {
        leaderLogin();
        cy.visit(`/volunteer/ministries/${ministryId}`, {
            failOnStatusCode: false,
        });

        // Whatever happens, it is not the admin shell rendering for a member.
        assertNoAdminShell();
        cy.get("#volunteer-ministry-page").should("not.exist");
    });

    it("gives a member with no team at all neither the entry nor the pages", () => {
        login(PLAIN_USERNAME, PLAIN_PASSWORD);
        cy.url({ timeout: 10000 }).should("include", "/portal");

        cy.get("#portal-nav").should("not.contain", "My Teams");

        cy.visit(TEAMS_URL, { failOnStatusCode: false });
        cy.get("#portal-team").should("not.exist");
        cy.get(".portal-team-card").should("not.exist");
        assertNoAdminShell();
    });
});
