/// <reference types="cypress" />

/**
 * Volunteer v2 — the coordinator dashboard aggregate (#9711, epic #9701).
 *
 * Normative sections of `.agents/skills/churchcrm/volunteer-v2-design.md`:
 * §3.3.2 (`GET /api/volunteer/dashboard?days=28` →
 * `{upcoming, gaps, pendingResponses, proposedSwaps, failedNotifications}`),
 * §4.4 (read scoping happens in the QUERY — a global manager sees everything, a
 * ministry coordinator their ministries, a team leader their teams), §4.6 (the
 * rules matrix, including the team-leader row) and §5.2 (what the five panels of
 * S1 answer, in order).
 *
 * The fixture is UC1's Coffee Bar plus a second, independently coordinated
 * ministry, built through the REAL APIs — setup (#9715/#9707), schedules
 * (#9708) and assignments (#9709) — so the aggregate is asserted against data
 * the rest of the system actually produced. Nothing is inserted by hand except
 * the two things with no API at all: pool-group membership (every
 * `/api/groups` write needs the global Manage Groups flag, §4.6) and the
 * terminal `failed` notification row, which only the drain can otherwise reach
 * after five attempts (§2.14).
 *
 * Personas (`cypress/data/seed.sql`):
 *
 *   person 1   `admin.api.key`                  Administrator — sees everything
 *   person 3   `user.api.key`                   ministry scope on ministry A
 *   person 95  `nofinance.api.key`              ministry scope on ministry B
 *   person 902 `menuoptions.api.key`             TEAM scope only, on team A2
 *   person 900 `plainauth.api.key`              no volunteer rights → 403
 *
 * The team leader is person 902 and NOT one of the EditSelf personas: #9706's
 * `VolunteerAuthorizationService::loadScopes()` returns no scopes at all for an
 * EditSelf-exclusive user, so such an account can never be a coordinator or a
 * team leader however many scope rows it is granted (D14 — those accounts are
 * the volunteers).
 *
 * Cleanup runs in `before` as well as `after` (cypress-testing.md): an `after`
 * hook does not run when the runner crashes mid-spec. Deletion order is FK-safe.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORD_A_KEY = "user.api.key";
const COORD_B_KEY = "nofinance.api.key";
const TEAM_LEADER_KEY = "menuoptions.api.key";
const PLAINAUTH_KEY = "plainauth.api.key";

const VOLUNTEER_URL = "/api/volunteer";
const DASHBOARD_URL = `${VOLUNTEER_URL}/dashboard`;

const PERSON_COORD_A = 3;
const PERSON_COORD_B = 95;
const PERSON_TEAM_LEADER = 902;
const PERSON_PLAIN = 900;

/** Seeded members of group 1 "Angels class". */
const POOL_GROUP = 1;
const POOL_MEMBER_A = 8;
const POOL_MEMBER_B = 9;
const POOL_MEMBER_C = 63;

const CHURCH_SERVICE_TYPE = 1; // seed.sql — weekly, Sunday, 10:30

const FIXTURE_PREFIX = "DSH9711";
const EVENT_TITLE = `${FIXTURE_PREFIX} Sunday Service`;

let ministryA = 0;
let ministryB = 0;
let teamA1 = 0;
let teamA2 = 0;
let posEspresso = 0; // ministry A, team A1
let posMilk = 0; // ministry A, team A1
let posSound = 0; // ministry A, team A2 — the team leader's only position
let posBooth = 0; // ministry B
let scheduleA1 = 0;
let scheduleA2 = 0;
let scheduleB = 0;
let occA1Near = 0;
let occA1Far = 0;
let occA2 = 0;
let occB = 0;
let originalVersion = "v1";
let seriesStart = "";
let seriesEnd = "";

// ── helpers ────────────────────────────────────────────────────────────────

/** Run SQL and fail the test if the database rejected it. */
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

function api(key, method, url, body, expectedStatus = 200) {
    return cy.makePrivateAPICall(Cypress.env(key), method, url, body, expectedStatus);
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

/** `YYYY-MM-DD`, `offsetDays` from today. */
function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Days from today to the next occurrence of `dow` (0 = Sunday). Never 0. */
function daysToNext(dow) {
    const today = new Date().getDay();
    return ((dow - today + 7) % 7) || 7;
}

function grantScope(personId, scopeType, scopeId) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/scopes`,
        { personId, scopeType, scopeId },
        [200, 201],
    );
}

function dashboard(key, query = "", status = 200) {
    return api(key, "GET", `${DASHBOARD_URL}${query}`, null, status);
}

function createMinistry(name) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/ministries`,
        { name: `${FIXTURE_PREFIX} ${name}`, description: "dashboard fixture" },
        201,
    ).then((resp) => resp.body.ministry.id);
}

