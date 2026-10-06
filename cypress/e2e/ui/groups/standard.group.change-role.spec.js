/// <reference types="cypress" />

// Change Role used to show the new role until a reload brought the old one back,
// so each test checks the role on a freshly loaded page.
describe("Change a group member's role (#10337)", () => {
    const personId = 2;
    const memberRoleId = 1; // a new group's default role, "Member"
    let groupId;
    let leaderRoleId;

    before(() => {
        cy.makePrivateAdminAPICall("POST", "/api/groups/", { groupName: `Change Role ${Date.now()}` }, 200).then(
            (resp) => {
                groupId = resp.body.Id;
                cy.makePrivateAdminAPICall("POST", `/api/groups/${groupId}/roles`, { roleName: "Leader" }, 200).then(
                    (roleResp) => {
                        leaderRoleId = roleResp.body.newRole.roleID;
                    },
                );
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/groups/${groupId}/addperson/${personId}`,
                    { RoleID: memberRoleId },
                    200,
                );
            },
        );
    });

    beforeEach(() => {
        cy.makePrivateAdminAPICall(
            "POST",
            `/api/groups/${groupId}/userRole/${personId}`,
            { roleID: memberRoleId },
            200,
        );
        cy.setupStandardSession();
    });

    after(() => {
        if (groupId) {
            cy.makePrivateAdminAPICall("DELETE", `/api/groups/${groupId}`, null, [200, 404]);
        }
    });

    // The role pickers are TomSelects whose dropdown is attached to <body>.
    const pickRole = (modalSelector, roleId) => {
        cy.get(`${modalSelector} .ts-control`).click();
        cy.get(`.ts-dropdown .option[data-value="${roleId}"]`).click();
    };

    it("saves the new role from the group page", () => {
        cy.intercept("POST", `**/api/groups/${groupId}/userRole/${personId}`).as("setRole");
        cy.visit(`/groups/view/${groupId}`);

        cy.get(`#membersTable .changeMembership[data-personid="${personId}"]`, { timeout: 10000 })
            .closest("tr")
            .find('[data-bs-toggle="dropdown"]')
            .click();
        cy.get(`#membersTable .changeMembership[data-personid="${personId}"]`).click();
        pickRole("#groupViewModal", leaderRoleId);
        cy.get("#gvModalConfirmBtn").click();
        cy.wait("@setRole").its("response.statusCode").should("eq", 200);

        cy.reload();
        cy.get(`#membersTable .changeMembership[data-personid="${personId}"]`, { timeout: 10000 })
            .closest("tr")
            .should("contain", "Leader");
    });

    it("saves the new role from the person page", () => {
        cy.intercept("POST", `**/api/groups/${groupId}/userRole/${personId}`).as("setRole");
        cy.visit(`/people/view/${personId}`);
        cy.get("#nav-item-groups").click();

        cy.get(`#groups .changeRole[data-groupid="${groupId}"]`)
            .should("have.attr", "data-current-role-id", String(memberRoleId))
            .closest(".list-group-item")
            .find('[data-bs-toggle="dropdown"]')
            .click();
        cy.get(`#groups .changeRole[data-groupid="${groupId}"]`).click();
        pickRole("#personGroupModal", leaderRoleId);
        cy.get("#personGroupConfirmBtn").click();
        cy.wait("@setRole").its("response.statusCode").should("eq", 200);

        cy.get(`#groups .changeRole[data-groupid="${groupId}"]`, { timeout: 10000 }).should(
            "have.attr",
            "data-current-role-id",
            String(leaderRoleId),
        );
    });
});
