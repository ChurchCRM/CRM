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
 * What is asserted, and where the design says so:
 *
 *   §2.5  a pool is a LINK to a group_grp row and stores no people; the link is
 *         unique per (ownerType, ownerId, groupId); unlinking never touches the
 *         group
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
 * Seed fixtures (design §6.4). The pool-union test deliberately does NOT use
 * group 10 "Worship Service" that the issue brief suggested: `seed.sql:1338`
 * gives it **no** `person2group2role_p2g2r` rows at all, so a union with it
 * could not demonstrate de-duplication. Group 1 "Angels class" (persons 4, 5, 8,
 * 9, 63) and group 8 "Girl Scouts" (persons 63, 80, 95) OVERLAP on person 63, so
 * their union is 7 distinct people where a naive concatenation would report 8 —
 * which is the property worth pinning. Group 10 is still linked once, to prove a
 * member-less pool contributes nothing and does not break the union.
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

/** seed.sql group_grp rows; the memberships are seed.sql:1338. */
const GROUP_ANGELS = 1; // persons 4, 5, 8, 9, 63
const GROUP_SCOUTS = 8; // persons 63, 80, 95 — overlaps Angels on 63
const GROUP_WORSHIP = 10; // type 1 "Ministry", zero members

/**
 * The two pool groups' memberships, read from the API in `before` rather than
 * hardcoded from the seed.
 *
 * `private.people.groups.spec.js` adds person 1 to group 1 and does not always
 * take them out again, so group 1's size is NOT stable across a full suite run.
 * What this spec is actually about is the union being de-duplicated, and that is
 * provable against whatever the groups happen to contain: person 63 is in both,
 * so `union.length < angels.length + scouts.length` always holds.
 */
