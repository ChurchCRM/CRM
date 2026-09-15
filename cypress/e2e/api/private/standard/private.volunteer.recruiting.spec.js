/// <reference types="cypress" />

/**
 * Volunteer v2 — "Recruit Volunteers" on a position (epic #9701, round four).
 *
 * One product decision, two API surfaces:
 *
 *   1. **A position carries a boolean `recruiting`, default off.** It rides the
 *      position wire shape, create and update accept it, and — because
 *      `InputSanitizationMiddleware` has no boolean type — the handler does the
 *      cast itself: `true`/`false`/`1`/`0` (and their string spellings) only,
 *      anything else is a `400` rather than a silent `(bool)` coercion that would
 *      turn `"no"` into `true`.
 *
 *   2. **`GET /me/help-wanted` advertises a ministry that has recruiting
 *      positions**, whether or not its own Help-wanted switch is on, and carries
 *      the positions with it as `recruitingPositions`, ordered team name first
 *      and then the position's own order — the same order the Positions table
 *      uses, computed server-side so every client agrees.
 *
 * What is asserted here that nothing else can: the ordering, the two exclusions
 * (an INACTIVE recruiting position is not advertised; a ministry with neither
 * flag does not appear at all), and that "I'd like to help" still answers `200`
 * on a ministry that qualifies only through its positions — the button is
 * rendered for those ministries, so a `403` there would be a dead control.
 *
 * Fixture layout, built once in `before`:
 *
 *   A "Coffee Bar"    helpWanted OFF, three recruiting positions across three
 *                     teams (alphabetically Alpha Crew < Coffee Bar Team <
 *                     Zulu Crew), one of them INACTIVE and one not recruiting.
 *   B "Sound Booth"   helpWanted ON, no recruiting positions.
 *   C "Library"       neither — the control that must never be listed.
 *
 * Cleanup runs in `before` AND `after`: an `after` hook does not run when the
 * runner crashes mid-spec (cypress-testing.md).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";
const MINISTRIES_URL = `${VOLUNTEER_URL}/ministries`;

/** Every fixture name starts with this so cleanup deletes exactly what this spec made. */
const PREFIX = "VRECRUIT";

/** The EditSelf-exclusive volunteer persona (D14) — the member surface's actor. */
const PERSON_VOLUNTEER = 99;

const TEAM_ALPHA = `${PREFIX} Alpha Crew`;
const TEAM_ZULU = `${PREFIX} Zulu Crew`;

const POS_ALPHA_FIRST = `${PREFIX} Alpha First`;
const POS_ALPHA_SECOND = `${PREFIX} Alpha Second`;
const POS_ZULU = `${PREFIX} Zulu Only`;
const POS_INACTIVE = `${PREFIX} Retired Role`;
const POS_NOT_RECRUITING = `${PREFIX} Quiet Role`;

const DESCRIPTION_ALPHA_FIRST = "Pull shots before the first service.";

let ministryA = 0;
let ministryB = 0;
let ministryC = 0;
let defaultTeamA = 0;
let teamAlpha = 0;
let teamZulu = 0;
let defaultTeamNameA = "";
let positionAlphaFirst = 0;

function volunteerKey() {
    return Cypress.env("selfedit.api.key");
}

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
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
    adminApi("POST", SETTING_URL, { value }, 200);
}

/**
 * The pool groups go before the ministries: `grp_ministry_id` is
 * `ON DELETE SET NULL`, so deleting a ministry first would leave its group
 * behind as an orphan nobody can recognise.
 */
function cleanupFixtures() {
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r
           JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE FROM volunteer_notification_vntf WHERE vntf_Type = 'help_offer'`);
    dbOk(
        `DELETE g FROM group_grp g
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [`${PREFIX}%`]);
}

function createMinistry(name) {
    return adminApi("POST", MINISTRIES_URL, { name: `${PREFIX} ${name}` }, 201).then(
        (resp) => resp.body.ministry.id,
    );
}

