/// <reference types="cypress" />

/**
 * Volunteer v2 — ministry / team / position setup API (#9715, epic #9701).
 *
 * The sibling file `private.volunteer.setup.spec.js` (#9705) asserts the same
 * entities at the DATABASE level — unique keys, foreign keys, ON DELETE rules.
 * This file asserts the HTTP surface design §3.3.1 specifies for them, and the
 * §4.6 rules matrix rows that govern who may call what:
 *
 *   ministry create / delete   Global Volunteer Manager (or administrator) ONLY
 *   ministry edit              ministry coordinator, in their own scope
 *   team create / edit         ministry coordinator, in their own scope
 *   position create / edit     ministry coordinator; team leader for their own team
 *
 * Plus the §2.3 / §2.6 data rules: a unique ministry name, a team name unique
 * inside its ministry, a case-insensitive position-name duplicate check inside
 * one (ministry, team) scope — the explicit check that compensates for MySQL
 * treating NULLs as distinct in `vpos_ministry_team_name_uidx` — and the delete
 * rules: an active ministry is refused with 409 and a deactivated one takes
 * everything with it (2026-09-17); a team or a position deletes straight away and
 * takes everything under it, service history included (2026-09-26).
 *
 * Tiers exercised (design §6.4 fixture table):
 *   person 1   `admin.api.key`      administrator — bypasses every ROLE gate
 *   person 3   `user.api.key`       every flag but Admin → ministry coordinator once scoped
 *   person 900 `plainauth.api.key`  Notes only → the "no volunteer rights" negative case
 *
 * §6.6 rule honoured throughout: never assert a strict 403 on an admin-keyed
 * call to a role-gated route — an administrator bypasses every role middleware
 * except AdminRoleAuthMiddleware. The 403s asserted with the admin key below
 * come from VolunteerV2EnabledMiddleware, which nobody bypasses.
 *
 * Two error shapes are still in play (design §4.9): BaseAuthRoleMiddleware
 * denials are `{"error","code"}`; everything from SlimUtils::renderErrorJSON()
 * is `{"success":false,"message":…}`. Assertions name the layer that denied and
 * never assert on wording.
 *
 * Rows that have no endpoint yet (schedules, occurrences, qualifications) go in
 * through `cy.dbQuery()` — they exist as tables from #9705 and are what the delete
 * rules are about. Cleanup runs in `before` AND `after` (cypress-testing.md:
 * an `after` hook does not run when the runner crashes mid-spec).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const MINISTRIES_URL = "/api/ministries/ministries";
const SCOPES_URL = "/api/ministries/scopes";

const PERSON_COORDINATOR = 3; // tony.wade — user.api.key
const PERSON_PLAIN = 900; // john.plainauth — plainauth.api.key

/** Every fixture name starts with this so cleanup deletes exactly what this spec made. */
const PREFIX = "SETUP9715";

let ministryA = 0;
let ministryB = 0;

function userKey() {
    return Cypress.env("user.api.key");
}

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

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

/**
 * Delete every row this spec could have created, children first so no foreign
 * key blocks. Scope rows carry no FK to their target (the column is
 * polymorphic, §2.15), so they are removed by person id.
 */
function cleanupFixtures() {
    dbOk(
        `DELETE q FROM volunteer_qualification_vqal q
           JOIN volunteer_position_vpos p ON p.vpos_ID = q.vqal_vpos_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = p.vpos_vmin_ID
          WHERE m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE o FROM volunteer_occurrence_vocc o
           JOIN volunteer_schedule_vsch s ON s.vsch_ID = o.vocc_vsch_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = s.vsch_vmin_ID
          WHERE m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE s FROM volunteer_schedule_vsch s
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = s.vsch_vmin_ID
          WHERE m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID IN (?, ?)`, [
        PERSON_COORDINATOR,
        PERSON_PLAIN,
    ]);
    // Positions, teams and pools cascade from the ministry, so one delete is enough.
    // #9869: a ministry created through the API now comes with its own calendar,
    // named after the ministry. `calendars.ministry_id` is ON DELETE SET NULL, so
    // deleting the ministry row directly would leave the calendar behind as an
    // unowned church calendar. It goes first, matched on the same prefix.
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, [
        `${PREFIX}%`,
    ]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [
        `${PREFIX}%`,
    ]);
}

