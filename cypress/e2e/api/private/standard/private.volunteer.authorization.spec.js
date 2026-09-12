/// <reference types="cypress" />

/**
 * Volunteer v2 — scoped authorization (#9706, epic #9701).
 *
 * Covers every row of design §4.8 that #9706 actually ships (the rows that
 * depend on assignments, occurrences, swaps and self-signup belong to
 * #9708/#9709/#9712 and are asserted in those issues' specs), plus:
 *
 *   - the scope CRUD surface  `/api/volunteer/scopes`
 *   - the idempotent grant    (§6.6: second POST is 200 with the SAME id, never 409)
 *   - the `usr_VolunteerManager` round trip through the user-administration
 *     surface (`/admin/system/users/{personId}/edit`), proving the new column
 *     drives `User::isVolunteerManagerEnabled()` and therefore the middleware
 *   - the §4.7 member-path exemption on the API-key branch of AuthMiddleware
 *
 * Tiers exercised (design §6.4 fixture table):
 *   person 1   `admin.api.key`      Administrator — bypasses every role gate
 *   person 3   `user.api.key`       every flag but Admin → Ministry Coordinator once granted a scope
 *   person 900 `plainauth.api.key`  Notes only → the "no volunteer rights" negative case,
 *                                   and the Global Volunteer Manager for the round-trip block
 *   person 99  `selfedit.api.key`   EditSelf-exclusive → the volunteer persona (D14)
 *
 * §6.6 rule honoured throughout: **never assert a strict 403 on an admin-keyed
 * call** to a role-gated route — an administrator bypasses every role middleware
 * except AdminRoleAuthMiddleware.
 *
 * Two error shapes are in play (design §4.9, still true because E-18/#9737 is
 * open): BaseAuthRoleMiddleware denials are `{"error","code"}`; everything from
 * SlimUtils::renderErrorJSON() is `{"success":false,"message":…}`. Assertions
 * below name the layer that denied, and never assert on wording — the
 * renderErrorJSON redaction regex still swallows innocuous English words.
 *
 * Fixture rows for ministries and teams go in through `cy.dbQuery()` because
 * there is no setup API yet (#9715 adds it). Scopes go in through the new API,
 * which is the point of the spec. Cleanup runs in `before` AND `after`
 * (cypress-testing.md: an `after` hook does not run when the runner crashes).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const SCOPES_URL = "/api/volunteer/scopes";
const ME_PERMISSIONS_URL = "/api/volunteer/me/permissions";
const DASHBOARD_URL = "/volunteer/dashboard";

const PERSON_COORDINATOR = 3; // tony.wade — user.api.key
const PERSON_MANAGER = 900; // john.plainauth — plainauth.api.key
const PERSON_VOLUNTEER = 99; // amanda.black — selfedit.api.key

// Fixture names are prefixed so cleanup can delete exactly what this spec made.
const FIXTURE_PREFIX = "AUTHZ9706";

let ministryA = 0;
let ministryB = 0;
let teamA1 = 0;
let teamB1 = 0;

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
 * Delete every row this spec could have created, children first so the FKs
 * never block. Scope rows carry no FK to ministry/team (the column is
 * polymorphic, §2.15), so they are removed by person id as well as by target.
 */
function cleanupFixtures() {
    dbOk(
        `DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID IN (?, ?, ?)`,
        [PERSON_COORDINATOR, PERSON_MANAGER, PERSON_VOLUNTEER],
    );
    dbOk(`DELETE FROM volunteer_team_vtem WHERE vtem_Name LIKE ?`, [
        `${FIXTURE_PREFIX}%`,
    ]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [
        `${FIXTURE_PREFIX}%`,
    ]);
}

function createMinistry(suffix) {
    return dbOk(
        `INSERT INTO volunteer_ministry_vmin (vmin_Name, vmin_Description, vmin_Active, vmin_CreatedDate)
         VALUES (?, 'volunteer v2 authorization fixture', 1, NOW())`,
        [`${FIXTURE_PREFIX} ${suffix}`],
    ).then((rows) => rows.insertId);
}

function createTeam(ministryId, suffix) {
    return dbOk(
        `INSERT INTO volunteer_team_vtem (vtem_vmin_ID, vtem_Name, vtem_Description, vtem_Active)
         VALUES (?, ?, 'volunteer v2 authorization fixture', 1)`,
        [ministryId, `${FIXTURE_PREFIX} ${suffix}`],
    ).then((rows) => rows.insertId);
}