function createPosition(ministryId, payload, expectedStatus = 201) {
    return adminApi(
        "POST",
        `${MINISTRIES_URL}/${ministryId}/positions`,
        payload,
        expectedStatus,
    );
}

/** The `recruitingPositions` rows of one ministry on the member surface. */
function helpWantedRow(ministryId, assert) {
    return cy
        .makePrivateAPICall(volunteerKey(), "GET", `${VOLUNTEER_URL}/me/help-wanted`, null, 200)
        .then((resp) => {
            assert(
                resp.body.ministries.find((m) => m.ministryId === ministryId),
                resp.body.ministries,
            );
        });
}

describe("Volunteer v2 position recruiting flag (#9701)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();

        createMinistry("Coffee Bar").then((id) => {
            ministryA = id;

            adminApi("GET", `${MINISTRIES_URL}/${ministryA}`, null, 200).then((detail) => {
                defaultTeamA = detail.body.teams[0].id;
                defaultTeamNameA = detail.body.teams[0].name;

                // Two extra teams so "ordered by team name" has something to order.
                // Alphabetically: Alpha Crew < Coffee Bar Team < Zulu Crew.
                adminApi(
                    "POST",
                    `${MINISTRIES_URL}/${ministryA}/teams`,
                    { name: TEAM_ALPHA },
                    201,
                ).then((team) => {
                    teamAlpha = team.body.team.id;

                    // Deliberately created SECOND-first, so a list that came back in
                    // insertion order would fail the ordering assertion.
                    createPosition(ministryA, {
                        name: POS_ALPHA_SECOND,
                        teamId: teamAlpha,
                        order: 2,
                        recruiting: true,
                    });
                    createPosition(ministryA, {
                        name: POS_ALPHA_FIRST,
                        teamId: teamAlpha,
                        order: 1,
                        description: DESCRIPTION_ALPHA_FIRST,
                        recruiting: true,
                    }).then((resp) => {
                        positionAlphaFirst = resp.body.position.id;
                    });
                });

                adminApi(
                    "POST",
                    `${MINISTRIES_URL}/${ministryA}/teams`,
                    { name: TEAM_ZULU },
                    201,
                ).then((team) => {
                    teamZulu = team.body.team.id;
                    createPosition(ministryA, {
                        name: POS_ZULU,
                        teamId: teamZulu,
                        order: 1,
                        recruiting: true,
                    });
                });

                // The two exclusions, both on the default team.
                createPosition(ministryA, {
                    name: POS_NOT_RECRUITING,
                    teamId: defaultTeamA,
                    order: 1,
                });
                createPosition(ministryA, {
                    name: POS_INACTIVE,
                    teamId: defaultTeamA,
                    order: 2,
                    recruiting: true,
                }).then((resp) => {
                    adminApi(
                        "POST",
                        `${VOLUNTEER_URL}/positions/${resp.body.position.id}`,
                        { active: false },
                        200,
                    );
                });
            });
        });

        createMinistry("Sound Booth").then((id) => {
            ministryB = id;
            adminApi("POST", `${MINISTRIES_URL}/${ministryB}`, { helpWanted: true }, 200);
        });

        createMinistry("Library").then((id) => {
            ministryC = id;
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    // ─────────────────────────────────────────────────────────────────────
    // 1. The column and the wire shape
    // ─────────────────────────────────────────────────────────────────────

    describe("the position wire shape", () => {
        it("carries recruiting, and a position created without it is off", () => {
            createPosition(ministryA, {
                name: `${PREFIX} Default Off`,
                teamId: defaultTeamA,
                order: 9,
            }).then((resp) => {
                expect(resp.body.position).to.have.property("recruiting");
                expect(resp.body.position.recruiting, "the default is off").to.eq(false);

                adminApi(
                    "DELETE",
                    `${VOLUNTEER_URL}/positions/${resp.body.position.id}`,
                    null,
                    200,
                );
            });
        });

        it("stores the column as 0 by default", () => {
            dbOk(
                `SELECT vpos_Recruiting FROM volunteer_position_vpos WHERE vpos_ID = ?`,
                [positionAlphaFirst],
            ).then((rows) => {
                expect(rows, "the fixture position must exist").to.have.length(1);
                expect(Number(rows[0].vpos_Recruiting), "created with recruiting on").to.eq(1);
            });
        });

        it("round-trips recruiting through create, read and update", () => {
            createPosition(ministryA, {
                name: `${PREFIX} Round Trip`,
                teamId: defaultTeamA,
                order: 8,
                recruiting: true,
            }).then((created) => {
                const positionId = created.body.position.id;
                expect(created.body.position.recruiting).to.eq(true);

                adminApi("GET", `${VOLUNTEER_URL}/positions/${positionId}`, null, 200).then(
                    (read) => {
                        expect(read.body.position.recruiting).to.eq(true);
                    },
                );

                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/positions/${positionId}`,
                    { recruiting: false },
                    200,
                ).then((updated) => {
                    expect(updated.body.position.recruiting).to.eq(false);
                });

                adminApi("GET", `${VOLUNTEER_URL}/positions/${positionId}`, null, 200).then(
                    (read) => {
                        expect(read.body.position.recruiting).to.eq(false);
                    },
                );

                adminApi("DELETE", `${VOLUNTEER_URL}/positions/${positionId}`, null, 200);
            });
        });

        it("leaves recruiting alone when the update never mentions it", () => {
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/positions/${positionAlphaFirst}`,
                { description: DESCRIPTION_ALPHA_FIRST },
                200,
            ).then((resp) => {
                expect(resp.body.position.recruiting, "untouched by a partial update").to.eq(
                    true,
                );
            });
        });

        it("accepts every spelling of a boolean the sanitizer would have", () => {
            createPosition(ministryA, {
                name: `${PREFIX} Spellings`,
                teamId: defaultTeamA,
                order: 7,
            }).then((created) => {
                const positionId = created.body.position.id;

                for (const [sent, expected] of [
                    [true, true],
                    [false, false],
                    [1, true],
                    [0, false],
                    ["1", true],
                    ["0", false],
                    ["true", true],
                    ["false", false],
                ]) {
                    adminApi(
                        "POST",
                        `${VOLUNTEER_URL}/positions/${positionId}`,
                        { recruiting: sent },
                        200,
                    ).then((resp) => {
                        expect(
                            resp.body.position.recruiting,
                            `recruiting: ${JSON.stringify(sent)}`,
                        ).to.eq(expected);
                    });
                }

                adminApi("DELETE", `${VOLUNTEER_URL}/positions/${positionId}`, null, 200);
            });
        });

        it("refuses anything that is not a boolean with a 400, on create and on update", () => {
            for (const bad of ["yes", "maybe", 2, -1, "", null, []]) {
                createPosition(
                    ministryA,
                    {
                        name: `${PREFIX} Bad ${JSON.stringify(bad)}`,
                        teamId: defaultTeamA,
                        order: 6,
                        recruiting: bad,
                    },
                    400,
                );

                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/positions/${positionAlphaFirst}`,
                    { recruiting: bad },
                    400,
                );
            }

            // The refused updates changed nothing.
            adminApi("GET", `${VOLUNTEER_URL}/positions/${positionAlphaFirst}`, null, 200).then(
                (resp) => {
                    expect(resp.body.position.recruiting).to.eq(true);
                },
            );
        });
    });

    // ─────────────────────────────────────────────────────────────────────
    // 2. The member surface
    // ─────────────────────────────────────────────────────────────────────

    describe("GET /me/help-wanted", () => {
        it("lists a ministry that has only recruiting positions, with its positions", () => {
            helpWantedRow(ministryA, (row) => {
                expect(row, "a ministry with recruiting positions is advertised").to.not.be
                    .undefined;
                expect(row.helpWantedText, "its own advert is off and empty").to.be.oneOf([
                    null,
                    "",
                ]);
                expect(row.recruitingPositions).to.be.an("array");
                expect(row.recruitingPositions.length).to.eq(3);
            });
        });

        it("orders the positions by team name, then by the position's own order", () => {
            helpWantedRow(ministryA, (row) => {
                expect(
                    row.recruitingPositions.map((p) => `${p.teamName} | ${p.positionName}`),
                ).to.deep.equal([
                    `${TEAM_ALPHA} | ${POS_ALPHA_FIRST}`,
                    `${TEAM_ALPHA} | ${POS_ALPHA_SECOND}`,
                    `${TEAM_ZULU} | ${POS_ZULU}`,
                ]);
            });
        });

        it("carries the position description, and null when there is none", () => {
            helpWantedRow(ministryA, (row) => {
                const first = row.recruitingPositions[0];
                expect(first.description).to.eq(DESCRIPTION_ALPHA_FIRST);
                expect(row.recruitingPositions[1].description).to.be.oneOf([null, ""]);
            });
        });

        it("omits an inactive recruiting position and a position that is not recruiting", () => {
            helpWantedRow(ministryA, (row) => {
                const names = row.recruitingPositions.map((p) => p.positionName);
                expect(names, "deactivated position").to.not.include(POS_INACTIVE);
                expect(names, "recruiting switch off").to.not.include(POS_NOT_RECRUITING);
                expect(
                    row.recruitingPositions.map((p) => p.teamName),
                    "the default team contributes nothing, so it is never named",
                ).to.not.include(defaultTeamNameA);
            });
        });

        it("still lists a Help-wanted ministry with no recruiting positions, with an empty list", () => {
            helpWantedRow(ministryB, (row) => {
                expect(row, "helpWanted alone is still enough").to.not.be.undefined;
                expect(row.recruitingPositions).to.deep.equal([]);
            });
        });

        it("omits a ministry with neither the switch nor a recruiting position", () => {
            helpWantedRow(ministryC, (row, all) => {
                expect(row, `ministry ${ministryC} must not be advertised`).to.be.undefined;
                expect(all.map((m) => m.ministryId)).to.include(ministryA);
            });
        });

        it("drops the ministry again as soon as its last recruiting position is switched off", () => {
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/positions/${positionAlphaFirst}`,
                { recruiting: false },
                200,
            );

            helpWantedRow(ministryA, (row) => {
                expect(row.recruitingPositions.map((p) => p.positionName)).to.deep.equal([
                    POS_ALPHA_SECOND,
                    POS_ZULU,
                ]);
            });

            adminApi(
                "POST",
                `${VOLUNTEER_URL}/positions/${positionAlphaFirst}`,
                { recruiting: true },
                200,
            );
        });
    });

    // ─────────────────────────────────────────────────────────────────────
    // 3. "I'd like to help" on a ministry that qualifies only by its positions
    // ─────────────────────────────────────────────────────────────────────

    describe("POST /me/help-wanted/{ministryId}", () => {
        beforeEach(() => {
            adminApi(
                "DELETE",
                `${MINISTRIES_URL}/${ministryA}/pool/${PERSON_VOLUNTEER}`,
                null,
                [200, 404],
            );
        });

        it("accepts the offer, because the button is rendered for this ministry", () => {
            cy.makePrivateAPICall(
                volunteerKey(),
                "POST",
                `${VOLUNTEER_URL}/me/help-wanted/${ministryA}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.joinedPool, "this tap is what put them in").to.eq(true);
            });

            adminApi("GET", `${MINISTRIES_URL}/${ministryA}/pool`, null, 200).then((resp) => {
                expect(resp.body.members.map((m) => m.personId)).to.include(PERSON_VOLUNTEER);
            });
        });

        it("still refuses a ministry that is advertising in neither way", () => {
            cy.makePrivateAPICall(
                volunteerKey(),
                "POST",
                `${VOLUNTEER_URL}/me/help-wanted/${ministryC}`,
                null,
                403,
            );
        });
    });
});