function createTeam(ministryId, name) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/ministries/${ministryId}/teams`,
        { name: `${FIXTURE_PREFIX} ${name}`, description: "dashboard fixture" },
        201,
    ).then((resp) => resp.body.team.id);
}

function createPosition(ministryId, teamId, name, order) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
        { name: `${FIXTURE_PREFIX} ${name}`, description: "dashboard fixture", teamId, order },
        201,
    ).then((resp) => resp.body.position.id);
}

function qualify(positionId, personId) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/positions/${positionId}/qualifications`,
        { personId, notes: "" },
        201,
    );
}

function createSchedule(ministryId, name, teamId) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
        {
            name: `${FIXTURE_PREFIX} ${name}`,
            linkMode: "event_type",
            eventTypeId: CHURCH_SERVICE_TYPE,
            titleFilter: EVENT_TITLE,
            windowStart: seriesStart,
            teamId: teamId ?? null,
        },
        201,
    ).then((resp) => resp.body.schedule.id);
}

function upsertRequirement(scheduleId, positionId, minCount, maxCount) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
        { positionId, minCount, maxCount },
        [200, 201],
    ).then((resp) => resp.body.requirement.id);
}

function generate(scheduleId, through) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`,
        { through },
        200,
    );
}

/** Occurrence ids of one schedule, soonest first. */
function occurrencesOf(scheduleId) {
    return dbOk(
        `SELECT vocc_ID AS id, vocc_OccurrenceDate AS d
           FROM volunteer_occurrence_vocc
          WHERE vocc_vsch_ID = ?
          ORDER BY vocc_OccurrenceDate ASC, vocc_ID ASC`,
        [scheduleId],
    );
}

function assign(occurrenceId, positionId, personId) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
        { positionId, personId },
        201,
    ).then((resp) => resp.body.assignment);
}

/** Remove every row this spec could have made, children first. */
function cleanupFixtures() {
    const scopedByAssignment = `
        JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
        JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
        JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
       WHERE vmin.vmin_Name LIKE ?`;

    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vntf.vntf_vasg_ID
           ${scopedByAssignment}`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vntf.vntf_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vswp FROM volunteer_swap_vswp vswp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vswp.vswp_vasg_ID
           ${scopedByAssignment}`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vrsp FROM volunteer_response_vrsp vrsp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vrsp.vrsp_vasg_ID
           ${scopedByAssignment}`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `UPDATE volunteer_assignment_vasg vasg ${scopedByAssignment.replace("WHERE", "SET vasg.vasg_Replaces_vasg_ID = NULL WHERE")}`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(`DELETE vasg FROM volunteer_assignment_vasg vasg ${scopedByAssignment}`, [
        `${FIXTURE_PREFIX}%`,
    ]);
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vreq.vreq_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vocc FROM volunteer_occurrence_vocc vocc
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vsch FROM volunteer_schedule_vsch vsch
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vqal FROM volunteer_qualification_vqal vqal
           JOIN volunteer_position_vpos vpos ON vpos.vpos_ID = vqal.vqal_vpos_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vpos FROM volunteer_position_vpos vpos
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vpol FROM volunteer_pool_vpol vpol
           JOIN volunteer_team_vtem vtem
             ON vtem.vtem_ID = vpol.vpol_OwnerId AND vpol.vpol_OwnerType = 'team'
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID IN (?, ?, ?, ?)`, [
        PERSON_COORD_A,
        PERSON_COORD_B,
        PERSON_TEAM_LEADER,
        PERSON_PLAIN,
    ]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [
        `${FIXTURE_PREFIX}%`,
    ]);
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [`${EVENT_TITLE}%`]);
}

// ── fixture ────────────────────────────────────────────────────────────────

before(() => {
    cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
        originalVersion = resp.body.value ?? resp.body.Value ?? "v1";
    });
    setVersion("v2");
    cleanupFixtures();

    createMinistry("Coffee Bar").then((id) => {
        ministryA = id;
    });
    createMinistry("Sound Booth").then((id) => {
        ministryB = id;
    });

    cy.then(() => {
        createTeam(ministryA, "Bar Team").then((id) => {
            teamA1 = id;
        });
        createTeam(ministryA, "Sound Team").then((id) => {
            teamA2 = id;
        });
    });

    cy.then(() => {
        createPosition(ministryA, teamA1, "Espresso", 1).then((id) => {
            posEspresso = id;
        });
        createPosition(ministryA, teamA1, "Milk Station", 2).then((id) => {
            posMilk = id;
        });
        createPosition(ministryA, teamA2, "Audio Engineer", 3).then((id) => {
            posSound = id;
        });
        createPosition(ministryB, null, "Booth Runner", 1).then((id) => {
            posBooth = id;
        });
    });

    // The pool is an existing Group — V2 never copies membership (D1).
    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamA1}/pools`, {
            groupId: POOL_GROUP,
            label: `${FIXTURE_PREFIX} pool`,
        }, 201);
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamA2}/pools`, {
            groupId: POOL_GROUP,
            label: `${FIXTURE_PREFIX} sound pool`,
        }, 201);
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryB}/pools`, {
            groupId: POOL_GROUP,
            label: `${FIXTURE_PREFIX} booth pool`,
        }, 201);
    });

    cy.then(() => {
        qualify(posEspresso, POOL_MEMBER_A);
        qualify(posEspresso, POOL_MEMBER_B);
        qualify(posMilk, POOL_MEMBER_A);
        qualify(posMilk, POOL_MEMBER_C);
        qualify(posSound, POOL_MEMBER_B);
        qualify(posSound, POOL_MEMBER_C);
        qualify(posBooth, POOL_MEMBER_A);
        qualify(posBooth, POOL_MEMBER_B);
    });

    // A real future event series; V2 must never create events_event rows itself.
    cy.then(() => {
        seriesStart = isoDate(daysToNext(0));
        seriesEnd = isoDate(daysToNext(0) + 35);

        api(ADMIN_KEY, "POST", "/api/events/repeat", {
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
        createSchedule(ministryA, "Coffee Bar — Sunday", teamA1).then((id) => {
            scheduleA1 = id;
        });
        createSchedule(ministryA, "Sound — Sunday", teamA2).then((id) => {
            scheduleA2 = id;
        });
        createSchedule(ministryB, "Booth — Sunday", null).then((id) => {
            scheduleB = id;
        });
    });

    cy.then(() => {
        upsertRequirement(scheduleA1, posEspresso, 1, 1);
        upsertRequirement(scheduleA1, posMilk, 1, 2);
        upsertRequirement(scheduleA2, posSound, 1, 1);
        upsertRequirement(scheduleB, posBooth, 1, 1);
    });

    cy.then(() => {
        generate(scheduleA1, seriesEnd);
        generate(scheduleA2, seriesEnd);
        generate(scheduleB, seriesEnd);
    });

    cy.then(() => {
        occurrencesOf(scheduleA1).then((rows) => {
            expect(rows.length, "ministry A occurrences generated").to.be.greaterThan(1);
            occA1Near = Number(rows[0].id);
            occA1Far = Number(rows[rows.length - 1].id);
        });
        occurrencesOf(scheduleA2).then((rows) => {
            occA2 = Number(rows[0].id);
        });
        occurrencesOf(scheduleB).then((rows) => {
            occB = Number(rows[0].id);
        });
    });

    // Scope grants. Person 3 coordinates ministry A, person 95 ministry B and
    // person 100 leads team A2 and nothing else — the §4.6 team-leader row.
    cy.then(() => {
        grantScope(PERSON_COORD_A, "ministry", ministryA);
        grantScope(PERSON_COORD_B, "ministry", ministryB);
        grantScope(PERSON_TEAM_LEADER, "team", teamA2);
    });

    // One pending assignment on ministry A's nearest occurrence — Espresso is then
    // filled and Milk Station is not, so exactly one gap remains on that occurrence.
    cy.then(() => {
        assign(occA1Near, posEspresso, POOL_MEMBER_A);
    });
});