/**
 * A page request against an MVC module, authenticated by API key.
 * x-api-key authenticates Slim MVC pages such as /volunteer (cypress-testing.md);
 * redirects are not followed so the 302 target can be asserted.
 */
function pageRequest(url, apiKey) {
    return cy.request({
        method: "GET",
        url,
        headers: { "x-api-key": apiKey },
        failOnStatusCode: false,
        followRedirect: false,
        withCredentials: false,
    });
}

/**
 * POST the admin user editor as a form, authenticated by API key.
 * CSRFMiddleware skips validation when X-API-Key is present
 * (CSRFMiddleware.php:40-44), so no token round trip is needed.
 * `form: true` is required: the editor reads $request->getParsedBody().
 */
function postUserEditor(personId, fields) {
    return cy.request({
        method: "POST",
        url: `/admin/system/users/${personId}/edit`,
        headers: { "x-api-key": Cypress.env("admin.api.key") },
        form: true,
        body: fields,
        failOnStatusCode: false,
        followRedirect: false,
        withCredentials: false,
    });
}

function getUserEditor(personId) {
    return pageRequest(
        `/admin/system/users/${personId}/edit`,
        Cypress.env("admin.api.key"),
    );
}

/** The plainauth user's stored login — the editor rejects a short/duplicate name. */
const MANAGER_USERNAME = "john.plainauth@example.com";

/** Restore person 900 to its seeded shape: Notes only, no volunteer manager. */
function restoreManagerUser() {
    postUserEditor(PERSON_MANAGER, {
        UserName: MANAGER_USERNAME,
        accessMode: "custom",
        Notes: "1",
    });
}

