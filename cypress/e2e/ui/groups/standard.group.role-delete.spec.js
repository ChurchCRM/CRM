/// <reference types="cypress" />

// Group Editor → delete a role (#10338). Roles Member (1, the default), Leader (2)
// and Helper (3), with one seeded person in each.
describe("Delete a role in Group Editor (#10338)", () => {
    const people = { member: 2, leader: 3, helper: 4 };
    const roleIds = { Member: 1, Leader: 2, Helper: 3 };
    let groupId;

    beforeEach(() => {
        cy.makePrivateAdminAPICall("POST", "/api/groups/", { groupName: `Editor role delete ${Date.now()}` }, 200).then(
            (resp) => {
                groupId = resp.body.Id;
                const api = (path, body) =>
                    cy.makePrivateAdminAPICall("POST", `/api/groups/${groupId}${path}`, body, 200);
                api("/roles", { roleName: "Leader" });
                api("/roles", { roleName: "Helper" });
                api(`/addperson/${people.member}`, { RoleID: roleIds.Member });
                api(`/addperson/${people.leader}`, { RoleID: roleIds.Leader });
                api(`/addperson/${people.helper}`, { RoleID: roleIds.Helper });
            },
        );
        cy.setupStandardSession();
    });

    afterEach(() => {
        if (groupId) {
            cy.makePrivateAdminAPICall("DELETE", `/api/groups/${groupId}`, null, [200, 404]);
        }
    });

    const deleteRole = (roleId) => {
        cy.intercept("DELETE", `**/api/groups/${groupId}/roles/${roleId}`).as("deleteRole");
        cy.get(`#roleDelete-${roleId}`).click();
        cy.get("#confirmDeleteRole").should("be.visible").click();
        cy.wait("@deleteRole").its("response.statusCode").should("eq", 200);
    };

    const memberRow = (personId) =>
        cy.get(`#membersTable .changeMembership[data-personid="${personId}"]`, { timeout: 10000 }).closest("tr");

    it("keeps the other members' roles", () => {
        cy.visit(`/groups/editor/${groupId}`);
        deleteRole(roleIds.Leader);

        cy.visit(`/groups/view/${groupId}`);
        memberRow(people.helper).should("contain", "Helper");
        memberRow(people.leader).should("contain", "Member");
        memberRow(people.member).should("contain", "Member");
    });

    it("shows the first remaining role as the default after the default is deleted", () => {
        cy.visit(`/groups/editor/${groupId}`);
        deleteRole(roleIds.Member);

        cy.get(`#roleDelete-${roleIds.Leader}`).closest("tr").find(".defaultRole").should("not.exist");
        cy.get(`#defaultRole-${roleIds.Helper}`).should("exist");

        cy.reload();
        cy.get(`#roleDelete-${roleIds.Leader}`).closest("tr").find(".defaultRole").should("not.exist");
        cy.get(`#defaultRole-${roleIds.Helper}`).should("exist");
    });
});