let angelsMembers = [];
let scoutsMembers = [];
let unionMembers = [];

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
    dbOk(
        `DELETE p FROM volunteer_pool_vpol p
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = p.vpol_OwnerId
          WHERE p.vpol_OwnerType = 'ministry' AND m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE p FROM volunteer_pool_vpol p
           JOIN volunteer_team_vtem t ON t.vtem_ID = p.vpol_OwnerId
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = t.vtem_vmin_ID
          WHERE p.vpol_OwnerType = 'team' AND m.vmin_Name LIKE ?`,
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

/** Link a group as a pool with the admin key and hand back the pool id. */
function linkPool(ownerPath, groupId, label) {
    return cy
        .makePrivateAdminAPICall("POST", ownerPath, { groupId, label }, 201)
        .then((resp) => resp.body.pool.id);
}

function grantQualification(positionId, personId, expected = 201) {
    return cy.makePrivateAdminAPICall(
        "POST",
        `${VOLUNTEER_URL}/positions/${positionId}/qualifications`,
        { personId },
        expected,
    );
}

/** The person ids currently in a group, via the core endpoint V2 reuses (G2). */
function readGroupMembers(groupId) {
    return cy
        .makePrivateAdminAPICall("GET", `/api/groups/${groupId}/members`, null, 200)
        .then((resp) => resp.body.Person2group2roleP2g2rs.map((m) => m.PersonId));
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

        readGroupMembers(GROUP_ANGELS).then((ids) => {
            angelsMembers = ids;
        });
        readGroupMembers(GROUP_SCOUTS).then((ids) => {
            scoutsMembers = ids;
            unionMembers = [...new Set([...angelsMembers, ...scoutsMembers])];
        });

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
                url: `${MINISTRIES_URL}/${ministryA}/pools`,
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
                `${MINISTRIES_URL}/${ministryA}/pools`,
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
                `${MINISTRIES_URL}/${ministryA}/pools`,
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
    // §2.5 / §3.3.1 — linking a Group as the pool
    // -----------------------------------------------------------------
    describe("Pools", () => {
        let ministryPoolId = 0;
        let teamPoolId = 0;

        it("links a Group to a ministry and reports its member count", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pools`,
                { groupId: GROUP_ANGELS, label: "Sunday A team" },
                201,
            ).then((resp) => {
                expect(resp.body).to.have.property("pool");
                const pool = resp.body.pool;
                expect(pool.id).to.be.a("number").and.to.be.greaterThan(0);
                expect(pool.ownerType).to.eq("ministry");
                expect(pool.ownerId).to.eq(ministryA);
                expect(pool.groupId).to.eq(GROUP_ANGELS);
                expect(pool.groupName).to.eq("Angels class");
                expect(pool.label).to.eq("Sunday A team");
                // The count comes from the group's own membership rows — V2 copies
                // nothing (D1, §2.5).
                expect(pool.memberCount).to.eq(angelsMembers.length);
                ministryPoolId = pool.id;
            });
        });

        it("stores the link only — no people are copied into V2", () => {
            dbOk(
                `SELECT vpol_grp_ID FROM volunteer_pool_vpol WHERE vpol_ID = ?`,
                [ministryPoolId],
            ).then((rows) => {
                expect(rows).to.have.lengthOf(1);
                expect(Number(rows[0].vpol_grp_ID)).to.eq(GROUP_ANGELS);
            });
            // The only V2 tables that may hold person ids are qualifications and
            // assignments; a pool link must never have created one.
            dbOk(
                `SELECT COUNT(*) AS c FROM volunteer_qualification_vqal
                   JOIN volunteer_position_vpos p ON p.vpos_ID = vqal_vpos_ID
                  WHERE p.vpos_vmin_ID = ?`,
                [ministryA],
            ).then((rows) => {
                expect(Number(rows[0].c)).to.eq(0);
            });
        });

        it("rejects a second link of the same Group to the same owner with 409", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pools`,
                { groupId: GROUP_ANGELS },
                409,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", false);
            });
        });

        it("rejects an unknown group with 404", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pools`,
                { groupId: 99999999 },
                404,
            );
        });

        it("rejects a missing groupId with 400", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pools`,
                { label: "no group" },
                400,
            );
        });

        it("rejects an owner that does not exist with 404", () => {
            // vpol_OwnerId is polymorphic and carries no foreign key (§2.5), so the
            // service is the only thing that can refuse this.
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/99999999/pools`,
                { groupId: GROUP_ANGELS },
                404,
            );
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/teams/99999999/pools`,
                { groupId: GROUP_ANGELS },
                404,
            );
        });

        it("links a second Group to a team under the same ministry", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/teams/${teamA}/pools`,
                { groupId: GROUP_SCOUTS },
                201,
            ).then((resp) => {
                expect(resp.body.pool.ownerType).to.eq("team");
                expect(resp.body.pool.ownerId).to.eq(teamA);
                expect(resp.body.pool.memberCount).to.eq(scoutsMembers.length);
                teamPoolId = resp.body.pool.id;
            });
        });

        it("allows the same Group to be linked to a different owner", () => {
            // The unique key is (ownerType, ownerId, groupId), so one group may feed
            // several teams — UC3/UC4 both want that (§2.5).
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/teams/${teamA}/pools`,
                { groupId: GROUP_ANGELS },
                201,
            ).then((resp) => {
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${VOLUNTEER_URL}/pools/${resp.body.pool.id}`,
                    null,
                    200,
                );
            });
        });

        it("lists the ministry's pools, its teams' pools and where each belongs", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pools`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("pools");
                const ids = resp.body.pools.map((p) => p.id);
                expect(ids).to.include(ministryPoolId);
                expect(ids).to.include(teamPoolId);
                const teamPool = resp.body.pools.find((p) => p.id === teamPoolId);
                expect(teamPool.ownerType).to.eq("team");
                expect(teamPool.ownerName).to.eq(`${PREFIX} Coffee Bar Team`);
            });
        });

        it("lists only that team's pools under /teams/{id}/pools", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/teams/${teamA}/pools`,
                null,
                200,
            ).then((resp) => {
                const ids = resp.body.pools.map((p) => p.id);
                expect(ids).to.include(teamPoolId);
                expect(ids).to.not.include(ministryPoolId);
            });
        });

        it("returns the union of the linked groups' memberships, de-duplicated", () => {
            // Person 63 is in BOTH group 1 and group 8 (seed.sql:1338), so a naive
            // concatenation reports one person more than the union does.
            expect(
                unionMembers.length,
                "the two seed groups overlap, or this test proves nothing",
            ).to.be.lessThan(angelsMembers.length + scoutsMembers.length);

            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/members`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("members");
                const personIds = resp.body.members.map((m) => m.personId);
                expect(personIds).to.have.lengthOf(unionMembers.length);
                expect(personIds).to.include.members(unionMembers);
                const shared = resp.body.members.find((m) => m.personId === 63);
                expect(shared, "the person in both groups appears once").to.exist;
                expect(shared.groupIds).to.include.members([
                    GROUP_ANGELS,
                    GROUP_SCOUTS,
                ]);
                expect(shared.displayName).to.be.a("string").and.not.to.eq("");
            });
        });

        it("narrows the pool people to one team when ?teamId= is given", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/teams/${teamA}/members`,
                null,
                200,
            ).then((resp) => {
                const personIds = resp.body.members.map((m) => m.personId);
                // The ministry-wide pool feeds every team under it, so the team view
                // is the ministry pool ∪ the team's own pool — still de-duplicated.
                expect(personIds).to.include.members(unionMembers);
                expect(personIds).to.have.lengthOf(unionMembers.length);
            });
        });

        it("treats a member-less pool group as contributing nobody", () => {
            // Group 10 "Worship Service" has no person2group2role_p2g2r rows at all.
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pools`,
                { groupId: GROUP_WORSHIP },
                201,
            ).then((resp) => {
                expect(resp.body.pool.memberCount).to.eq(0);
                cy.makePrivateAdminAPICall(
                    "GET",
                    `${MINISTRIES_URL}/${ministryA}/members`,
                    null,
                    200,
                ).then((members) => {
                    expect(members.body.members).to.have.lengthOf(
                        unionMembers.length,
                    );
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${VOLUNTEER_URL}/pools/${resp.body.pool.id}`,
                    null,
                    200,
                );
            });
        });

        it("unlinks a pool without touching the Group or its members", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryB}/pools`,
                { groupId: GROUP_ANGELS },
                201,
            ).then((resp) => {
                const poolId = resp.body.pool.id;
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${VOLUNTEER_URL}/pools/${poolId}`,
                    null,
                    200,
                );
                dbOk(`SELECT COUNT(*) AS c FROM volunteer_pool_vpol WHERE vpol_ID = ?`, [
                    poolId,
                ]).then((rows) => {
                    expect(Number(rows[0].c)).to.eq(0);
                });
            });
            // The group is untouched: same membership as the seed.
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${GROUP_ANGELS}/members`,
                null,
                200,
            ).then((resp) => {
                const memberIds = resp.body.Person2group2roleP2g2rs.map(
                    (m) => m.PersonId,
                );
                expect(memberIds).to.include.members(angelsMembers);
            });
        });

        it("returns 404 for a pool that does not exist", () => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                `${VOLUNTEER_URL}/pools/99999999`,
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
        it("carries the positions, the pool people and each person's qualification ids", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/qualification-matrix`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("positions");
                expect(resp.body).to.have.property("people");
                expect(resp.body).to.have.property("pools");
                expect(resp.body.ministryId).to.eq(ministryA);

                const positionIds = resp.body.positions.map((p) => p.id);
                expect(positionIds).to.include.members([
                    positionSetup,
                    positionEspresso,
                    positionExpeditor,
                ]);

                const personIds = resp.body.people.map((p) => p.personId);
                expect(personIds).to.include.members(unionMembers);

                // One fetch carries every cell — §5.4 forbids a request per cell.
                const tony = resp.body.people.find((p) => p.personId === 4);
                expect(tony.qualifications).to.include.members([
                    positionSetup,
                    positionEspresso,
                    positionExpeditor,
                ]);
                const notQualified = resp.body.people.find((p) => p.personId === 80);
                expect(notQualified.qualifications).to.be.an("array").that.is.empty;
            });
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

        it("returns an empty people list when no pool is linked", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryB}/qualification-matrix`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.pools).to.be.an("array").that.is.empty;
                expect(resp.body.people).to.be.an("array").that.is.empty;
                expect(resp.body.positions).to.be.an("array").that.is.not.empty;
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

        it("lets the ministry coordinator link and unlink pools in their ministry", () => {
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${VOLUNTEER_URL}/teams/${teamA}/pools`,
                { groupId: GROUP_WORSHIP },
                201,
            ).then((resp) => {
                cy.makePrivateAPICall(
                    userKey(),
                    "DELETE",
                    `${VOLUNTEER_URL}/pools/${resp.body.pool.id}`,
                    null,
                    200,
                );
            });
        });

        it("denies the coordinator of ministry A every pool route on ministry B", () => {
            cy.makePrivateAPICall(
                userKey(),
                "GET",
                `${MINISTRIES_URL}/${ministryB}/pools`,
                null,
                403,
            );
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${MINISTRIES_URL}/${ministryB}/pools`,
                { groupId: GROUP_ANGELS },
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

        it("lets a team leader link a pool on their own team but not on the ministry", () => {
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${VOLUNTEER_URL}/teams/${teamB}/pools`,
                { groupId: GROUP_SCOUTS },
                201,
            ).then((resp) => {
                cy.makePrivateAPICall(
                    userKey(),
                    "DELETE",
                    `${VOLUNTEER_URL}/pools/${resp.body.pool.id}`,
                    null,
                    200,
                );
            });
            cy.makePrivateAPICall(
                userKey(),
                "POST",
                `${MINISTRIES_URL}/${ministryB}/pools`,
                { groupId: GROUP_SCOUTS },
                403,
            );
        });

        it("denies person 900 every write on the surface", () => {
            cy.makePrivatePlainAuthAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pools`,
                { groupId: GROUP_SCOUTS },
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
