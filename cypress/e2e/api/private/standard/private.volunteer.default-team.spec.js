/// <reference types="cypress" />

/**
 * Volunteer v2 — "every ministry always has at least one team" (epic #9701).
 *
 * The product decision this file pins, in full:
 *
 *   1. Creating a ministry auto-creates its first team, named "{Ministry} Team",
 *      in the same transaction — so every caller of the create endpoint gets it.
 *   2. A position ALWAYS belongs to a team (`vpos_vtem_ID` is NOT NULL). The API
 *      rejects a position with no `teamId` (400) and one naming another
 *      ministry's team (400).
 *   3. A schedule ALWAYS belongs to a team (`vsch_vtem_ID` NOT NULL). Same
 *      treatment: a missing `teamId` is a 400.
 *   4. The ONLY team of a ministry cannot be deleted (409). Deleting a ministry
 *      still takes its teams with it.
 *   5. Scope semantics are unchanged: a ministry coordinator manages every team,
 *      a team leader their own team only — and a team leader may now create
 *      positions and schedules in their own team exactly as before.
 *
 * Why "Whole ministry" went away: two teams under one ministry could each own a
 * "Lead Teacher" position, and a ministry-wide view showed the name twice with
 * nothing to tell them apart. A position with no team was the mechanism behind
 * that ambiguity, so the mechanism is gone rather than papered over.
 *
 * Conventions (cypress-testing.md): cleanup runs in `before` AND `after` — an
 * `after` hook does not run when the runner crashes mid-spec. Every fixture name
 * starts with PREFIX so cleanup deletes exactly what this spec made. Assertions
 * name the status and the shape, never the exact English wording, except where
 * the wording IS the decision (the 409 tells the coordinator to rename instead).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const MINISTRIES_URL = "/api/volunteer/ministries";
const TEAMS_URL = "/api/volunteer/teams";
const SCOPES_URL = "/api/volunteer/scopes";

const PERSON_COORDINATOR = 3; // tony.wade — user.api.key
const EVENT_TYPE_CHURCH_SERVICE = 1; // "Church Service" in the seed

const PREFIX = "DEFTEAM9701";

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
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

/** Teams, positions and schedules all cascade from the ministry row. */
function cleanupFixtures() {
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [
        PERSON_COORDINATOR,
    ]);
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

function createMinistry(name) {
    return cy
        .makePrivateAdminAPICall(
            "POST",
            MINISTRIES_URL,
            { name: `${PREFIX} ${name}`, description: "default-team spec" },
            201,
        )
        .then((resp) => resp.body.ministry);
}

/** The team a fresh ministry was born with. */
function defaultTeamOf(ministryId) {
    return cy
        .makePrivateAdminAPICall("GET", `${MINISTRIES_URL}/${ministryId}`, null, 200)
        .then((resp) => {
            expect(resp.body.teams, "the ministry has exactly one team").to.have.length(1);
            return resp.body.teams[0];
        });
}

function scheduleBody(teamId, name) {
    return {
        name: `${PREFIX} ${name}`,
        teamId,
        linkMode: "event_type",
        eventTypeId: EVENT_TYPE_CHURCH_SERVICE,
        windowStart: "2026-09-13",
    };
}