after(() => {
    cleanupFixtures();
    setVersion(originalVersion);
});

// ── tests ──────────────────────────────────────────────────────────────────

describe("GET /api/volunteer/dashboard — shape and defaults (#9711, §3.3.2)", () => {
    it("returns the five documented keys", () => {
        dashboard(ADMIN_KEY).then((resp) => {
            expect(resp.body).to.have.property("upcoming");
            expect(resp.body).to.have.property("gaps");
            expect(resp.body).to.have.property("pendingResponses");
            expect(resp.body).to.have.property("proposedSwaps");
            expect(resp.body).to.have.property("failedNotifications");
            expect(resp.body.upcoming).to.be.an("array");
            expect(resp.body.gaps).to.be.an("array");
            expect(resp.body.pendingResponses).to.be.an("array");
            expect(resp.body.proposedSwaps).to.be.an("array");
            expect(resp.body.failedNotifications).to.be.a("number");
        });
    });

    it("defaults to a 28 day window and reports the window it used", () => {
        dashboard(ADMIN_KEY).then((resp) => {
            expect(resp.body.days).to.eq(28);
            expect(resp.body.from).to.eq(isoDate(0));
            expect(resp.body.to).to.eq(isoDate(28));
        });
    });

    it("carries the caller's scope so the page can offer an entry point", () => {
        dashboard(ADMIN_KEY).then((resp) => {
            expect(resp.body.scope).to.be.an("object");
            expect(resp.body.scope.isManager).to.eq(true);
            expect(resp.body.scope.ministries).to.be.an("array");
            expect(resp.body.scope.teams).to.be.an("array");
        });
    });

    it("rejects a days value outside the supported range", () => {
        dashboard(ADMIN_KEY, "?days=0", 400);
        dashboard(ADMIN_KEY, "?days=999", 400);
        dashboard(ADMIN_KEY, "?days=notanumber", 400);
    });
});

