/// <reference types="cypress" />

/**
 * Volunteer v2 — volunteer pool and qualification API (#9707, epic #9701).
 *
 * A sibling of `private.volunteer.setup-api.spec.js` rather than a new describe
 * inside it: that file is already 900 lines covering ministries, teams and
 * positions (#9715), and the pool/qualification surface adds roughly as much
 * again. Two files that each fit on a screen of scrollback beat one that does
 * not; the fixtures are disjoint (a different `PREFIX`), so the two can run in
 * either order or in parallel.
 *
 * REWRITTEN BY D19. The pool is no longer a link table: a ministry owns exactly one
 * core Group, created with it and carrying `group_grp.grp_ministry_id`. The "Pools"
 * block below is therefore about that group's MEMBERSHIP, and everything about
 * linking, unlinking, `volunteer_pool_vpol` and several groups per owner is gone.
 * The group's creation, rename, deletion and the coordinator write path live in
 * `private.volunteer.ministry-group.spec.js`; this file keeps the membership surface
 * and the qualification half.
 *
 * What is asserted, and where the design says so:
 *
 *   D19   a ministry's pool is its own Group; membership is read live and is
 *         editable by the ministry's coordinator; qualifying somebody adds them
 *         to the pool and revoking never removes them
 *   §2.7  qualification is per (person, position) — UNIQUE (vqal_per_ID,
 *         vqal_vpos_ID) — revocation is DEACTIVATION so history survives, and a
 *         re-grant reactivates the SAME row rather than inserting a second
 *   D16   one person may hold several qualifications in one team and one ministry
 *   §3.3.1 the endpoint set, its status codes and its idempotency rules
 *   §4.6  who may link a pool and who may grant a qualification: coordinator in
 *         their own ministry, team leader for their own team's positions only
 *   P5/P6 the Cart is the bulk-selection mechanism; V2 adds a sink, not a second
 *         selection UI
 *
 * Seed fixtures (design §6.4). Since D19 a ministry's pool starts EMPTY — the group
 * is created with the ministry and nobody is in it — so the people this spec works
 * with are put there by the spec itself. The seed group ids below are kept only
 * because a couple of tests still need a person id that is certainly a real person.
 *
 * Tiers exercised:
 *   person 1   `admin.api.key`      administrator — bypasses every ROLE gate
 *   person 3   `user.api.key`       coordinator of ministry A, team leader in B
 *   person 900 `plainauth.api.key`  the "no volunteer rights" negative case
 *
 * §6.6 rule honoured: never assert a strict 403 on an admin-keyed call to a
 * role-gated route. The admin-keyed 403s below come from
 * VolunteerV2EnabledMiddleware, which nobody bypasses.
 *
 * Cleanup runs in `before` AND `after` (cypress-testing.md: an `after` hook does
 * not run when the runner crashes mid-spec), children first so no foreign key
 * blocks.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";
const MINISTRIES_URL = `${VOLUNTEER_URL}/ministries`;
const SCOPES_URL = `${VOLUNTEER_URL}/scopes`;
const CART_URL = "/api/cart/";

const PERSON_COORDINATOR = 3; // tony.wade — user.api.key
const PERSON_PLAIN = 900; // john.plainauth — plainauth.api.key


/**
 * Four real people from the seed, used as pool members and qualification subjects.
 * Read as ids rather than through a group, because a ministry's pool is its own
 * group now and starts with nobody in it (D19).
 */
const POOL_MEMBERS = [4, 5, 8, 9];

/**
 * A pool member this spec never qualifies — the "In the pool, not qualified yet"
 * row the matrix has to keep. Deliberately not one of `POOL_MEMBERS`: those are
 * qualified all over the qualification block, so using one here would make the
 * assertion depend on the order the tests happened to run in.
 */
const MATRIX_POOL_ONLY = 63;

/** Every fixture name starts with this so cleanup deletes exactly what this spec made. */
const PREFIX = "POOL9707";