/** Create a ministry with the admin key and hand back its id. */
function createMinistry(name, description = "created by the #9715 API spec") {
    return cy
        .makePrivateAdminAPICall(
            "POST",
            MINISTRIES_URL,
            { name: `${PREFIX} ${name}`, description },
            201,
        )
        .then((resp) => resp.body.ministry.id);
}

/** The team a ministry was born with — every ministry is created with one. */
function defaultTeam(ministryId) {
    return cy
        .makePrivateAdminAPICall("GET", `${MINISTRIES_URL}/${ministryId}`, null, 200)
        .then((resp) => resp.body.teams[0].id);
}

/** Grant person 3 a ministry-level scope over $ministryId (manager-only route). */
function grantMinistryScope(ministryId) {
    return cy.makePrivateAdminAPICall(
        "POST",
        SCOPES_URL,
        {
            personId: PERSON_COORDINATOR,
            scopeType: "ministry",
            scopeId: ministryId,
        },
        [200, 201],
    );
}

describe("Volunteer v2 ministry/team/position setup API (#9715)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    // -----------------------------------------------------------------
    // §4.8 — the gates in front of the whole surface
    // -----------------------------------------------------------------
    describe("Gates", () => {
        it("returns 401 to an unauthenticated caller", () => {
            cy.request({
                method: "GET",
                url: MINISTRIES_URL,
                failOnStatusCode: false,
                withCredentials: false,
            }).then((resp) => {
                expect(resp.status).to.eq(401);
                expect(resp.body).to.have.property("code", 401);
            });
        });

        it("returns 403 for every setup route while the rollout state is v1", () => {
            setVersion("v1");
            // Admin deliberately: this 403 is VolunteerV2EnabledMiddleware, which an
            // administrator does NOT bypass, so it is not a role-gate 403 on admin.
            cy.makePrivateAdminAPICall("GET", MINISTRIES_URL, null, 403).then(
                (resp) => {
                    expect(resp.body).to.have.property("success", false);
                },
            );
            cy.makePrivateAdminAPICall(
                "POST",
                MINISTRIES_URL,
                { name: `${PREFIX} Blocked` },
                403,
            );
            setVersion("v2");
        });

        it("denies a user with no manager flag and no scope", () => {
            cy.makePrivatePlainAuthAPICall("GET", MINISTRIES_URL, null, 403).then(
                (resp) => {
                    // BaseAuthRoleMiddleware still emits its own shape (E-18/#9737 open).
                    expect(resp.body).to.have.property("code", 403);
                },
            );
        });
    });

    // -----------------------------------------------------------------
    // §2.3 / §3.3.1 — ministries
    // -----------------------------------------------------------------
    describe("Ministries", () => {
        it("creates a ministry", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                MINISTRIES_URL,
                { name: `${PREFIX} Coffee Bar`, description: "UC1" },
                201,
            ).then((resp) => {
                expect(resp.body).to.have.property("ministry");
                const ministry = resp.body.ministry;
                expect(ministry.name).to.eq(`${PREFIX} Coffee Bar`);
                expect(ministry.description).to.eq("UC1");
                expect(ministry.active).to.eq(true);
                expect(ministry.id).to.be.a("number").and.to.be.greaterThan(0);
                // A ministry is created with its first team already in it (D18), so
                // the create response says one team and no positions.
                expect(ministry.teamCount).to.eq(1);
                expect(ministry.positionCount).to.eq(0);
                ministryA = ministry.id;
            });
        });

        it("rejects a duplicate ministry name with 409", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                MINISTRIES_URL,
                { name: `${PREFIX} Coffee Bar` },
                409,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", false);
            });
        });

        it("rejects a duplicate name that differs only in case", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                MINISTRIES_URL,
                { name: `${PREFIX} COFFEE BAR` },
                409,
            );
        });

        it("rejects a missing name with 400", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                MINISTRIES_URL,
                { description: "no name" },
                400,
            );
        });

        it("lists the ministry with its counts", () => {
            cy.makePrivateAdminAPICall("GET", MINISTRIES_URL, null, 200).then(
                (resp) => {
                    expect(resp.body).to.have.property("ministries");
                    const found = resp.body.ministries.find(
                        (m) => m.id === ministryA,
                    );
                    expect(found, "the created ministry is listed").to.exist;
                    expect(found.name).to.eq(`${PREFIX} Coffee Bar`);
                    expect(found).to.have.property("teamCount");
                    expect(found).to.have.property("positionCount");
                },
            );
        });

        it("reads one ministry with its teams and positions", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.ministry.id).to.eq(ministryA);
                expect(resp.body.teams).to.be.an("array");
                expect(resp.body.positions).to.be.an("array");
            });
        });

        it("updates name, description and active", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}`,
                {
                    name: `${PREFIX} Coffee Bar`,
                    description: "UC1 — the worked example",
                    active: false,
                },
                200,
            ).then((resp) => {
                expect(resp.body.ministry.description).to.eq(
                    "UC1 — the worked example",
                );
                expect(resp.body.ministry.active).to.eq(false);
            });
            // Put it back active for the rest of the spec.
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}`,
                { active: true },
                200,
            ).then((resp) => {
                expect(resp.body.ministry.active).to.eq(true);
            });
        });

        it("filters the list by active", () => {
            createMinistry("Retired").then((id) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `${MINISTRIES_URL}/${id}`,
                    { active: false },
                    200,
                );
                cy.makePrivateAdminAPICall(
                    "GET",
                    `${MINISTRIES_URL}?active=1`,
                    null,
                    200,
                ).then((resp) => {
                    const ids = resp.body.ministries.map((m) => m.id);
                    expect(ids).to.not.include(id);
                    expect(ids).to.include(ministryA);
                });
                // Active ministries are refused (409): deactivate first (2026-09-17 lifecycle rule).
                cy.makePrivateAdminAPICall("POST", `${MINISTRIES_URL}/${id}`, { active: false }, 200);
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${MINISTRIES_URL}/${id}`,
                    null,
                    200,
                );
            });
        });

        it("returns 404 for a ministry that does not exist", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/99999999`,
                null,
                404,
            );
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/99999999`,
                { name: "nope" },
                404,
            );
            cy.makePrivateAdminAPICall(
                "DELETE",
                `${MINISTRIES_URL}/99999999`,
                null,
                404,
            );
        });

        it("deletes an empty ministry", () => {
            createMinistry("Disposable").then((id) => {
                // Active ministries are refused (409): deactivate first (2026-09-17 lifecycle rule).
                cy.makePrivateAdminAPICall("POST", `${MINISTRIES_URL}/${id}`, { active: false }, 200);
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${MINISTRIES_URL}/${id}`,
                    null,
                    200,
                );
                cy.makePrivateAdminAPICall(
                    "GET",
                    `${MINISTRIES_URL}/${id}`,
                    null,
                    404,
                );
            });
        });

        it("removes the ministry's scope grants with it (§3.4 deleteMinistry)", () => {
            createMinistry("Scoped Away").then((id) => {
                grantMinistryScope(id);
                // Active ministries are refused (409): deactivate first (2026-09-17 lifecycle rule).
                cy.makePrivateAdminAPICall("POST", `${MINISTRIES_URL}/${id}`, { active: false }, 200);
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${MINISTRIES_URL}/${id}`,
                    null,
                    200,
                );
                dbOk(
                    `SELECT COUNT(*) AS c FROM volunteer_scope_vscp
                      WHERE vscp_ScopeType = 'ministry' AND vscp_ScopeId = ?`,
                    [id],
                ).then((rows) => {
                    expect(Number(rows[0].c)).to.eq(0);
                });
            });
        });

        it("refuses (409) to delete a ministry while it is still active", () => {
            createMinistry("Still Running").then((id) => {
                cy.makePrivateAdminAPICall("DELETE", `${MINISTRIES_URL}/${id}`, null, 409).then((resp) => {
                    expect(resp.body).to.have.property("success", false);
                    expect(resp.body.message).to.include("Deactivate");
                });
                cy.makePrivateAdminAPICall("GET", `${MINISTRIES_URL}/${id}`, null, 200);

                // Deactivated, the same delete goes through.
                cy.makePrivateAdminAPICall("POST", `${MINISTRIES_URL}/${id}`, { active: false }, 200);
                cy.makePrivateAdminAPICall("DELETE", `${MINISTRIES_URL}/${id}`, null, 200);
                cy.makePrivateAdminAPICall("GET", `${MINISTRIES_URL}/${id}`, null, 404);
            });
        });

        it("deletes a deactivated ministry together with its occurrences and assignments", () => {
            createMinistry("Retired").then((id) => {
                // vsch_vtem_ID is NOT NULL, so even a raw fixture schedule names the
                // team the ministry was created with.
                defaultTeam(id).then((teamId) =>
                dbOk(
                    `INSERT INTO volunteer_schedule_vsch
                       (vsch_vmin_ID, vsch_vtem_ID, vsch_Name, vsch_LinkMode, vsch_WindowStart, vsch_GenerateAheadDays, vsch_Active)
                     VALUES (?, ?, ?, 'standalone', '2026-09-13', 56, 1)`,
                    [id, teamId, `${PREFIX} Weekly`],
                ).then((scheduleRows) => {
                    const scheduleId = scheduleRows.insertId;
                    dbOk(
                        `INSERT INTO volunteer_occurrence_vocc
                           (vocc_vsch_ID, vocc_OccurrenceDate, vocc_Status, vocc_GeneratedDate)
                         VALUES (?, '2026-09-13', 'scheduled', NOW())`,
                        [scheduleId],
                    ).then((occurrenceRows) => {
                        const occurrenceId = occurrenceRows.insertId;
                        dbOk(
                            `INSERT INTO volunteer_position_vpos (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Active)
                             VALUES (?, ?, ?, 1)`,
                            [id, teamId, `${PREFIX} Usher`],
                        ).then((positionRows) => {
                            // A past-dated, accepted assignment: service history, which the
                            // delete removes on purpose (the confirmation names the counts).
                            dbOk(
                                `INSERT INTO volunteer_assignment_vasg
                                   (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                                 VALUES (?, ?, 4, 'accepted', 'coordinator', NOW())`,
                                [occurrenceId, positionRows.insertId],
                            );
                        });

                        cy.makePrivateAdminAPICall("GET", `${MINISTRIES_URL}/${id}`, null, 200).then((resp) => {
                            expect(resp.body.summary).to.have.property("occurrenceCount", 1);
                            expect(resp.body.summary).to.have.property("assignmentCount", 1);
                        });

                        cy.makePrivateAdminAPICall("POST", `${MINISTRIES_URL}/${id}`, { active: false }, 200);
                        cy.makePrivateAdminAPICall("DELETE", `${MINISTRIES_URL}/${id}`, null, 200);
                        cy.makePrivateAdminAPICall("GET", `${MINISTRIES_URL}/${id}`, null, 404);

                        dbOk(`SELECT COUNT(*) AS c FROM volunteer_assignment_vasg WHERE vasg_vocc_ID = ?`, [occurrenceId]).then((rows) => {
                            expect(Number(rows[0].c), "assignments").to.eq(0);
                        });
                        dbOk(`SELECT COUNT(*) AS c FROM volunteer_occurrence_vocc WHERE vocc_vsch_ID = ?`, [scheduleId]).then((rows) => {
                            expect(Number(rows[0].c), "occurrences").to.eq(0);
                        });
                        dbOk(`SELECT COUNT(*) AS c FROM volunteer_schedule_vsch WHERE vsch_ID = ?`, [scheduleId]).then((rows) => {
                            expect(Number(rows[0].c), "schedules").to.eq(0);
                        });
                    });
                }),
                );
            });
        });
    });

    // -----------------------------------------------------------------
    // §4.6 — ministry create/delete is manager-only, edit is scoped
    // -----------------------------------------------------------------
    describe("Scoped authorization", () => {
        before(() => {
            createMinistry("Worship").then((id) => {
                ministryB = id;
            });
            // ministryA exists from the block above; scope person 3 to it only.
            cy.then(() => grantMinistryScope(ministryA));
        });

        it("lets the scoped coordinator list only their own ministries", () => {
            cy.makePrivateAPICall(userKey(), "GET", MINISTRIES_URL, null, 200).then(
                (resp) => {
                    const ids = resp.body.ministries.map((m) => m.id);
                    expect(ids).to.include(ministryA);
                    expect(ids).to.not.include(ministryB);
                },
            );
        });

        it("denies ministry creation to a coordinator (manager-only)", () => {
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                MINISTRIES_URL,
                { name: `${PREFIX} Coordinator Made This` },
                403,
            );
        });

        it("denies ministry deletion to a coordinator (manager-only)", () => {
            cy.makePrivateAPICall(
                userKey(),
                "DELETE",
                `${MINISTRIES_URL}/${ministryA}`,
                null,
                403,
            );
        });

        it("lets the coordinator update their own ministry", () => {
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${MINISTRIES_URL}/${ministryA}`,
                { description: "edited by the coordinator" },
                200,
            ).then((resp) => {
                expect(resp.body.ministry.description).to.eq(
                    "edited by the coordinator",
                );
            });
        });

        it("denies the coordinator every verb on a ministry outside their scope", () => {
            cy.makePrivateAPICall(
                userKey(),
                "GET",
                `${MINISTRIES_URL}/${ministryB}`,
                null,
                403,
            );
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${MINISTRIES_URL}/${ministryB}`,
                { description: "not mine" },
                403,
            );
            cy.makePrivateAPICall(
                userKey(),
                "GET",
                `${MINISTRIES_URL}/${ministryB}/teams`,
                null,
                403,
            );
        });
    });

    // -----------------------------------------------------------------
    // §2.4 / §3.3.1 — teams
    // -----------------------------------------------------------------
    describe("Teams", () => {
        let teamA = 0;

        it("creates a team inside a ministry", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/teams`,
                // NOT "<ministry> Team": that is the name the ministry's own first
                // team already has, and a second one of the same name is a 409.
                { name: `${PREFIX} Bar Crew`, description: "UC1" },
                201,
            ).then((resp) => {
                expect(resp.body.team.ministryId).to.eq(ministryA);
                expect(resp.body.team.name).to.eq(`${PREFIX} Bar Crew`);
                expect(resp.body.team.active).to.eq(true);
                teamA = resp.body.team.id;
            });
        });

        it("rejects a duplicate team name inside the same ministry", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/teams`,
                { name: `${PREFIX} Bar Crew` },
                409,
            );
        });

        it("accepts the same team name under a different ministry", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryB}/teams`,
                { name: `${PREFIX} Bar Crew` },
                201,
            ).then((resp) => {
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/ministries/teams/${resp.body.team.id}`,
                    null,
                    200,
                );
            });
        });

        it("lists, reads and updates a team", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/teams`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.teams.map((t) => t.id)).to.include(teamA);
            });
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/ministries/teams/${teamA}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.team.id).to.eq(teamA);
                expect(resp.body.positions).to.be.an("array");
            });
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/ministries/teams/${teamA}`,
                { description: "renamed description", active: false },
                200,
            ).then((resp) => {
                expect(resp.body.team.description).to.eq("renamed description");
                expect(resp.body.team.active).to.eq(false);
            });
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/ministries/teams/${teamA}`,
                { active: true },
                200,
            );
        });

        it("lets the scoped coordinator create a team in their ministry and refuses elsewhere", () => {
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${MINISTRIES_URL}/${ministryA}/teams`,
                { name: `${PREFIX} Coordinator Team` },
                201,
            ).then((resp) => {
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/ministries/teams/${resp.body.team.id}`,
                    null,
                    200,
                );
            });
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${MINISTRIES_URL}/${ministryB}/teams`,
                { name: `${PREFIX} Not Mine` },
                403,
            );
        });

        it("returns 404 for a team that does not exist", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/ministries/teams/99999999",
                null,
                404,
            );
        });

        it("deletes a team together with everything under it (2026-09-26)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/positions`,
                { name: `${PREFIX} Team Scoped`, teamId: teamA },
                201,
            ).then((created) => {
                const positionId = created.body.position.id;

                // A revoked qualification is listed on no screen, and it used to be
                // what kept both the position and its team from ever being deleted.
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/ministries/positions/${positionId}/qualifications`,
                    { personId: 4 },
                    [200, 201],
                ).then((resp) => {
                    cy.makePrivateAdminAPICall(
                        "DELETE",
                        `/api/ministries/qualifications/${resp.body.qualification.id}`,
                        null,
                        200,
                    );
                });
                cy.makePrivateAdminAPICall(
                    "POST",
                    SCOPES_URL,
                    { personId: PERSON_COORDINATOR, scopeType: "team", scopeId: teamA },
                    [200, 201],
                );

                dbOk(
                    `INSERT INTO volunteer_schedule_vsch
                       (vsch_vmin_ID, vsch_vtem_ID, vsch_Name, vsch_LinkMode, vsch_WindowStart, vsch_GenerateAheadDays, vsch_Active)
                     VALUES (?, ?, ?, 'standalone', '2026-09-13', 56, 1)`,
                    [ministryA, teamA, `${PREFIX} Team Weekly`],
                ).then((scheduleRows) => {
                    const scheduleId = scheduleRows.insertId;
                    dbOk(
                        `INSERT INTO volunteer_requirement_vreq (vreq_vsch_ID, vreq_vpos_ID, vreq_MinCount)
                         VALUES (?, ?, 1)`,
                        [scheduleId, positionId],
                    );
                    dbOk(
                        `INSERT INTO volunteer_occurrence_vocc
                           (vocc_vsch_ID, vocc_OccurrenceDate, vocc_Status, vocc_GeneratedDate)
                         VALUES (?, '2026-09-13', 'scheduled', NOW())`,
                        [scheduleId],
                    ).then((occurrenceRows) => {
                        const occurrenceId = occurrenceRows.insertId;
                        // Past service history: the delete removes it on purpose.
                        dbOk(
                            `INSERT INTO volunteer_assignment_vasg
                               (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                             VALUES (?, ?, 4, 'accepted', 'coordinator', NOW())`,
                            [occurrenceId, positionId],
                        );

                        cy.makePrivateAdminAPICall("DELETE", `/api/ministries/teams/${teamA}`, null, 200);
                        cy.makePrivateAdminAPICall("GET", `/api/ministries/teams/${teamA}`, null, 404);

                        const gone = [
                            ["positions", "SELECT COUNT(*) AS c FROM volunteer_position_vpos WHERE vpos_ID = ?", positionId],
                            ["qualifications", "SELECT COUNT(*) AS c FROM volunteer_qualification_vqal WHERE vqal_vpos_ID = ?", positionId],
                            ["requirements", "SELECT COUNT(*) AS c FROM volunteer_requirement_vreq WHERE vreq_vpos_ID = ?", positionId],
                            ["schedules", "SELECT COUNT(*) AS c FROM volunteer_schedule_vsch WHERE vsch_ID = ?", scheduleId],
                            ["occurrences", "SELECT COUNT(*) AS c FROM volunteer_occurrence_vocc WHERE vocc_vsch_ID = ?", scheduleId],
                            ["assignments", "SELECT COUNT(*) AS c FROM volunteer_assignment_vasg WHERE vasg_vocc_ID = ?", occurrenceId],
                            [
                                "team-leader grants",
                                "SELECT COUNT(*) AS c FROM volunteer_scope_vscp WHERE vscp_ScopeType = 'team' AND vscp_ScopeId = ?",
                                teamA,
                            ],
                        ];
                        gone.forEach(([label, sql, id]) => {
                            dbOk(sql, [id]).then((rows) => {
                                expect(Number(rows[0].c), label).to.eq(0);
                            });
                        });
                    });
                });
            });
        });
    });

    // -----------------------------------------------------------------
    // §2.6 / §3.3.1 — positions
    // -----------------------------------------------------------------
    describe("Positions", () => {
        let teamId = 0;
        /** The team ministryA was created with; `positionId` lives here. */
        let homeTeamId = 0;
        let positionId = 0;

        before(() => {
            defaultTeam(ministryA).then((id) => {
                homeTeamId = id;
            });
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/teams`,
                { name: `${PREFIX} Positions Team` },
                201,
            ).then((resp) => {
                teamId = resp.body.team.id;
            });
        });

        it("creates a position in a team", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/positions`,
                {
                    name: `${PREFIX} Espresso`,
                    description: "Pulls shots",
                    teamId: homeTeamId,
                    order: 3,
                },
                201,
            ).then((resp) => {
                const position = resp.body.position;
                expect(position.ministryId).to.eq(ministryA);
                // Every position names a team (D18); there is no null case left.
                expect(position.teamId).to.eq(homeTeamId);
                expect(position.teamName).to.be.a("string").and.not.be.empty;
                expect(position.active).to.eq(true);
                expect(position.order).to.eq(3);
                positionId = position.id;
            });
        });

        it("rejects a case-insensitive duplicate inside the same team", () => {
            // The unique index is now able to catch this on its own — vpos_vtem_ID is
            // NOT NULL, so the "MySQL treats NULLs as distinct" hole is gone — but the
            // explicit check in VolunteerMinistryService::createPosition() still owns the
            // 409 and its message, and it is the half that is case-insensitive by
            // intent rather than by collation.
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/positions`,
                { name: `${PREFIX} espresso`, teamId: homeTeamId },
                409,
            );
            dbOk(
                `SELECT COUNT(*) AS c FROM volunteer_position_vpos
                  WHERE vpos_vmin_ID = ? AND vpos_vtem_ID = ? AND vpos_Name LIKE ?`,
                [ministryA, homeTeamId, `${PREFIX} Espresso`],
            ).then((rows) => {
                expect(Number(rows[0].c), "no second row was written").to.eq(1);
            });
        });

        it("defaults Self-assignable to on, and only a real boolean turns it off (2026-09-18)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/positions`,
                { name: `${PREFIX} Preacher`, description: "Chosen, not signed up for", teamId: homeTeamId, order: 9 },
                201,
            ).then((resp) => {
                const id = resp.body.position.id;
                expect(resp.body.position.selfAssignable, "the default").to.eq(true);

                cy.makePrivateAdminAPICall("POST", `/api/ministries/positions/${id}`, { selfAssignable: false }, 200).then((upd) => {
                    expect(upd.body.position.selfAssignable).to.eq(false);
                });
                cy.makePrivateAdminAPICall("GET", `/api/ministries/positions/${id}`, null, 200).then((read) => {
                    expect(read.body.position.selfAssignable).to.eq(false);
                });
                cy.makePrivateAdminAPICall("POST", `/api/ministries/positions/${id}`, { selfAssignable: "no" }, 400);
            });

            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/positions`,
                { name: `${PREFIX} Preacher Off`, teamId: homeTeamId, order: 10, selfAssignable: false },
                201,
            ).then((resp) => {
                expect(resp.body.position.selfAssignable).to.eq(false);
            });
        });

        it("rejects a position with no team at all (400)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/positions`,
                { name: `${PREFIX} Team-less` },
                400,
            );
        });

        it("accepts the same name in a different team scope", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/positions`,
                { name: `${PREFIX} Espresso`, teamId },
                201,
            ).then((resp) => {
                expect(resp.body.position.teamId).to.eq(teamId);
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/ministries/positions/${resp.body.position.id}`,
                    null,
                    200,
                );
            });
        });

        it("rejects a teamId belonging to another ministry", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryB}/teams`,
                { name: `${PREFIX} Foreign Team` },
                201,
            ).then((resp) => {
                const foreignTeam = resp.body.team.id;
                cy.makePrivateAdminAPICall(
                    "POST",
                    `${MINISTRIES_URL}/${ministryA}/positions`,
                    { name: `${PREFIX} Wrong Team`, teamId: foreignTeam },
                    400,
                );
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/ministries/teams/${foreignTeam}`,
                    null,
                    200,
                );
            });
        });

        it("reads, updates and reorders a position", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/ministries/positions/${positionId}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.position.id).to.eq(positionId);
            });
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/ministries/positions/${positionId}`,
                { description: "Pulls shots, calls drinks", order: 1 },
                200,
            ).then((resp) => {
                expect(resp.body.position.description).to.eq(
                    "Pulls shots, calls drinks",
                );
                expect(resp.body.position.order).to.eq(1);
            });
        });

        it("deactivates a position instead of destroying it", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/ministries/positions/${positionId}`,
                { active: false },
                200,
            ).then((resp) => {
                expect(resp.body.position.active).to.eq(false);
            });
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/positions?active=1`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.positions.map((p) => p.id)).to.not.include(
                    positionId,
                );
            });
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/positions`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.positions.map((p) => p.id)).to.include(
                    positionId,
                );
            });
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/ministries/positions/${positionId}`,
                { active: true },
                200,
            );
        });

        it("filters the position list by team", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/positions`,
                { name: `${PREFIX} Milk Station`, teamId },
                201,
            ).then((resp) => {
                const teamPosition = resp.body.position.id;
                cy.makePrivateAdminAPICall(
                    "GET",
                    `${MINISTRIES_URL}/${ministryA}/positions?teamId=${teamId}`,
                    null,
                    200,
                ).then((listed) => {
                    const ids = listed.body.positions.map((p) => p.id);
                    expect(ids).to.include(teamPosition);
                    expect(ids).to.not.include(positionId);
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/ministries/positions/${teamPosition}`,
                    null,
                    200,
                );
            });
        });

        it("deletes a position together with its qualifications, staffing needs and assignments (2026-09-26)", () => {
            cy.then(() => {
                // One active and one REVOKED qualification: the revoked row is listed on
                // no screen, and it used to be what made the delete impossible.
                dbOk(
                    `INSERT INTO volunteer_qualification_vqal
                       (vqal_per_ID, vqal_vpos_ID, vqal_Active, vqal_GrantedDate)
                     VALUES (1, ?, 1, NOW()), (4, ?, 0, NOW())`,
                    [positionId, positionId],
                );
                dbOk(
                    `INSERT INTO volunteer_schedule_vsch
                       (vsch_vmin_ID, vsch_vtem_ID, vsch_Name, vsch_LinkMode, vsch_WindowStart, vsch_GenerateAheadDays, vsch_Active)
                     VALUES (?, ?, ?, 'standalone', '2026-09-13', 56, 1)`,
                    [ministryA, homeTeamId, `${PREFIX} Position Weekly`],
                ).then((scheduleRows) => {
                    const scheduleId = scheduleRows.insertId;
                    dbOk(
                        `INSERT INTO volunteer_requirement_vreq (vreq_vsch_ID, vreq_vpos_ID, vreq_MinCount)
                         VALUES (?, ?, 1)`,
                        [scheduleId, positionId],
                    );
                    dbOk(
                        `INSERT INTO volunteer_occurrence_vocc
                           (vocc_vsch_ID, vocc_OccurrenceDate, vocc_Status, vocc_GeneratedDate)
                         VALUES (?, '2026-09-13', 'scheduled', NOW())`,
                        [scheduleId],
                    ).then((occurrenceRows) => {
                        const occurrenceId = occurrenceRows.insertId;
                        dbOk(
                            `INSERT INTO volunteer_assignment_vasg
                               (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                             VALUES (?, ?, 4, 'accepted', 'coordinator', NOW())`,
                            [occurrenceId, positionId],
                        );

                        cy.makePrivateAdminAPICall("DELETE", `/api/ministries/positions/${positionId}`, null, 200);
                        cy.makePrivateAdminAPICall("GET", `/api/ministries/positions/${positionId}`, null, 404);

                        const gone = [
                            ["qualifications", "SELECT COUNT(*) AS c FROM volunteer_qualification_vqal WHERE vqal_vpos_ID = ?", positionId],
                            ["requirements", "SELECT COUNT(*) AS c FROM volunteer_requirement_vreq WHERE vreq_vpos_ID = ?", positionId],
                            ["assignments", "SELECT COUNT(*) AS c FROM volunteer_assignment_vasg WHERE vasg_vpos_ID = ?", positionId],
                        ];
                        gone.forEach(([label, sql, id]) => {
                            dbOk(sql, [id]).then((rows) => {
                                expect(Number(rows[0].c), label).to.eq(0);
                            });
                        });
                        // The schedule and its occurrence belong to the team, not the position.
                        dbOk("SELECT COUNT(*) AS c FROM volunteer_occurrence_vocc WHERE vocc_ID = ?", [occurrenceId]).then(
                            (rows) => {
                                expect(Number(rows[0].c), "occurrence kept").to.eq(1);
                            },
                        );
                    });
                });
            });
        });

        it("returns 404 for a position that does not exist", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/ministries/positions/99999999",
                null,
                404,
            );
        });

        it("lets the scoped coordinator manage positions in their ministry only", () => {
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${MINISTRIES_URL}/${ministryA}/positions`,
                { name: `${PREFIX} Coordinator Position`, teamId: homeTeamId },
                201,
            ).then((resp) => {
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/ministries/positions/${resp.body.position.id}`,
                    null,
                    200,
                );
            });
            // Ministry B's own team: the payload is well formed, so the 403 is the
            // scope check and nothing else.
            defaultTeam(ministryB).then((foreignTeamId) => {
                cy.makePrivateAPICall(
                    userKey(),
                    "POST",
                    `${MINISTRIES_URL}/${ministryB}/positions`,
                    { name: `${PREFIX} Not Mine`, teamId: foreignTeamId },
                    403,
                );
            });
            cy.makePrivateAPICall(
                userKey(),
                "GET",
                `${MINISTRIES_URL}/${ministryB}/positions`,
                null,
                403,
            );
        });
    });
});