describe("GET /api/volunteer/dashboard — the gap panel (#9711, §5.2)", () => {
    it("lists the unfilled Milk Station requirement, soonest first", () => {
        dashboard(ADMIN_KEY).then((resp) => {
            const mine = resp.body.gaps.filter((gap) => gap.ministryId === ministryA);
            expect(mine.length).to.be.greaterThan(0);

            const names = mine.map((gap) => gap.positionName);
            expect(names).to.include(`${FIXTURE_PREFIX} Milk Station`);

            const dates = mine.map((gap) => gap.occurrenceDate);
            const sorted = [...dates].sort();
            expect(dates).to.deep.eq(sorted);
        });
    });

    it("does not list a requirement that is filled", () => {
        dashboard(ADMIN_KEY).then((resp) => {
            const nearEspresso = resp.body.gaps.filter(
                (gap) => gap.occurrenceId === occA1Near && gap.positionId === posEspresso,
            );
            expect(nearEspresso).to.have.length(0);
        });
    });

    it("carries the context a row needs to be clickable", () => {
        dashboard(ADMIN_KEY).then((resp) => {
            const gap = resp.body.gaps.find((row) => row.occurrenceId === occA1Near);
            expect(gap).to.be.an("object");
            expect(gap.occurrenceId).to.eq(occA1Near);
            expect(gap.positionId).to.be.a("number");
            expect(gap.gapCount).to.be.greaterThan(0);
            expect(gap.scheduleName).to.be.a("string");
            expect(gap.ministryName).to.be.a("string");
        });
    });
});

