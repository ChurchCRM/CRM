/// <reference types="cypress" />

/**
 * Volunteer v2 — what a TEAM LEADER may do through the API (#9868, epic #9701).
 *
 * Normative sections: volunteer design §4.4 (scope semantics — a `team` grant is
 * authority over that team and nothing else), §4.5 (the three layers), §4.6 (the
 * rules matrix, whose "Create / edit schedule" row reads `scope (own team)` for a
 * team leader), and Member Portal design P17 (D14 revised: scopes count for a
 * self-service account, and a team leader may create schedules for their own
 * team).
 *
 * **The persona is person 99, `selfedit.api.key`** — EditSelf-exclusive, the
 * least-authority account that can still log in, and exactly the person P17 is
 * about. Before #9868 that account could reach nothing under `/api/volunteer/`
 * except `/me/`: `AuthMiddleware` confined it and
 * `VolunteerCoordinatorRoleAuthMiddleware` refused it. Every 200/201 below fails
 * with 403 on the branch point, and every 403 below is the one that must SURVIVE
 * the change.
 *
 * Two teams under one ministry, and person 99 leads only the first. That is the
 * whole shape of the spec: the same call against team A and team B, over and
 * over, and the answer must differ.
 *
 * The fixture is built through the real setup (#9715/#9707) and schedule (#9708)
 * APIs as admin. Cleanup runs in `before` as well as `after`
 * (cypress-testing.md): an `after` hook does not run when the runner crashes
 * mid-spec. Deletion order is FK-safe.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
/** EditSelf-exclusive — the member-login team leader of P17. */
const LEADER_KEY = "selfedit.api.key";
/** EditSelf + Notes, no scope of any kind — the negative persona. */
const NOSCOPE_KEY = "selfedit.plus.notes.api.key";

const VOLUNTEER_URL = "/api/volunteer";
const PORTAL_TEAMS_URL = "/portal/teams";

const PERSON_LEADER = 99;
const PERSON_NOSCOPE = 100;
/** Seeded member of group 1 "Angels class" — somebody to qualify and assign. */
const POOL_MEMBER = 8;

const FIXTURE_PREFIX = "LEAD9868";

let ministryId = 0;
let teamLed = 0;
let teamOther = 0;
let posLedDoor = 0;
let posOtherDoor = 0;
let leaderScopeId = 0;
let scheduleLed = 0;
let occurrenceLed = 0;
let originalVersion = "v1";
let windowStart = "";
let windowEnd = "";

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

function schedulePayload(teamId, name) {
    return {
        name: `${FIXTURE_PREFIX} ${name}`,
        linkMode: "standalone",
        teamId,
        recurType: "weekly",
        recurDow: "Sunday",
        startTime: "09:00",
        endTime: "10:00",
        windowStart,
        windowEnd,
    };
}