describe("Volunteer v2 scoped authorization (#9706)", () => {
    before(() => {
        // Cleanup-before: an earlier crashed run may have left rows or v2 set.
        setVersion("v2");
        cleanupFixtures();
        restoreManagerUser();
        createMinistry("Ministry A").then((id) => {
            ministryA = id;
            createTeam(ministryA, "Team A1").then((tid) => {
                teamA1 = tid;
            });
        });
        createMinistry("Ministry B").then((id) => {
            ministryB = id;
            createTeam(ministryB, "Team B1").then((tid) => {
                teamB1 = tid;
            });
        });
    });

    after(() => {
        cleanupFixtures();
        restoreManagerUser();
        setVersion("v1");
    });

    // ---------------------------------------------------------------------
    // §4.8 — authentication and the rollout gate
    // ---------------------------------------------------------------------
    describe("Authentication and rollout gates", () => {
        it("returns 401 for an unauthenticated call to the scope API", () => {
            cy.request({
                method: "GET",
                url: SCOPES_URL,
                failOnStatusCode: false,
                withCredentials: false,
            }).then((resp) => {
                expect(resp.status).to.eq(401);
                expect(resp.body).to.have.property("code", 401);
            });
        });

        it("returns 403 for every /api/volunteer call while the rollout state is v1", () => {
            setVersion("v1");
            // Admin is used deliberately here: this 403 comes from
            // VolunteerV2EnabledMiddleware, which an administrator does NOT bypass,
            // so it is not the forbidden "strict 403 on an admin-keyed role gate".
            cy.makePrivateAdminAPICall("GET", SCOPES_URL, null, 403).then((resp) => {
                expect(resp.body).to.have.property("success", false);
            });
            cy.makePrivateAdminAPICall("GET", ME_PERMISSIONS_URL, null, 403);
            setVersion("v2");
        });
    });

    // ---------------------------------------------------------------------
    // §4.8 — the coarse role gate
    // ---------------------------------------------------------------------
    describe("Role gate on the manager-only scope API", () => {
        it("denies a user with no manager flag and no scope", () => {
            cy.makePrivatePlainAuthAPICall("GET", SCOPES_URL, null, 403).then(
                (resp) => {
                    // BaseAuthRoleMiddleware still emits its own shape (E-18/#9737 open).
                    expect(resp.body).to.have.property("code", 403);
                },
            );
        });

        it("denies a standard user with every flag but Admin", () => {
            cy.makePrivateAPICall(
                Cypress.env("user.api.key"),
                "GET",
                SCOPES_URL,
                null,
                403,
            );
        });

        it("serves an administrator", () => {
            cy.makePrivateAdminAPICall("GET", SCOPES_URL, null, 200).then((resp) => {
                expect(resp.body).to.have.property("scopes");
                expect(resp.body.scopes).to.be.an("array");
            });
        });
    });

    // ---------------------------------------------------------------------
    // usr_VolunteerManager — the new column, end to end
    // ---------------------------------------------------------------------
    describe("usr_VolunteerManager round trip through the user editor", () => {
        afterEach(() => {
            restoreManagerUser();
        });

        it("renders an unchecked Volunteer Manager checkbox for a user without it", () => {
            getUserEditor(PERSON_MANAGER).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body).to.include('name="VolunteerManager"');
                const field = resp.body.match(
                    /<input[^>]*name="VolunteerManager"[^>]*>/,
                );
                expect(field, "the VolunteerManager input").to.not.eq(null);
                expect(field[0]).to.not.include("checked");
            });
        });

        it("grants and revokes global manager authority through the editor", () => {
            // Before: denied by the role middleware.
            cy.makePrivatePlainAuthAPICall("GET", SCOPES_URL, null, 403);

            postUserEditor(PERSON_MANAGER, {
                UserName: MANAGER_USERNAME,
                accessMode: "custom",
                Notes: "1",
                VolunteerManager: "1",
            }).then((resp) => {
                expect(resp.status).to.be.oneOf([200, 302]);
            });

            // The editor reads the stored column back as checked.
            getUserEditor(PERSON_MANAGER).then((resp) => {
                const field = resp.body.match(
                    /<input[^>]*name="VolunteerManager"[^>]*>/,
                );
                expect(field[0]).to.include("checked");
            });

            // And the role middleware now lets the same key through.
            cy.makePrivatePlainAuthAPICall("GET", SCOPES_URL, null, 200);
            cy.makePrivatePlainAuthAPICall(
                "GET",
                ME_PERMISSIONS_URL,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("isGlobalManager", true);
                expect(resp.body).to.have.property("isAdmin", false);
            });

            // Revoke: the same form without the checkbox.
            restoreManagerUser();
            cy.makePrivatePlainAuthAPICall("GET", SCOPES_URL, null, 403);
        });

        it("clears the manager flag when the account is switched to EditSelf mode", () => {
            postUserEditor(PERSON_MANAGER, {
                UserName: MANAGER_USERNAME,
                accessMode: "custom",
                Notes: "1",
                VolunteerManager: "1",
            });
            cy.makePrivatePlainAuthAPICall("GET", SCOPES_URL, null, 200);

            // accessMode 'self' is exclusive: extractModulePerms() zeroes every
            // module permission, VolunteerManager included (#9079).
            postUserEditor(PERSON_MANAGER, {
                UserName: MANAGER_USERNAME,
                accessMode: "self",
                VolunteerManager: "1",
            });
            cy.dbQuery(
                "SELECT usr_VolunteerManager AS flag FROM user_usr WHERE usr_per_ID = ?",
                [PERSON_MANAGER],
            ).then((result) => {
                expect(result.error).to.eq(null);
                expect(Number(result.rows[0].flag)).to.eq(0);
            });
        });
    });

    // ---------------------------------------------------------------------
    // Scope CRUD
    // ---------------------------------------------------------------------
    describe("Scope CRUD", () => {
        beforeEach(() => {
            dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID IN (?, ?, ?)`, [
                PERSON_COORDINATOR,
                PERSON_MANAGER,
                PERSON_VOLUNTEER,
            ]);
        });

        it("creates a ministry scope and echoes the stored row", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_COORDINATOR,
                    scopeType: "ministry",
                    scopeId: ministryA,
                },
                201,
            ).then((resp) => {
                expect(resp.body).to.have.property("scope");
                expect(resp.body.scope).to.include({
                    personId: PERSON_COORDINATOR,
                    scopeType: "ministry",
                    scopeId: ministryA,
                });
                expect(resp.body.scope.id).to.be.a("number").and.be.greaterThan(0);
            });
        });

        it("is idempotent — the second identical grant returns 200 with the same id and no second row", () => {
            let firstId = 0;
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_COORDINATOR,
                    scopeType: "ministry",
                    scopeId: ministryA,
                },
                201,
            ).then((resp) => {
                firstId = resp.body.scope.id;
            });

            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_COORDINATOR,
                    scopeType: "ministry",
                    scopeId: ministryA,
                },
                200,
            ).then((resp) => {
                expect(resp.body.scope.id).to.eq(firstId);
            });

            cy.makePrivateAdminAPICall(
                "GET",
                `${SCOPES_URL}?personId=${PERSON_COORDINATOR}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.scopes).to.have.length(1);
            });
        });

        it("filters the listing by ministry, team and person", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_COORDINATOR,
                    scopeType: "ministry",
                    scopeId: ministryA,
                },
                201,
            );
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_MANAGER,
                    scopeType: "team",
                    scopeId: teamB1,
                },
                201,
            );

            cy.makePrivateAdminAPICall(
                "GET",
                `${SCOPES_URL}?ministryId=${ministryA}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.scopes).to.have.length(1);
                expect(resp.body.scopes[0].personId).to.eq(PERSON_COORDINATOR);
            });

            cy.makePrivateAdminAPICall(
                "GET",
                `${SCOPES_URL}?ministryId=${ministryB}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.scopes).to.have.length(0);
            });

            cy.makePrivateAdminAPICall(
                "GET",
                `${SCOPES_URL}?teamId=${teamB1}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.scopes).to.have.length(1);
                expect(resp.body.scopes[0].personId).to.eq(PERSON_MANAGER);
            });

            cy.makePrivateAdminAPICall(
                "GET",
                `${SCOPES_URL}?personId=${PERSON_MANAGER}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.scopes).to.have.length(1);
                expect(resp.body.scopes[0].scopeType).to.eq("team");
            });
        });

        it("rejects an unknown scope type with 400 from the sanitizer", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_COORDINATOR,
                    scopeType: "diocese",
                    scopeId: ministryA,
                },
                400,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", false);
            });
        });

        it("rejects a missing personId with 400", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                { scopeType: "ministry", scopeId: ministryA },
                400,
            );
        });

        it("rejects a scope target that does not exist with 404 (the column is polymorphic, so nothing else would)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_COORDINATOR,
                    scopeType: "ministry",
                    scopeId: 99999999,
                },
                404,
            );
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_COORDINATOR,
                    scopeType: "team",
                    scopeId: 99999999,
                },
                404,
            );
        });

        it("rejects a grant to a person who does not exist with 404", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: 99999999,
                    scopeType: "ministry",
                    scopeId: ministryA,
                },
                404,
            );
        });

        it("revokes a scope and 404s on a second revoke", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_COORDINATOR,
                    scopeType: "ministry",
                    scopeId: ministryA,
                },
                201,
            ).then((resp) => {
                const scopeId = resp.body.scope.id;
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${SCOPES_URL}/${scopeId}`,
                    null,
                    200,
                );
                cy.makePrivateAdminAPICall(
                    "GET",
                    `${SCOPES_URL}?personId=${PERSON_COORDINATOR}`,
                    null,
                    200,
                ).then((listing) => {
                    expect(listing.body.scopes).to.have.length(0);
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${SCOPES_URL}/${scopeId}`,
                    null,
                    404,
                );
            });
        });
    });

    // ---------------------------------------------------------------------
    // Tiers: coordinator and team leader
    // ---------------------------------------------------------------------
    describe("Coordinator and team-leader tiers", () => {
        beforeEach(() => {
            dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID IN (?, ?, ?)`, [
                PERSON_COORDINATOR,
                PERSON_MANAGER,
                PERSON_VOLUNTEER,
            ]);
        });

        it("reports an empty permission set for a user with no volunteer authority", () => {
            cy.makePrivatePlainAuthAPICall(
                "GET",
                ME_PERMISSIONS_URL,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.deep.eq({
                    isAdmin: false,
                    isGlobalManager: false,
                    managedMinistryIds: [],
                    managedTeamIds: [],
                });
            });
        });

        it("gives a ministry coordinator the ministry and every team under it", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_COORDINATOR,
                    scopeType: "ministry",
                    scopeId: ministryA,
                },
                201,
            );

            cy.makePrivateAPICall(
                Cypress.env("user.api.key"),
                "GET",
                ME_PERMISSIONS_URL,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.isAdmin).to.eq(false);
                expect(resp.body.isGlobalManager).to.eq(false);
                expect(resp.body.managedMinistryIds).to.deep.eq([ministryA]);
                // §4.4: own team scopes ∪ every team under a managed ministry.
                expect(resp.body.managedTeamIds).to.include(teamA1);
                expect(resp.body.managedTeamIds).to.not.include(teamB1);
            });
        });

        it("keeps the manager-only scope API closed to a coordinator", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_COORDINATOR,
                    scopeType: "ministry",
                    scopeId: ministryA,
                },
                201,
            );
            cy.makePrivateAPICall(
                Cypress.env("user.api.key"),
                "GET",
                SCOPES_URL,
                null,
                403,
            );
        });

        it("gives a team leader exactly one team and no ministry", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_MANAGER,
                    scopeType: "team",
                    scopeId: teamB1,
                },
                201,
            );
            cy.makePrivatePlainAuthAPICall(
                "GET",
                ME_PERMISSIONS_URL,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.managedMinistryIds).to.deep.eq([]);
                expect(resp.body.managedTeamIds).to.deep.eq([teamB1]);
            });
        });

        it("reports full authority for an administrator without any scope row", () => {
            cy.makePrivateAdminAPICall("GET", ME_PERMISSIONS_URL, null, 200).then(
                (resp) => {
                    expect(resp.body.isAdmin).to.eq(true);
                    expect(resp.body.isGlobalManager).to.eq(true);
                },
            );
        });
    });

    // ---------------------------------------------------------------------
    // The coordinator MVC gate (replaces #9704's AdminRoleAuthMiddleware)
    // ---------------------------------------------------------------------
    describe("Coordinator gate on the /volunteer module", () => {
        beforeEach(() => {
            dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID IN (?, ?, ?)`, [
                PERSON_COORDINATOR,
                PERSON_MANAGER,
                PERSON_VOLUNTEER,
            ]);
        });

        it("serves the dashboard to an administrator", () => {
            pageRequest(DASHBOARD_URL, Cypress.env("admin.api.key")).then((resp) => {
                expect(resp.status).to.eq(200);
            });
        });

        it("denies the dashboard to a user with no scope", () => {
            pageRequest(DASHBOARD_URL, Cypress.env("user.api.key")).then((resp) => {
                expect(resp.status).to.be.oneOf([302, 403]);
                if (resp.status === 302) {
                    expect(resp.headers.location).to.include(
                        "role=VolunteerCoordinator",
                    );
                }
            });
        });

        it("serves the dashboard once the same user is granted a ministry scope", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_COORDINATOR,
                    scopeType: "ministry",
                    scopeId: ministryA,
                },
                201,
            );
            pageRequest(DASHBOARD_URL, Cypress.env("user.api.key")).then((resp) => {
                expect(resp.status).to.eq(200);
            });
        });

        it("serves the dashboard to a team leader", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_MANAGER,
                    scopeType: "team",
                    scopeId: teamB1,
                },
                201,
            );
            pageRequest(DASHBOARD_URL, Cypress.env("plainauth.api.key")).then(
                (resp) => {
                    expect(resp.status).to.eq(200);
                },
            );
        });
    });

    // ---------------------------------------------------------------------
    // §4.7 — the EditSelf-exclusive member-path exemption
    // ---------------------------------------------------------------------
    describe("EditSelf-exclusive volunteer (§4.7 member-path exemption)", () => {
        it("reaches the member permission endpoint", () => {
            cy.makePrivateEditSelfAPICall(
                "GET",
                ME_PERMISSIONS_URL,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.deep.eq({
                    isAdmin: false,
                    isGlobalManager: false,
                    managedMinistryIds: [],
                    managedTeamIds: [],
                });
            });
        });

        it("still cannot reach the coordinator/manager scope API", () => {
            cy.makePrivateEditSelfAPICall("GET", SCOPES_URL, null, 403);
        });

        it("still cannot reach a non-volunteer internal API it was blocked from before", () => {
            // Own person record — 403 before #9706 and 403 after: the exemption
            // grants reachability of the enumerated member paths only.
            cy.makePrivateEditSelfAPICall(
                "GET",
                `/api/person/${PERSON_VOLUNTEER}`,
                null,
                403,
            );
            cy.makePrivateEditSelfAPICall("GET", "/api/family/20", null, 403);
        });

        it("is redirected away from the coordinator dashboard", () => {
            pageRequest(DASHBOARD_URL, Cypress.env("selfedit.api.key")).then(
                (resp) => {
                    expect(resp.status).to.be.oneOf([302, 403]);
                },
            );
        });

        it("loses the member exemption when the rollout state leaves v2", () => {
            setVersion("v1");
            cy.makePrivateEditSelfAPICall("GET", ME_PERMISSIONS_URL, null, 403);
            setVersion("v2");
            cy.makePrivateEditSelfAPICall("GET", ME_PERMISSIONS_URL, null, 200);
        });
    });
});