describe("GET /api/volunteer/dashboard — pending responses and swaps (#9711, §5.2)", () => {
    it("lists the pending assignment with its occurrence context", () => {
        dashboard(ADMIN_KEY).then((resp) => {
            const row = resp.body.pendingResponses.find(
                (pending) => pending.occurrenceId === occA1Near,
            );
            expect(row, "the pending Espresso assignment").to.be.an("object");
            expect(row.status).to.eq("pending");
            expect(row.personId).to.eq(POOL_MEMBER_A);
            expect(row.positionName).to.eq(`${FIXTURE_PREFIX} Espresso`);
            expect(row.occurrenceDate).to.be.a("string");
            expect(row).to.have.property("withinReminderWindow");
        });
    });

    it("drops an assignment once it has been answered", () => {
        let assignmentId = 0;
        assign(occA1Far, posEspresso, POOL_MEMBER_B).then((assignment) => {
            assignmentId = assignment.id;
        });
        cy.then(() => {
            // occA1Far sits beyond the default 28-day window, so the wider one is
            // what proves the row is listed at all.
            dashboard(ADMIN_KEY, "?days=60").then((resp) => {
                const ids = resp.body.pendingResponses.map((row) => row.id);
                expect(ids).to.include(assignmentId);
            });
        });
        cy.then(() => {
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/assignments/${assignmentId}/status`, {
                status: "accepted",
                comment: "",
            }, 200);
        });
        cy.then(() => {
            dashboard(ADMIN_KEY, "?days=60").then((resp) => {
                const ids = resp.body.pendingResponses.map((row) => row.id);
                expect(ids).to.not.include(assignmentId);
            });
        });
    });

    it("lists a proposed swap and drops it once decided", () => {
        let assignmentId = 0;
        let swapId = 0;

        assign(occA2, posSound, POOL_MEMBER_B).then((assignment) => {
            assignmentId = assignment.id;
        });
        cy.then(() => {
            // The proposal is made on the volunteer's behalf through the coordinator
            // surface's own data: /me/* never accepts a personId, so the swap row is
            // created by the assigned volunteer themselves is not reachable here —
            // the admin-facing path is a direct insert of a proposal row.
            dbOk(
                `INSERT INTO volunteer_swap_vswp
                    (vswp_vasg_ID, vswp_ProposedBy_per_ID, vswp_Proposed_per_ID, vswp_Status, vswp_ProposedDate)
                 VALUES (?, ?, ?, 'proposed', NOW())`,
                [assignmentId, POOL_MEMBER_B, POOL_MEMBER_C],
            );
        });
        cy.then(() => {
            dbOk(`SELECT vswp_ID AS id FROM volunteer_swap_vswp WHERE vswp_vasg_ID = ?`, [
                assignmentId,
            ]).then((rows) => {
                swapId = Number(rows[0].id);
            });
        });
        cy.then(() => {
            dashboard(ADMIN_KEY).then((resp) => {
                const ids = resp.body.proposedSwaps.map((swap) => swap.id);
                expect(ids).to.include(swapId);
                const swap = resp.body.proposedSwaps.find((row) => row.id === swapId);
                expect(swap.status).to.eq("proposed");
                expect(swap.occurrenceId).to.eq(occA2);
            });
        });
        cy.then(() => {
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/reject`, { comment: "" }, 200);
        });
        cy.then(() => {
            dashboard(ADMIN_KEY).then((resp) => {
                const ids = resp.body.proposedSwaps.map((swap) => swap.id);
                expect(ids).to.not.include(swapId);
            });
        });
    });
});

describe("GET /api/volunteer/dashboard — failedNotifications (#9711, §2.14)", () => {
    it("counts only terminal failed rows, not pending or skipped ones", () => {
        let assignmentId = 0;

        dashboard(ADMIN_KEY, "?days=60").then((resp) => {
            // The fixture's own assignment mail is still `pending`, so the baseline
            // proves pending rows are NOT counted.
            expect(resp.body.failedNotifications).to.eq(0);
        });

        assign(occA1Far, posMilk, POOL_MEMBER_C).then((assignment) => {
            assignmentId = assignment.id;
        });

        cy.then(() => {
            dbOk(
                `UPDATE volunteer_notification_vntf
                    SET vntf_Status = 'failed', vntf_Attempts = 5, vntf_LastError = 'test'
                  WHERE vntf_vasg_ID = ? AND vntf_Type = 'assignment'`,
                [assignmentId],
            );
        });

        cy.then(() => {
            dashboard(ADMIN_KEY, "?days=60").then((resp) => {
                expect(resp.body.failedNotifications).to.eq(1);
            });
        });

        // `skipped` is not a failure either (§2.14).
        cy.then(() => {
            dbOk(
                `UPDATE volunteer_notification_vntf
                    SET vntf_Status = 'skipped'
                  WHERE vntf_vasg_ID = ? AND vntf_Type = 'assignment'`,
                [assignmentId],
            );
        });
        cy.then(() => {
            dashboard(ADMIN_KEY, "?days=60").then((resp) => {
                expect(resp.body.failedNotifications).to.eq(0);
            });
        });
    });
});