describe("Volunteer v2 — every ministry has at least one team (#9701)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    // -----------------------------------------------------------------
    // 1 — the default team
    // -----------------------------------------------------------------
    describe("Creating a ministry creates its first team", () => {
        it("names the team after the ministry and counts it on the create response", () => {
            createMinistry("Coffee Bar").then((ministry) => {
                expect(ministry.teamCount, "the new ministry already has a team").to.eq(1);

                defaultTeamOf(ministry.id).then((team) => {
                    expect(team.name).to.eq(`${PREFIX} Coffee Bar Team`);
                    expect(team.ministryId).to.eq(ministry.id);
                    expect(team.active).to.eq(true);
                    expect(team.positionCount).to.eq(0);
                });
            });
        });

        it("writes the team in the same transaction as the ministry", () => {
            createMinistry("Transactional").then((ministry) => {
                dbOk(
                    `SELECT COUNT(*) AS c FROM volunteer_team_vtem WHERE vtem_vmin_ID = ?`,
                    [ministry.id],
                ).then((rows) => {
                    expect(Number(rows[0].c)).to.eq(1);
                });
            });
        });

        it("lets the auto-created team be renamed like any other team", () => {
            createMinistry("Renameable").then((ministry) => {
                defaultTeamOf(ministry.id).then((team) => {
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `${TEAMS_URL}/${team.id}`,
                        { name: `${PREFIX} Sunday Crew` },
                        200,
                    ).then((resp) => {
                        expect(resp.body.team.name).to.eq(`${PREFIX} Sunday Crew`);
                        expect(resp.body.team.id).to.eq(team.id);
                    });
                });
            });
        });

        it("still lets a coordinator add more teams alongside it", () => {
            createMinistry("Two Teams").then((ministry) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `${MINISTRIES_URL}/${ministry.id}/teams`,
                    { name: `${PREFIX} Second Team` },
                    201,
                );
                cy.makePrivateAdminAPICall(
                    "GET",
                    `${MINISTRIES_URL}/${ministry.id}`,
                    null,
                    200,
                ).then((resp) => {
                    expect(resp.body.teams).to.have.length(2);
                });
            });
        });
    });

    // -----------------------------------------------------------------
    // 2 — positions always belong to a team
    // -----------------------------------------------------------------
    describe("Positions always belong to a team", () => {
        let ministryId = 0;
        let teamId = 0;
        let foreignTeamId = 0;

        before(() => {
            createMinistry("Positions").then((ministry) => {
                ministryId = ministry.id;
                defaultTeamOf(ministryId).then((team) => {
                    teamId = team.id;
                });
            });
            createMinistry("Foreign").then((ministry) => {
                defaultTeamOf(ministry.id).then((team) => {
                    foreignTeamId = team.id;
                });
            });
        });

        it("creates a position in a team", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/positions`,
                { name: `${PREFIX} Espresso`, teamId, order: 1 },
                201,
            ).then((resp) => {
                expect(resp.body.position.teamId).to.eq(teamId);
                expect(resp.body.position.teamName).to.be.a("string").and.not.be.empty;
            });
        });

        it("rejects a position with no teamId (400)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/positions`,
                { name: `${PREFIX} Team-less` },
                400,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", false);
            });
        });

        it("rejects a position with an explicitly null teamId (400)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/positions`,
                { name: `${PREFIX} Null Team`, teamId: null },
                400,
            );
        });

        it("rejects a team from another ministry (400)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/positions`,
                { name: `${PREFIX} Wrong Ministry`, teamId: foreignTeamId },
                400,
            );
        });

        it("refuses to move an existing position out of every team (400)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/positions`,
                { name: `${PREFIX} Movable`, teamId, order: 9 },
                201,
            ).then((resp) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/volunteer/positions/${resp.body.position.id}`,
                    { teamId: null },
                    400,
                );
            });
        });

        it("keeps the database from holding a team-less position at all", () => {
            cy.dbQuery(
                `INSERT INTO volunteer_position_vpos
                    (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Active, vpos_Order)
                 VALUES (?, NULL, ?, 1, 0)`,
                [ministryId, `${PREFIX} Direct Insert`],
            ).then((result) => {
                expect(result.error, "vpos_vtem_ID is NOT NULL").to.not.eq(null);
            });
        });
    });

    // -----------------------------------------------------------------
    // 3 — schedules always belong to a team
    // -----------------------------------------------------------------
    describe("Schedules always belong to a team", () => {
        let ministryId = 0;
        let teamId = 0;

        before(() => {
            createMinistry("Schedules").then((ministry) => {
                ministryId = ministry.id;
                defaultTeamOf(ministryId).then((team) => {
                    teamId = team.id;
                });
            });
        });

        it("creates a schedule in a team", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/schedules`,
                scheduleBody(teamId, "Sunday"),
                201,
            ).then((resp) => {
                expect(resp.body.schedule.teamId).to.eq(teamId);
            });
        });

        it("rejects a schedule with no teamId (400)", () => {
            const body = scheduleBody(teamId, "No Team");
            delete body.teamId;
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/schedules`,
                body,
                400,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", false);
            });
        });

        it("rejects a schedule with an explicitly null teamId (400)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/schedules`,
                scheduleBody(null, "Null Team"),
                400,
            );
        });

        it("keeps the database from holding a team-less schedule at all", () => {
            cy.dbQuery(
                `INSERT INTO volunteer_schedule_vsch
                    (vsch_vmin_ID, vsch_vtem_ID, vsch_Name, vsch_LinkMode, vsch_event_type_id,
                     vsch_RecurType, vsch_WindowStart, vsch_GenerateAheadDays, vsch_Active)
                 VALUES (?, NULL, ?, 'event_type', ?, 'none', '2026-09-13', 56, 1)`,
                [ministryId, `${PREFIX} Direct Insert`, EVENT_TYPE_CHURCH_SERVICE],
            ).then((result) => {
                expect(result.error, "vsch_vtem_ID is NOT NULL").to.not.eq(null);
            });
        });
    });

    // -----------------------------------------------------------------
    // 4 — the last team cannot be deleted
    // -----------------------------------------------------------------
    describe("The only team of a ministry cannot be deleted", () => {
        it("refuses the last team with a 409 that says to rename it instead", () => {
            createMinistry("Last Team").then((ministry) => {
                defaultTeamOf(ministry.id).then((team) => {
                    cy.makePrivateAdminAPICall(
                        "DELETE",
                        `${TEAMS_URL}/${team.id}`,
                        null,
                        409,
                    ).then((resp) => {
                        expect(resp.body).to.have.property("success", false);
                        // The wording IS the decision here: the coordinator has to be
                        // told what to do instead, or the 409 is a dead end.
                        expect(resp.body.message).to.contain("at least one team");
                        expect(resp.body.message).to.contain("Rename");
                    });

                    // And it is genuinely still there.
                    cy.makePrivateAdminAPICall(
                        "GET",
                        `${TEAMS_URL}/${team.id}`,
                        null,
                        200,
                    );
                });
            });
        });

        it("still deletes a team that is not the last one", () => {
            createMinistry("Deletable").then((ministry) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `${MINISTRIES_URL}/${ministry.id}/teams`,
                    { name: `${PREFIX} Spare Team` },
                    201,
                ).then((resp) => {
                    const spareId = resp.body.team.id;
                    cy.makePrivateAdminAPICall(
                        "DELETE",
                        `${TEAMS_URL}/${spareId}`,
                        null,
                        200,
                    );
                    cy.makePrivateAdminAPICall(
                        "GET",
                        `${MINISTRIES_URL}/${ministry.id}`,
                        null,
                        200,
                    ).then((detail) => {
                        expect(detail.body.teams).to.have.length(1);
                    });
                });
            });
        });

        it("still deletes a ministry together with its teams", () => {
            createMinistry("Disposable").then((ministry) => {
                // An active ministry cannot be deleted (409): deactivate first (2026-09-17 lifecycle rule).
                cy.makePrivateAdminAPICall("POST", `${MINISTRIES_URL}/${ministry.id}`, { active: false }, [200, 404]);
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${MINISTRIES_URL}/${ministry.id}`,
                    null,
                    200,
                );
                dbOk(
                    `SELECT COUNT(*) AS c FROM volunteer_team_vtem WHERE vtem_vmin_ID = ?`,
                    [ministry.id],
                ).then((rows) => {
                    expect(Number(rows[0].c), "the teams went with it").to.eq(0);
                });
            });
        });
    });

    // -----------------------------------------------------------------
    // 5 — scope semantics are unchanged
    // -----------------------------------------------------------------
    describe("Scope semantics survive the change", () => {
        let ministryId = 0;
        let teamId = 0;

        before(() => {
            createMinistry("Scoped").then((ministry) => {
                ministryId = ministry.id;
                defaultTeamOf(ministryId).then((team) => {
                    teamId = team.id;
                    cy.makePrivateAdminAPICall(
                        "POST",
                        SCOPES_URL,
                        {
                            personId: PERSON_COORDINATOR,
                            scopeType: "team",
                            scopeId: teamId,
                        },
                        [200, 201],
                    );
                });
            });
        });

        it("lets a team leader create a position in their own team", () => {
            cy.makePrivateAPICall(
                Cypress.env("user.api.key"),
                "POST",
                `${MINISTRIES_URL}/${ministryId}/positions`,
                { name: `${PREFIX} Leader Made`, teamId, order: 1 },
                201,
            ).then((resp) => {
                expect(resp.body.position.teamId).to.eq(teamId);
            });
        });

        it("still refuses a team leader a position with no team (400, not 201)", () => {
            cy.makePrivateAPICall(
                Cypress.env("user.api.key"),
                "POST",
                `${MINISTRIES_URL}/${ministryId}/positions`,
                { name: `${PREFIX} Leader Team-less` },
                400,
            );
        });

        it("does not let a team leader delete the team they lead", () => {
            cy.makePrivateAPICall(
                Cypress.env("user.api.key"),
                "DELETE",
                `${TEAMS_URL}/${teamId}`,
                null,
                403,
            );
        });
    });
});
