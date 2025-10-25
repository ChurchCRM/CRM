/// <reference types="cypress" />

describe("API Private Group Operations", () => {
    let groupID = 1; // Use existing group ID for testing

    describe("Group Member Operations", () => {
        it("Add member to group and verify authorization", () => {
            // Test adding a person to a group
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/addperson/1`,
                {
                    RoleID: 1,
                },
                200
            ).then((resp) => {
                expect(resp.body).to.exist;
                expect(resp.body).to.be.an("array");
            });
        });

        it("Remove member from group", () => {
            // Test removing a person from a group
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/groups/${groupID}/removeperson/1`,
                null,
                200
            );
        });

        it("Get group members", () => {
            // Test retrieving group members
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${groupID}/members`,
                null,
                200
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
            });
        });

        it("Update member role in group", () => {
            // Test updating a person's role in a group
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/userRole/1`,
                {
                    roleID: 1,
                },
                200
            ).then((resp) => {
                expect(resp.body).to.exist;
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
            });
        });

        it("Add new role to group", () => {
            // Test adding a new role to a group
            const roleNameUnique = "TestRole" + Date.now();
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupID}/roles`,
                {
                    roleName: roleNameUnique,
                },
                200
            ).then((resp) => {
                expect(resp.body).to.have.property("newRole");
                expect(resp.body.newRole).to.have.property("roleName");
            });
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

        it("Delete group role authorization", () => {
            // Test that deleting roles is properly authorized
            // Note: This may fail if it's the last role or admin permissions restrict it
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/groups/${groupID}/roles/999`, // Non-existent role ID
                null,
                [200, 400, 422] // Accept various responses
            );
        });
    });

    describe("Group Properties Operations", () => {
        it("Enable group-specific properties", () => {
            // Note: This operation may fail if properties already exist
            // We're testing that it's properly authorized via AuthService
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/enable-properties`,
                {
                    groupID: groupID,
                },
                [200, 400, 422]
            );
        });

        it("Disable group-specific properties", () => {
            // Note: This operation may fail depending on group state
            // We're testing that it's properly authorized via AuthService
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/disable-properties`,
                {
                    groupID: groupID,
                },
                [200, 400, 422]
            );
        });
    });

    describe("Authorization Tests - Non-Admin Users", () => {
        it("Non-admin should be denied adding group members", () => {
            // Test that a user without bManageGroups permission is denied
            cy.request({
                method: "POST",
                url: `${Cypress.env("apiRoot")}/api/groups/${groupID}/addperson/1`,
                headers: {
                    Cookie: `PHPSESSID=${Cypress.env("sessionID")}`,
                },
                body: {
                    RoleID: 1,
                },
                failOnStatusCode: false,
            }).then((resp) => {
                // Should be denied with 401 or similar auth error
                expect(resp.status).to.be.oneOf([401, 403]);
            });
        });

        it("Non-admin should be denied removing group members", () => {
            // Test that a user without bManageGroups permission is denied
            cy.request({
                method: "DELETE",
                url: `${Cypress.env("apiRoot")}/api/groups/${groupID}/removeperson/1`,
                headers: {
                    Cookie: `PHPSESSID=${Cypress.env("sessionID")}`,
                },
                failOnStatusCode: false,
            }).then((resp) => {
                // Should be denied with 401 or similar auth error
                expect(resp.status).to.be.oneOf([401, 403]);
            });
        });

        it("Non-admin should be denied adding group roles", () => {
            // Test that a user without bManageGroups permission is denied
            cy.request({
                method: "POST",
                url: `${Cypress.env("apiRoot")}/api/groups/${groupID}/roles`,
                headers: {
                    Cookie: `PHPSESSID=${Cypress.env("sessionID")}`,
                },
                body: {
                    roleName: "Unauthorized Role",
                },
                failOnStatusCode: false,
            }).then((resp) => {
                // Should be denied with 401 or similar auth error
                expect(resp.status).to.be.oneOf([401, 403]);
            });
        });
    });
});