describe("GET /api/volunteer/dashboard — the days window (#9711, §3.3.2)", () => {
    it("excludes an occurrence beyond the requested window", () => {
        dashboard(ADMIN_KEY, "?days=1").then((resp) => {
            const ids = resp.body.upcoming.map((occurrence) => occurrence.id);
            expect(ids).to.not.include(occA1Far);
        });
    });

    it("includes it again when the window is wide enough", () => {
        dashboard(ADMIN_KEY, "?days=60").then((resp) => {
            const ids = resp.body.upcoming.map((occurrence) => occurrence.id);
            expect(ids).to.include(occA1Far);
        });
    });

    it("never reports an occurrence that has already happened", () => {
        dashboard(ADMIN_KEY, "?days=60").then((resp) => {
            const today = isoDate(0);
            for (const occurrence of resp.body.upcoming) {
                expect(occurrence.occurrenceDate >= today).to.eq(true);
            }
        });
    });
});

describe("GET /api/volunteer/dashboard — scoping happens in the query (#9711, §4.4)", () => {
    it("an administrator sees both ministries", () => {
        dashboard(ADMIN_KEY, "?days=60").then((resp) => {
            const ministryIds = new Set(
                resp.body.upcoming.map((occurrence) => occurrence.ministryId),
            );
            expect(ministryIds.has(ministryA)).to.eq(true);
            expect(ministryIds.has(ministryB)).to.eq(true);
        });
    });

    it("ministry A's coordinator sees only ministry A", () => {
        dashboard(COORD_A_KEY, "?days=60").then((resp) => {
            for (const occurrence of resp.body.upcoming) {
                expect(occurrence.ministryId).to.eq(ministryA);
            }
            for (const gap of resp.body.gaps) {
                expect(gap.ministryId).to.eq(ministryA);
            }
            for (const pending of resp.body.pendingResponses) {
                expect(pending.ministryId).to.eq(ministryA);
            }
            expect(resp.body.scope.isManager).to.eq(false);
            expect(resp.body.scope.ministries.map((m) => m.id)).to.deep.eq([ministryA]);
        });
    });

    it("ministry B's coordinator sees only ministry B", () => {
        dashboard(COORD_B_KEY, "?days=60").then((resp) => {
            for (const occurrence of resp.body.upcoming) {
                expect(occurrence.ministryId).to.eq(ministryB);
            }
            expect(resp.body.scope.ministries.map((m) => m.id)).to.deep.eq([ministryB]);
        });
    });

    it("a team-scope-only user sees only their own team's occurrences", () => {
        dashboard(TEAM_LEADER_KEY, "?days=60").then((resp) => {
            expect(resp.body.upcoming.length).to.be.greaterThan(0);
            for (const occurrence of resp.body.upcoming) {
                expect(occurrence.teamId).to.eq(teamA2);
            }
            for (const gap of resp.body.gaps) {
                expect(gap.teamId).to.eq(teamA2);
            }
        });
    });

    it("a team leader gets a navigable entry point: their ministry, read-only, and their team", () => {
        dashboard(TEAM_LEADER_KEY, "?days=60").then((resp) => {
            expect(resp.body.scope.isManager).to.eq(false);

            const ministries = resp.body.scope.ministries;
            expect(ministries.map((m) => m.id)).to.include(ministryA);
            const parent = ministries.find((m) => m.id === ministryA);
            expect(parent.manageable, "read-only for a team leader").to.eq(false);

            const teams = resp.body.scope.teams;
            expect(teams.map((t) => t.id)).to.deep.eq([teamA2]);
            expect(teams[0].ministryId).to.eq(ministryA);
        });
    });

    it("a team leader never sees the sibling team's swaps or pending responses", () => {
        dashboard(TEAM_LEADER_KEY, "?days=60").then((resp) => {
            for (const pending of resp.body.pendingResponses) {
                expect(pending.teamId).to.eq(teamA2);
            }
            for (const swap of resp.body.proposedSwaps) {
                expect(swap.teamId).to.eq(teamA2);
            }
        });
    });

    it("refuses a caller with no volunteer rights at all", () => {
        dashboard(PLAINAUTH_KEY, "", 403);
    });
});

describe("GET /api/volunteer/dashboard — the rollout gate (#9711, §3.3)", () => {
    it("is refused when V2 is off, and reachable again when it is on", () => {
        setVersion("v1");
        dashboard(ADMIN_KEY, "", 403);
        setVersion("v2");
        dashboard(ADMIN_KEY, "", 200);
    });
});