function cleanupFixtures() {
    const byMinistry = `JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = %s WHERE vmin.vmin_Name LIKE ?`;
    const like = [`${FIXTURE_PREFIX}%`];

    dbOk(
        `DELETE vasg FROM volunteer_assignment_vasg vasg
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           ${byMinistry.replace("%s", "vsch.vsch_vmin_ID")}`,
        like,
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
           ${byMinistry.replace("%s", "vsch.vsch_vmin_ID")}`,
        like,
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vreq.vreq_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           ${byMinistry.replace("%s", "vsch.vsch_vmin_ID")}`,
        like,
    );
    dbOk(
        `DELETE vocc FROM volunteer_occurrence_vocc vocc
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           ${byMinistry.replace("%s", "vsch.vsch_vmin_ID")}`,
        like,
    );
    dbOk(
        `DELETE vsch FROM volunteer_schedule_vsch vsch ${byMinistry.replace("%s", "vsch.vsch_vmin_ID")}`,
        like,
    );
    dbOk(
        `DELETE vqal FROM volunteer_qualification_vqal vqal
           JOIN volunteer_position_vpos vpos ON vpos.vpos_ID = vqal.vqal_vpos_ID
           ${byMinistry.replace("%s", "vpos.vpos_vmin_ID")}`,
        like,
    );
    dbOk(
        `DELETE vpos FROM volunteer_position_vpos vpos ${byMinistry.replace("%s", "vpos.vpos_vmin_ID")}`,
        like,
    );
    // The scope rows this spec grants, found through the teams they name.
    dbOk(
        `DELETE vscp FROM volunteer_scope_vscp vscp
           JOIN volunteer_team_vtem vtem ON vtem.vtem_ID = vscp.vscp_ScopeId
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vscp.vscp_ScopeType = 'team' AND vmin.vmin_Name LIKE ?`,
        like,
    );
    // D19: the pool Group's `grp_ministry_id` is ON DELETE SET NULL, so the group
    // and its memberships go BEFORE the ministry row or an orphan group is left.
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
        `DELETE vtem FROM volunteer_team_vtem vtem ${byMinistry.replace("%s", "vtem.vtem_vmin_ID")}`,
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
    cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
        originalVersion = resp.body.value ?? resp.body.Value ?? "v1";
    });
    setVersion("v2");

    cleanupFixtures();

    windowStart = isoDate(daysToNext(0));
    windowEnd = isoDate(daysToNext(0) + 21);

    api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries`, {
        name: `${FIXTURE_PREFIX} Hospitality`,
        description: "team-leader fixture",
    }, 201).then((resp) => {
        ministryId = resp.body.ministry.id;
    });

    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, {
            name: `${FIXTURE_PREFIX} Greeters`,
            description: "the team person 99 leads",
        }, 201).then((resp) => {
            teamLed = resp.body.team.id;
        });
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, {
            name: `${FIXTURE_PREFIX} Ushers`,
            description: "the team person 99 does NOT lead",
        }, 201).then((resp) => {
            teamOther = resp.body.team.id;
        });
    });

    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
            name: `${FIXTURE_PREFIX} Door`,
            description: "",
            teamId: teamLed,
            order: 1,
        }, 201).then((resp) => {
            posLedDoor = resp.body.position.id;
        });
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
            name: `${FIXTURE_PREFIX} Aisle`,
            description: "",
            teamId: teamOther,
            order: 1,
        }, 201).then((resp) => {
            posOtherDoor = resp.body.position.id;
        });
    });

    // The grant that makes person 99 a team leader — through the real scope API,
    // as an administrator, which is the only way it is ever made (§4.6).
    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/scopes`, {
            personId: PERSON_LEADER,
            scopeType: "team",
            scopeId: teamLed,
        }, [200, 201]).then((resp) => {
            leaderScopeId = resp.body.scope.id;
        });
    });

    // Somebody to assign later: in the pool and qualified for the led team's door.
    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${POOL_MEMBER}`, null, [200, 201]);
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/positions/${posLedDoor}/qualifications`, {
            personId: POOL_MEMBER,
            notes: "",
        }, 201);
    });
});

after(() => {
    cleanupFixtures();
    setVersion(originalVersion);
});

// ── the spec ───────────────────────────────────────────────────────────────