let ministryA = 0;
let ministryB = 0;
let teamA = 0;
let teamB = 0;
/** Three team-scoped positions in ministry A, team A — the D16 multi-qualification case. */
let positionSetup = 0;
let positionEspresso = 0;
let positionExpeditor = 0;
/**
 * Two positions in ministry B, in two DIFFERENT teams: the team-leader boundary.
 *
 * `positionBTeam` is in team B, which person 3 leads. `positionBOtherTeam` is in
 * the team ministry B was created with, which they do not — it used to be a
 * ministry-wide position, and since every position now belongs to a team, another
 * team's position is what "outside this leader's scope, inside the same ministry"
 * means.
 */
let positionBOtherTeam = 0;
let positionBTeam = 0;
/** The team ministry B was born with; nobody in this spec leads it. */
let teamBDefault = 0;

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
 * Delete every row this spec could have created. Qualifications and pools
 * cascade from the position / ministry rows, but they are removed explicitly
 * first so a failure in the middle of the chain still leaves a clean database.
 * Scope rows carry no FK to their target (§2.15) and go by person id.
 */
function cleanupFixtures() {
    dbOk(
        `DELETE q FROM volunteer_qualification_vqal q
           JOIN volunteer_position_vpos p ON p.vpos_ID = q.vqal_vpos_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = p.vpos_vmin_ID
          WHERE m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    // D19: each ministry owns a Group. `grp_ministry_id` is ON DELETE SET NULL, so
    // deleting the ministry row in SQL would leave the group behind as an orphan
    // nobody recognises — the memberships and the groups go first, by the link.
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
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID IN (?, ?)`, [
        PERSON_COORDINATOR,
        PERSON_PLAIN,
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
            { name: `${PREFIX} ${name}`, description: "created by the #9707 API spec" },
            201,
        )
        .then((resp) => resp.body.ministry.id);
}

function createTeam(ministryId, name) {
    return cy
        .makePrivateAdminAPICall(
            "POST",
            `${MINISTRIES_URL}/${ministryId}/teams`,
            { name: `${PREFIX} ${name}` },
            201,
        )
        .then((resp) => resp.body.team.id);
}

/** The team a ministry was born with — every ministry is created with one. */
function defaultTeam(ministryId) {
    return cy
        .makePrivateAdminAPICall("GET", `${MINISTRIES_URL}/${ministryId}`, null, 200)
        .then((resp) => resp.body.teams[0].id);
}

function createPosition(ministryId, name, teamId) {
    return cy
        .makePrivateAdminAPICall(
            "POST",
            `${MINISTRIES_URL}/${ministryId}/positions`,
            { name: `${PREFIX} ${name}`, teamId },
            201,
        )
        .then((resp) => resp.body.position.id);
}

function grantScope(scopeType, scopeId) {
    return cy.makePrivateAdminAPICall(
        "POST",
        SCOPES_URL,
        { personId: PERSON_COORDINATOR, scopeType, scopeId },
        [200, 201],
    );
}

function grantQualification(positionId, personId, expected = 201) {
    return cy.makePrivateAdminAPICall(
        "POST",
        `${VOLUNTEER_URL}/positions/${positionId}/qualifications`,
        { personId },
        expected,
    );
}

/** How many qualification rows exist for this pair, whatever their active flag. */
function countQualificationRows(personId, positionId) {
    return dbOk(
        `SELECT COUNT(*) AS c FROM volunteer_qualification_vqal
          WHERE vqal_per_ID = ? AND vqal_vpos_ID = ?`,
        [personId, positionId],
    ).then((rows) => Number(rows[0].c));
}

describe("Volunteer v2 pool and qualification API (#9707)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();

        createMinistry("Coffee Bar").then((id) => {
            ministryA = id;
            // The ministry came with "<name> Team"; adopt it rather than asking for a
            // second team of the same name, which is now a 409.
            defaultTeam(ministryA).then((teamId) => {
                teamA = teamId;
                createPosition(ministryA, "Setup", teamA).then((p) => {
                    positionSetup = p;
                });
                createPosition(ministryA, "Espresso", teamA).then((p) => {
                    positionEspresso = p;
                });
                createPosition(ministryA, "Expeditor", teamA).then((p) => {
                    positionExpeditor = p;
                });
            });
        });

        createMinistry("Sound Booth").then((id) => {
            ministryB = id;
            createTeam(ministryB, "Sound Team").then((teamId) => {
                teamB = teamId;
                createPosition(ministryB, "Monitor Engineer", teamB).then((p) => {
                    positionBTeam = p;
                });
            });
            // A position in ministry B's OTHER team — the one it was created with.
            defaultTeam(ministryB).then((teamId) => {
                teamBDefault = teamId;
                createPosition(ministryB, "Audio Engineer", teamBDefault).then((p) => {
                    positionBOtherTeam = p;
                });
            });
        });
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
                url: `${MINISTRIES_URL}/${ministryA}/pool`,
                failOnStatusCode: false,
                withCredentials: false,
            }).then((resp) => {
                expect(resp.status).to.eq(401);
            });
        });

        it("returns 403 for the pool and qualification routes while the rollout state is v1", () => {
            setVersion("v1");
            // Admin deliberately: this 403 is VolunteerV2EnabledMiddleware, which an
            // administrator does NOT bypass.
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pool`,
                null,
                403,
            );
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/positions/${positionSetup}/qualifications`,
                null,
                403,
            );
            setVersion("v2");
        });

        it("denies a user with no manager flag and no scope", () => {
            cy.makePrivatePlainAuthAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pool`,
                null,
                403,
            );
            cy.makePrivatePlainAuthAPICall(
                "POST",
                `${VOLUNTEER_URL}/positions/${positionSetup}/qualifications`,
                { personId: 4 },
                403,
            );
        });
    });

    // -----------------------------------------------------------------
    // D19 — the ministry's own pool Group and who is in it
    // -----------------------------------------------------------------
    describe("The pool Group", () => {
        it("gives a new ministry an empty pool Group of its own", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pool`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.groupId).to.be.a("number").and.to.be.greaterThan(0);
                expect(resp.body.groupName).to.eq(`${PREFIX} Coffee Bar`);
                expect(resp.body.members).to.be.an("array").that.is.empty;
            });
        });

        it("adds a person to the pool, through the Group's own membership table", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pool/${POOL_MEMBERS[0]}`,
                null,
                201,
            ).then((resp) => {
                expect(resp.body.added).to.eq(true);
            });

            // Nothing is copied into V2: the row is in the CORE membership table
            // (D1), hanging off the ministry's group.
            dbOk(
                `SELECT COUNT(*) AS c FROM person2group2role_p2g2r r
                   JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
                  WHERE g.grp_ministry_id = ? AND r.p2g2r_per_ID = ?`,
                [ministryA, POOL_MEMBERS[0]],
            ).then((rows) => {
                expect(Number(rows[0].c)).to.eq(1);
            });
        });

        it("is idempotent: adding somebody already in the pool is 200, not 409", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pool/${POOL_MEMBERS[0]}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.added).to.eq(false);
            });
        });

        it("lists the pool alphabetically, with each person's qualification count", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pool/${POOL_MEMBERS[1]}`,
                null,
                201,
            );
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pool`,
                null,
                200,
            ).then((resp) => {
                const ids = resp.body.members.map((m) => m.personId);
                expect(ids).to.include.members([POOL_MEMBERS[0], POOL_MEMBERS[1]]);
                for (const member of resp.body.members) {
                    expect(member.inPool).to.eq(true);
                    expect(member.displayName).to.be.a("string").and.not.to.eq("");
                }
                const names = resp.body.members.map((m) => m.displayName.toLowerCase());
                expect(names).to.deep.eq([...names].sort());
            });
        });

        it("rejects an unknown person with 404 and an unknown ministry with 404", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pool/99999999`,
                null,
                404,
            );
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/99999999/pool/${POOL_MEMBERS[0]}`,
                null,
                404,
            );
        });

        it("removes somebody from the pool without deleting anything else", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pool/${POOL_MEMBERS[2]}`,
                null,
                201,
            );
            cy.makePrivateAdminAPICall(
                "DELETE",
                `${MINISTRIES_URL}/${ministryA}/pool/${POOL_MEMBERS[2]}`,
                null,
                200,
            );
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pool`,
                null,
                200,
            ).then((resp) => {
                const ids = resp.body.members.map((m) => m.personId);
                expect(ids).to.not.include(POOL_MEMBERS[2]);
            });
            // The person still exists — "remove from the pool" is not "delete".
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/person/${POOL_MEMBERS[2]}`,
                null,
                200,
            );
        });

        it("returns 404 when removing somebody who is not in the pool", () => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                `${MINISTRIES_URL}/${ministryA}/pool/${POOL_MEMBERS[3]}`,
                null,
                404,
            );
        });
    });

    // -----------------------------------------------------------------
    // §2.7 / §3.3.1 — qualifications
    // -----------------------------------------------------------------
    describe("Qualifications", () => {
        it("grants a qualification and returns 201", () => {
            grantQualification(positionSetup, 4).then((resp) => {
                expect(resp.body).to.have.property("qualification");
                const qualification = resp.body.qualification;
                expect(qualification.personId).to.eq(4);
                expect(qualification.positionId).to.eq(positionSetup);
                expect(qualification.active).to.eq(true);
                expect(qualification.grantedDate).to.be.a("string");
                expect(qualification.grantedByPersonId).to.eq(1);
                expect(qualification.displayName).to.be.a("string").and.not.to.eq("");
            });
        });

        it("puts a newly qualified outsider into the ministry's pool (D19)", () => {
            // Person 9 is in nothing this spec has touched. Qualifying them is what
            // brings them into the roster — before D19 they would have been
            // qualified and invisible to every screen that reads the pool.
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pool`,
                null,
                200,
            ).then((before) => {
                expect(before.body.members.map((m) => m.personId)).to.not.include(
                    POOL_MEMBERS[3],
                );
            });

            grantQualification(positionEspresso, POOL_MEMBERS[3], [200, 201]);

            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pool`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.members.map((m) => m.personId)).to.include(
                    POOL_MEMBERS[3],
                );
            });
        });

        it("leaves them in the pool when the qualification is revoked (D19)", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/positions/${positionEspresso}/qualifications`,
                null,
                200,
            ).then((resp) => {
                const row = resp.body.qualifications.find(
                    (q) => q.personId === POOL_MEMBERS[3],
                );
                expect(row, "the grant above must be findable").to.exist;
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${VOLUNTEER_URL}/qualifications/${row.id}`,
                    null,
                    200,
                );
            });

            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pool`,
                null,
                200,
            ).then((resp) => {
                expect(
                    resp.body.members.map((m) => m.personId),
                    "revoking one qualification must not un-roster anybody",
                ).to.include(POOL_MEMBERS[3]);
            });
        });

        it("is idempotent: a repeat grant returns 200 and the same row", () => {
            grantQualification(positionSetup, 4, 200).then((resp) => {
                expect(resp.body.qualification.active).to.eq(true);
            });
            countQualificationRows(4, positionSetup).then((count) => {
                expect(count).to.eq(1);
            });
        });

        it("rejects an unknown person with 404 and a missing personId with 400", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/positions/${positionSetup}/qualifications`,
                { personId: 99999999 },
                404,
            );
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/positions/${positionSetup}/qualifications`,
                { notes: "no person" },
                400,
            );
        });

        it("lets one person hold several qualifications in one team (D16)", () => {
            grantQualification(positionEspresso, 4);
            grantQualification(positionExpeditor, 4);

            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/people/4/qualifications`,
                null,
                200,
            ).then((resp) => {
                const positionIds = resp.body.qualifications
                    .filter((q) => q.active)
                    .map((q) => q.positionId);
                expect(positionIds).to.include.members([
                    positionSetup,
                    positionEspresso,
                    positionExpeditor,
                ]);
            });

            dbOk(
                `SELECT COUNT(*) AS c FROM volunteer_qualification_vqal
                  WHERE vqal_per_ID = 4 AND vqal_vpos_ID IN (?, ?, ?)`,
                [positionSetup, positionEspresso, positionExpeditor],
            ).then((rows) => {
                expect(Number(rows[0].c)).to.eq(3);
            });
        });

        it("enforces UNIQUE (person, position) at the database level", () => {
            cy.dbQuery(
                `INSERT INTO volunteer_qualification_vqal
                   (vqal_per_ID, vqal_vpos_ID, vqal_Active, vqal_GrantedDate)
                 VALUES (?, ?, 1, NOW())`,
                [4, positionSetup],
            ).then((result) => {
                expect(result.error, "the duplicate insert is refused").to.not.be.null;
                expect(result.error.code).to.eq("ER_DUP_ENTRY");
            });
        });

        it("lists who is qualified for a position", () => {
            grantQualification(positionEspresso, 5);
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/positions/${positionEspresso}/qualifications`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("qualifications");
                const personIds = resp.body.qualifications.map((q) => q.personId);
                expect(personIds).to.include.members([4, 5]);
            });
        });

        it("revokes by DEACTIVATING, keeping exactly one row (§2.7)", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/positions/${positionEspresso}/qualifications`,
                null,
                200,
            ).then((resp) => {
                const row = resp.body.qualifications.find((q) => q.personId === 5);
                expect(row, "person 5 is qualified before the revoke").to.exist;

                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${VOLUNTEER_URL}/qualifications/${row.id}`,
                    null,
                    200,
                ).then((deleted) => {
                    expect(deleted.body).to.have.property("qualification");
                    expect(deleted.body.qualification.active).to.eq(false);
                    expect(deleted.body.qualification.id).to.eq(row.id);
                });

                countQualificationRows(5, positionEspresso).then((count) => {
                    expect(count, "the row survives the revoke").to.eq(1);
                });
                dbOk(
                    `SELECT vqal_Active FROM volunteer_qualification_vqal WHERE vqal_ID = ?`,
                    [row.id],
                ).then((rows) => {
                    expect(Number(rows[0].vqal_Active)).to.eq(0);
                });
            });
        });

        it("re-granting reactivates the same row rather than inserting a second", () => {
            grantQualification(positionEspresso, 5, 200).then((resp) => {
                expect(resp.body.qualification.active).to.eq(true);
            });
            countQualificationRows(5, positionEspresso).then((count) => {
                expect(count).to.eq(1);
            });
        });

        it("drops a revoked person out of the active list but keeps them readable", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/positions/${positionEspresso}/qualifications`,
                null,
                200,
            ).then((resp) => {
                const row = resp.body.qualifications.find((q) => q.personId === 5);
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${VOLUNTEER_URL}/qualifications/${row.id}`,
                    null,
                    200,
                );

                cy.makePrivateAdminAPICall(
                    "GET",
                    `${VOLUNTEER_URL}/positions/${positionEspresso}/qualifications?active=1`,
                    null,
                    200,
                ).then((active) => {
                    expect(active.body.qualifications.map((q) => q.personId)).to.not.include(5);
                });

                cy.makePrivateAdminAPICall(
                    "GET",
                    `${VOLUNTEER_URL}/positions/${positionEspresso}/qualifications`,
                    null,
                    200,
                ).then((all) => {
                    const still = all.body.qualifications.find((q) => q.personId === 5);
                    expect(still, "history survives the revoke").to.exist;
                    expect(still.active).to.eq(false);
                });

                // Put it back for the matrix test below.
                grantQualification(positionEspresso, 5, 200);
            });
        });

        it("returns 404 for a qualification that does not exist", () => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                `${VOLUNTEER_URL}/qualifications/99999999`,
                null,
                404,
            );
        });
    });

    // -----------------------------------------------------------------
    // §5.4 — the matrix payload: people × positions in ONE response
    // -----------------------------------------------------------------
    describe("Qualification matrix", () => {
        before(() => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pool/${MATRIX_POOL_ONLY}`,
                null,
                [200, 201],
            );
        });

        it("carries the positions, the union of pool and qualified people, and each person's qualification ids", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/qualification-matrix`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("positions");
                expect(resp.body).to.have.property("people");
                // D19 removed the `pools` array: there is one pool and it is the
                // ministry's own group, so there is nothing to list.
                expect(resp.body).to.not.have.property("pools");
                expect(resp.body.ministryId).to.eq(ministryA);

                const positionIds = resp.body.positions.map((p) => p.id);
                expect(positionIds).to.include.members([
                    positionSetup,
                    positionEspresso,
                    positionExpeditor,
                ]);

                // One fetch carries every cell — §5.4 forbids a request per cell.
                const tony = resp.body.people.find((p) => p.personId === 4);
                expect(tony.qualifications).to.include.members([
                    positionSetup,
                    positionEspresso,
                    positionExpeditor,
                ]);
                expect(tony.inPool, "qualifying somebody puts them in the pool").to.eq(
                    true,
                );

                // D19's union, the other half: a pool member with no ticks is a row.
                const poolOnly = resp.body.people.find(
                    (p) => p.personId === MATRIX_POOL_ONLY,
                );
                expect(poolOnly, "a pool member with no qualifications is still a row")
                    .to.exist;
                expect(poolOnly.inPool).to.eq(true);
                expect(poolOnly.qualifications).to.be.an("array").that.is.empty;
            });
        });

        it("keeps a qualified person on the matrix after they leave the pool", () => {
            // The other half of the union, and the D19 rule it exists for: removing
            // somebody from the pool does not un-qualify them, so they must stay
            // visible — and `inPool: false` is how the screen says which they are.
            cy.makePrivateAdminAPICall(
                "DELETE",
                `${MINISTRIES_URL}/${ministryA}/pool/4`,
                null,
                200,
            );
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/qualification-matrix`,
                null,
                200,
            ).then((resp) => {
                const tony = resp.body.people.find((p) => p.personId === 4);
                expect(tony, "a qualified non-member is still a row").to.exist;
                expect(tony.inPool).to.eq(false);
                expect(tony.qualifications).to.include(positionSetup);
            });
            // Put them back so the later tests see the state they expect.
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pool/4`,
                null,
                201,
            );
        });

        it("narrows to one team's positions with ?teamId=", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryB}/qualification-matrix?teamId=${teamB}`,
                null,
                200,
            ).then((resp) => {
                const positionIds = resp.body.positions.map((p) => p.id);
                expect(positionIds).to.include(positionBTeam);
                expect(positionIds).to.not.include(positionBOtherTeam);
                expect(resp.body.teamId).to.eq(teamB);
            });
        });
    });

    // -----------------------------------------------------------------
    // P5 / P6 — bulk grant from the Cart, not a second selection mechanism
    // -----------------------------------------------------------------
    describe("Bulk grant from the cart", () => {
        beforeEach(() => {
            cy.makePrivateAdminAPICall("DELETE", CART_URL, null, 200);
        });

        after(() => {
            cy.makePrivateAdminAPICall("DELETE", CART_URL, null, 200);
        });

        it("qualifies everyone in the cart for one position", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                CART_URL,
                { Persons: [8, 9, 63] },
                200,
            );

            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/positions/${positionExpeditor}/qualifications/from-cart`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.granted).to.eq(3);
                expect(resp.body.existing).to.eq(0);
            });

            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/positions/${positionExpeditor}/qualifications?active=1`,
                null,
                200,
            ).then((resp) => {
                const personIds = resp.body.qualifications.map((q) => q.personId);
                expect(personIds).to.include.members([8, 9, 63]);
            });
        });

        it("is idempotent: re-running reports the rows that already existed", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                CART_URL,
                { Persons: [8, 9, 63] },
                200,
            );
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/positions/${positionExpeditor}/qualifications/from-cart`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.granted).to.eq(0);
                expect(resp.body.existing).to.eq(3);
            });
            dbOk(
                `SELECT COUNT(*) AS c FROM volunteer_qualification_vqal
                  WHERE vqal_vpos_ID = ? AND vqal_per_ID IN (8, 9, 63)`,
                [positionExpeditor],
            ).then((rows) => {
                expect(Number(rows[0].c)).to.eq(3);
            });
        });

        it("returns 400 when the cart is empty", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/positions/${positionExpeditor}/qualifications/from-cart`,
                null,
                400,
            );
        });
    });

    // -----------------------------------------------------------------
    // §4.6 — who may link a pool and who may grant a qualification
    // -----------------------------------------------------------------
    describe("Authorization (§4.6)", () => {
        before(() => {
            grantScope("ministry", ministryA);
            grantScope("team", teamB);
        });

        it("lets the ministry coordinator add and remove pool members in their ministry", () => {
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pool/${POOL_MEMBERS[3]}`,
                null,
                [200, 201],
            );
            cy.makePrivateAPICall(
                userKey(),
                "DELETE",
                `${MINISTRIES_URL}/${ministryA}/pool/${POOL_MEMBERS[3]}`,
                null,
                200,
            );
        });

        it("denies the coordinator of ministry A every pool route on ministry B", () => {
            cy.makePrivateAPICall(
                userKey(),
                "GET",
                `${MINISTRIES_URL}/${ministryB}/pool`,
                null,
                403,
            );
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${MINISTRIES_URL}/${ministryB}/pool/${POOL_MEMBERS[0]}`,
                null,
                403,
            );
            cy.makePrivateAPICall(
                userKey(),
                "GET",
                `${MINISTRIES_URL}/${ministryB}/members`,
                null,
                403,
            );
        });

        it("denies the coordinator of ministry A the qualification routes of ministry B", () => {
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${VOLUNTEER_URL}/positions/${positionBOtherTeam}/qualifications`,
                { personId: 4 },
                403,
            );
            cy.makePrivateAPICall(
                userKey(),
                "GET",
                `${VOLUNTEER_URL}/positions/${positionBOtherTeam}/qualifications`,
                null,
                403,
            );
        });

        it("lets a team leader grant on their own team's positions only", () => {
            // Person 3 holds a TEAM scope on team B and no ministry scope on B.
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${VOLUNTEER_URL}/positions/${positionBTeam}/qualifications`,
                { personId: 4 },
                [200, 201],
            ).then((resp) => {
                cy.makePrivateAPICall(
                    userKey(),
                    "DELETE",
                    `${VOLUNTEER_URL}/qualifications/${resp.body.qualification.id}`,
                    null,
                    200,
                );
            });

            // A position in ANOTHER team of the same ministry belongs to that
            // team's leader and to the ministry's coordinator, never to this leader
            // (§4.6, canManagePosition).
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${VOLUNTEER_URL}/positions/${positionBOtherTeam}/qualifications`,
                { personId: 4 },
                403,
            );
        });

        it("denies a team leader the ministry-level pool routes", () => {
            // D19 puts the pool on the MINISTRY, so a team leader who is not the
            // ministry's coordinator has no pool route at all — §4.6's "scope"
            // for a team leader never widened to the ministry.
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${MINISTRIES_URL}/${ministryB}/pool/${POOL_MEMBERS[0]}`,
                null,
                403,
            );
            cy.makePrivateAPICall(
                userKey(),
                "GET",
                `${MINISTRIES_URL}/${ministryB}/pool`,
                null,
                403,
            );
        });

        it("denies person 900 every write on the surface", () => {
            cy.makePrivatePlainAuthAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pool/${POOL_MEMBERS[0]}`,
                null,
                403,
            );
            cy.makePrivatePlainAuthAPICall(
                "DELETE",
                `${VOLUNTEER_URL}/qualifications/1`,
                null,
                403,
            );
            cy.makePrivatePlainAuthAPICall(
                "POST",
                `${VOLUNTEER_URL}/positions/${positionSetup}/qualifications/from-cart`,
                null,
                403,
            );
        });

        it("scopes a person's qualification list to the caller's ministries", () => {
            // Person 4 is qualified in ministry A (the caller's) and nothing else
            // is visible to them from ministry B.
            cy.makePrivateAPICall(
                userKey(),
                "GET",
                `${VOLUNTEER_URL}/people/4/qualifications`,
                null,
                200,
            ).then((resp) => {
                const ministryIds = resp.body.qualifications.map((q) => q.ministryId);
                expect(ministryIds).to.not.include(ministryB);
                expect(ministryIds).to.include(ministryA);
            });
        });
    });
});
