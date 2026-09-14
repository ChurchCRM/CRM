/// <reference types="cypress" />

describe("API Private Group Operations", () => {
    let groupID = 1; // Use existing group ID for testing

    describe("Group Member Operations", () => {
        // Person 1 is not a seeded member of group 1. The tests below add them,
        // so every membership this describe creates has to be removed again —
        // otherwise group 1 keeps an extra member for the rest of the run and
        // any later spec that asserts its roster, size or email export becomes
        // order-dependent (issue #9828).
        const testPersonId = 1;

        // Group 1's roles come from list_lst id 13, seeded with Teacher
        // (OptionId 1) and Student (OptionId 2) — see cypress/data/seed.sql.
        // Two distinct seeded roles let the role-update test assert the value
        // actually changed instead of re-setting the role it started with.
        const initialRoleId = 1;
        const updatedRoleId = 2;

        let seededMemberIds;

        const sortIds = (ids) => [...ids].sort((a, b) => a - b);

        // removeperson walks the group's memberships and deletes the matching
        // one; a person who is not a member is a no-op that still returns 200
        // with {"success": true}. 200 is therefore the only status it returns —
        // no defensive extra codes (cypress-testing.md → allowedStatuses).
        const removeTestPerson = () =>
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/groups/${groupID}/removeperson/${testPersonId}`,
                null,
                [200]
            );

        const addTestPerson = (roleID) =>
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/addperson/${testPersonId}`,
                {
                    RoleID: roleID,
                },
                200
            );

        const getMembers = () =>
            cy
                .makePrivateAdminAPICall(
                    "GET",
                    `/api/groups/${groupID}/members`,
                    null,
                    200
                )
                .then((resp) => resp.body.Person2group2roleP2g2rs);

        const getMemberIds = () =>
            getMembers().then((members) =>
                members.map((member) => member.PersonId)
            );

        const expectTestPersonAbsent = () =>
            getMemberIds().then((ids) => {
                expect(ids, "group members after cleanup").to.not.include(
                    testPersonId
                );
            });

        before(() => {
            // Drop a stale membership a previously killed run may have left
            // behind, so the baseline is the seeded roster and not the seeded
            // roster + 1.
            removeTestPerson();
            getMemberIds().then((ids) => {
                seededMemberIds = sortIds(ids);
            });
        });

        beforeEach(() => {
            // Safety net only. The per-test restore lives in afterEach; this
            // covers the one case no hook in the failing run can cover — a run
            // that was killed outright (process termination skips every hook,
            // afterEach and beforeEach alike), leaving person 1 in the group
            // for the *next* run to find.
            removeTestPerson();
        });

        afterEach(() => {
            // Per-test restore. afterEach DOES run after an ordinary mid-test
            // failure — including a synchronous assertion failure, which is
            // why cleanup belongs here and not queued inside the failing
            // callback: Cypress's command queue abandons commands still
            // pending in a callback that threw, but it still runs the hooks.
            // Each test therefore puts group 1 back as it found it instead of
            // relying on the next test's beforeEach.
            removeTestPerson();
            expectTestPersonAbsent();
        });

        after(() => {
            // Guard against the leak coming back: compare the exact id set the
            // describe started with, not just its size, so a swapped or
            // replaced membership is caught too. Assert only, never clean up
            // here — a cleanup would make this assertion pass unconditionally.
            getMemberIds().then((ids) => {
                expect(
                    sortIds(ids),
                    "group roster at end of describe"
                ).to.deep.equal(seededMemberIds);
            });
        });

        it("Add member to group and verify response structure", () => {
            // Test adding a person to a group
            // GET /api/groups/1/members to ensure proper structure
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${groupID}/members`,
                null,
                200
            ).then((resp) => {
                // Response should be an object with Person2group2roleP2g2rs array
                expect(resp.body).to.have.property("Person2group2roleP2g2rs");
                expect(resp.body.Person2group2roleP2g2rs).to.be.an("array");
                // Each member should have these properties
                if (resp.body.Person2group2roleP2g2rs.length > 0) {
                    const member = resp.body.Person2group2roleP2g2rs[0];
                    expect(member).to.have.property("GroupId");
                    expect(member).to.have.property("PersonId");
                }
            });
        });

        it("Add member to group via POST addperson", () => {
            // Test adding a person to a group (person ID 1)
            addTestPerson(initialRoleId).then((resp) => {
                expect(resp.body).to.be.an("array");
            });

            // The POST status is only half the contract: read the roster back
            // and assert the membership really exists with the role requested.
            getMembers().then((members) => {
                const membership = members.find(
                    (member) => member.PersonId === testPersonId
                );
                expect(membership, `person ${testPersonId} membership`).to.exist;
                expect(membership.RoleId).to.equal(initialRoleId);
            });
        });

        it("Remove member from group", () => {
            // Seed the membership this test removes. Person 1 is not a seeded
            // member of group 1, so without this the DELETE below would be a
            // no-op that passes whether or not removal works.
            addTestPerson(initialRoleId);

            // Test removing a person from a group
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/groups/${groupID}/removeperson/${testPersonId}`,
                null,
                200
            );

            getMemberIds().then((ids) => {
                expect(ids, "group members after removal").to.not.include(
                    testPersonId
                );
            });
        });

        it("Update member role in group", () => {
            // First ensure member exists, with the role the update moves away
            // from so the assertion below proves the change took effect.
            addTestPerson(initialRoleId);

            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/userRole/${testPersonId}`,
                {
                    roleID: updatedRoleId,
                },
                200
            ).then((resp) => {
                expect(resp.body).to.exist;
                expect(resp.body).to.have.property("RoleId");
                expect(resp.body.RoleId).to.equal(updatedRoleId);
            });

            // ...and that the stored membership, not just the response, moved.
            getMembers().then((members) => {
                const membership = members.find(
                    (member) => member.PersonId === testPersonId
                );
                expect(membership, `person ${testPersonId} membership`).to.exist;
                expect(membership.RoleId).to.equal(updatedRoleId);
            });
        });
    });

    describe("Group Role Operations", () => {
        it("Get group roles", () => {
            // Test retrieving available roles for a group
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${groupID}/roles`,
                null,
                200
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
                // Each role should have properties like OptionId, OptionName
                if (resp.body.length > 0) {
                    const role = resp.body[0];
                    expect(role).to.have.property("OptionId");
                    expect(role).to.have.property("OptionName");
                }
            });
        });

        it("Add new role to group", () => {
            const roleNameUnique = "TestRole" + Date.now();
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/roles`,
                {
                    roleName: roleNameUnique,
                },
                200
            ).then((resp) => {
                expect(resp.body).to.exist;
                expect(resp.body).to.have.property("newRole");
            });
        });

        it("Update group role name", () => {
            // Test updating a role name in a group
            const newRoleName = "UpdatedRole" + Date.now();
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/roles/1`,
                {
                    groupRoleName: newRoleName,
                },
                200
            );
        });

        it("Update group role order", () => {
            // Test setting role sequence/order
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/roles/1`,
                {
                    groupRoleOrder: "1",
                },
                200
            );
        });

        it("Delete group role", () => {
            // Create a temporary role first so we have a real ID to delete.
            // Deleting a non-existent role ID against a single-role group throws
            // "only group" guard → 500. Create-then-delete is the correct pattern.
            const tempRoleName = "TempRoleToDelete" + Date.now();
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/roles`,
                { roleName: tempRoleName },
                200
            ).then((resp) => {
                const roleId = resp.body.newRole.roleID;
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/groups/${groupID}/roles/${roleId}`,
                    null,
                    200
                );
            });
        });
    });

    describe("Group Properties Operations", () => {
        it("Toggle group-specific properties status", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/setGroupSpecificPropertyStatus`,
                {
                    GroupSpecificPropertyStatus: true,
                },
                200
            ).then((resp) => {
                expect(resp.body).to.exist;
                expect(resp.body).to.have.property("status");
            });
        });

        it("Set default role for group", () => {
            // Test setting the default role for a group
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/defaultRole`,
                {
                    roleID: 1,
                },
                200
            );
        });

        it("Toggle group active status", () => {
            // Test enabling/disabling group active status
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/settings/active/true`,
                null,
                200
            );
        });

        it("Toggle group email export status", () => {
            // Test enabling/disabling group in email export
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/settings/email/export/false`,
                null,
                200
            );
        });
    });

    describe("Middleware Validation Tests", () => {
        it("Returns 404 when updating a non-existent group", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/999999`,
                { groupName: "Ghost Group", groupType: 0, description: "" },
                404
            );
        });

        it("Returns 404 when deleting a non-existent group", () => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/groups/999999`,
                null,
                404
            );
        });

        it("Returns 404 when adding a person to a non-existent group", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/999999/addperson/1`,
                { RoleID: 1 },
                404
            );
        });

        it("Sanitizes XSS in groupName when creating a group", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/`,
                {
                    groupName: "<script>alert('xss')</script>TestGroup",
                    description: "safe description",
                },
                200
            ).then((resp) => {
                expect(resp.body).to.have.property("Name");
                expect(resp.body.Name).to.not.include("<script>");
            });
        });

        it("Sanitizes XSS in groupName when updating a group", () => {
            // Create a temporary group to avoid mutating seed data (group 1 is used by
            // Sunday School tests which rely on its name and type remaining unchanged).
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/`,
                { groupName: "XSSTestGroup", description: "" },
                200
            ).then((createResp) => {
                const tempGroupId = createResp.body.Id;

                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/groups/${tempGroupId}`,
                    {
                        groupName: "<img src=x onerror=alert(1)>CleanName",
                        groupType: 0,
                        description: "",
                    },
                    200
                ).then((resp) => {
                    expect(resp.body).to.have.property("Name");
                    expect(resp.body.Name).to.not.include("onerror");

                    // Clean up the temporary group
                    cy.makePrivateAdminAPICall("DELETE", `/api/groups/${tempGroupId}`, null, 200);
                });
            });
        });

        it("Returns 404 when updating role for non-member", () => {
            // Person 999999 is not a member of group 1
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/userRole/999999`,
                { roleID: 1 },
                404,
            );
        });

        it("Returns 404 when updating non-existent role", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/roles/999999`,
                { groupRoleName: "Ghost Role" },
                404,
            );
        });

        it("Sanitizes XSS in role name when updating group role", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/roles/1`,
                { groupRoleName: "<b>Bold</b>RoleName" },
                200
            ).then((resp) => {
                expect(resp.body).to.have.property("OptionName");
                expect(resp.body.OptionName).to.not.include("<b>");
                expect(resp.body.OptionName).to.include("RoleName");
            });
        });
    });

    describe("Authorization Tests - Non-Admin Users", () => {
        it("Non-admin should be denied adding group members", () => {
            // Test that a user without bManageGroups permission is denied
            cy.makePrivateUserAPICall(
                "POST",
                `/api/groups/${groupID}/addperson/1`,
                {
                    RoleID: 1,
                },
                [401, 403, 500]
            );
        });

        it("Non-admin should be denied removing group members", () => {
            // user.api.key (tony.wade, id 3) has usr_ManageGroups = 1, so it is
            // NOT denied here — it used to return 500 only because the leaked
            // person-1 membership (issue #9828) sent the route into its audit
            // Note write, which has no current user under API-key auth. With
            // the leak gone the call is an authorized no-op returning 200.
            // plainauth (john.plainauth, id 900) passes AuthMiddleware (it is
            // not EditSelf-exclusive) and lacks usr_ManageGroups, so the 403
            // below comes from ManageGroupRoleAuthMiddleware — the gate this
            // test's name promises to cover. (limited.user would be stopped by
            // AuthMiddleware first and prove nothing about the group gate.)
            cy.makePrivatePlainAuthAPICall(
                "DELETE",
                `/api/groups/${groupID}/removeperson/1`,
                null,
                [403]
            );
        });

        it("Non-admin should be denied adding group roles", () => {
            // Test that a user without bManageGroups permission is denied
            cy.makePrivateUserAPICall(
                "POST",
                `/api/groups/${groupID}/roles`,
                {
                    roleName: "Unauthorized Role",
                },
                [401, 403, 500]
            );
        });

        it("Non-admin should be denied deleting group roles", () => {
            // Test that a user without bManageGroups permission is denied
            cy.makePrivateUserAPICall(
                "DELETE",
                `/api/groups/${groupID}/roles/1`,
                null,
                [401, 403, 500]
            );
        });
    });
});