describe("Volunteer v2 — a team leader on their own team", () => {
    describe("Reading", () => {
        it("Reads the qualification matrix of the team they lead", () => {
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/teams/${teamLed}/qualification-matrix`).then((resp) => {
                expect(resp.body.teamId).to.eq(teamLed);
                expect(resp.body.ministryId).to.eq(ministryId);
                expect(resp.body.positions.map((p) => p.id)).to.include(posLedDoor);
                expect(resp.body.positions.map((p) => p.id)).to.not.include(posOtherDoor);
            });
        });

        it("Is refused the qualification matrix of another team", () => {
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/teams/${teamOther}/qualification-matrix`, null, 403);
        });

        it("Is refused the ministry-wide qualification matrix", () => {
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/ministries/${ministryId}/qualification-matrix`, null, 403);
        });

        it("Reads the schedules of the team they lead", () => {
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/teams/${teamLed}/schedules`).then((resp) => {
                expect(resp.body.schedules).to.be.an("array");
            });
        });

        it("Is refused another team's schedules, and the ministry-wide list", () => {
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/teams/${teamOther}/schedules`, null, 403);
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`, null, 403);
        });

        it("Sees only their own team's positions in the ministry position list", () => {
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`).then((resp) => {
                const ids = resp.body.positions.map((p) => p.id);
                expect(ids).to.include(posLedDoor);
                expect(ids).to.not.include(posOtherDoor);
            });
        });

        it("A login with no scope at all is refused every one of them", () => {
            api(NOSCOPE_KEY, "GET", `${VOLUNTEER_URL}/teams/${teamLed}/qualification-matrix`, null, 403);
            api(NOSCOPE_KEY, "GET", `${VOLUNTEER_URL}/teams/${teamLed}/schedules`, null, 403);
        });
    });

    describe("Creating a schedule (design section 4.6)", () => {
        it("Creates one for the team they lead", () => {
            api(
                LEADER_KEY,
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                schedulePayload(teamLed, "Greeters — Sunday"),
                201,
            ).then((resp) => {
                expect(resp.body.schedule.teamId).to.eq(teamLed);
                scheduleLed = resp.body.schedule.id;
            });
        });

        it("Is refused one for a team they do not lead", () => {
            api(
                LEADER_KEY,
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                schedulePayload(teamOther, "Ushers — Sunday"),
                403,
            );
        });

        it("Writes nothing when it is refused", () => {
            dbOk(
                `SELECT vsch_ID FROM volunteer_schedule_vsch WHERE vsch_vtem_ID = ?`,
                [teamOther],
            ).then((rows) => {
                expect(rows).to.have.length(0);
            });
        });

        it("A login with no scope is refused even for a team of the same ministry", () => {
            api(
                NOSCOPE_KEY,
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                schedulePayload(teamLed, "Nobody — Sunday"),
                403,
            );
        });

        it("Edits and generates dates for their own schedule", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/schedules/${scheduleLed}`, {
                name: `${FIXTURE_PREFIX} Greeters — Sunday morning`,
            }, 200);

            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/schedules/${scheduleLed}/requirements`, {
                positionId: posLedDoor,
                minCount: 1,
                maxCount: 1,
            }, [200, 201]);

            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/schedules/${scheduleLed}/generate`, {
                through: windowEnd,
            }, 200).then((resp) => {
                expect(resp.body.created).to.be.greaterThan(0);
            });
        });
    });

    describe("Staffing their own team's occurrence", () => {
        it("Lists the dates of the team they lead", () => {
            api(
                LEADER_KEY,
                "GET",
                `${VOLUNTEER_URL}/occurrences?from=${windowStart}&to=${windowEnd}&teamId=${teamLed}`,
            ).then((resp) => {
                expect(resp.body.occurrences.length).to.be.greaterThan(0);
                occurrenceLed = resp.body.occurrences[0].id;
            });
        });

        it("Reads its staffing and assigns a qualified volunteer", () => {
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/occurrences/${occurrenceLed}/staffing`).then((resp) => {
                expect(resp.body.requirements.map((r) => r.positionId)).to.include(posLedDoor);
            });

            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/occurrences/${occurrenceLed}/assignments`, {
                positionId: posLedDoor,
                personId: POOL_MEMBER,
            }, 201).then((resp) => {
                expect(resp.body.assignment.personId).to.eq(POOL_MEMBER);
            });
        });

        it("Overrides this date's staffing needs", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/occurrences/${occurrenceLed}/requirements`, {
                positionId: posLedDoor,
                minCount: 2,
                maxCount: 2,
            }, [200, 201]);
        });

        it("A login with no scope is refused the same occurrence", () => {
            api(NOSCOPE_KEY, "GET", `${VOLUNTEER_URL}/occurrences/${occurrenceLed}/staffing`, null, 403);
        });
    });

    describe("Ministry-level acts stay refused", () => {
        it("Cannot read the ministry document", () => {
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 403);
        });

        it("Cannot create a team", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, {
                name: `${FIXTURE_PREFIX} Smuggled`,
                description: "",
            }, 403);
        });

        it("Cannot add somebody to the ministry's pool", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${PERSON_NOSCOPE}`, null, 403);
        });

        it("Cannot grant themselves a second scope", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/scopes`, {
                personId: PERSON_LEADER,
                scopeType: "team",
                scopeId: teamOther,
            }, 403);
        });

        it("Cannot revoke the scope they hold", () => {
            api(LEADER_KEY, "DELETE", `${VOLUNTEER_URL}/scopes/${leaderScopeId}`, null, 403);
        });
    });

    describe("The portal's own pages are not an API", () => {
        /*
         * Design P11 / the portal access rule: every portal page derives the
         * acting person from the session, so an API key — which names an account
         * but arrives without that session — is refused outright rather than
         * being given a second route to the same data.
         */
        it("Refuses an API key on the My Teams pages", () => {
            for (const url of [
                PORTAL_TEAMS_URL,
                `${PORTAL_TEAMS_URL}/${teamLed}`,
                `${PORTAL_TEAMS_URL}/${teamLed}/occurrences/${occurrenceLed}`,
            ]) {
                cy.request({
                    method: "GET",
                    url,
                    headers: { "x-api-key": Cypress.env(LEADER_KEY) },
                    failOnStatusCode: false,
                }).then((resp) => {
                    expect(resp.status).to.eq(403);
                });
            }
        });

        it("Refuses an administrator's API key just the same", () => {
            cy.request({
                method: "GET",
                url: PORTAL_TEAMS_URL,
                headers: { "x-api-key": Cypress.env(ADMIN_KEY) },
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(403);
            });
        });
    });
});
