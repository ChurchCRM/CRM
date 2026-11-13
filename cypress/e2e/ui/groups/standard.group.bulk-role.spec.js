/// <reference types="cypress" />

describe("Change the role of several group members at once (#3850)", () => {
    const memberRoleId = 1; // a new group's default role, "Member"
    const memberIds = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];
    let groupId;
    let leaderRoleId;

    const roleOf = (personId) =>
        cy.makePrivateAdminAPICall("GET", `/api/groups/${groupId}/members`, null, 200).then(
            (resp) => resp.body.Person2group2roleP2g2rs.find((m) => m.PersonId === personId).RoleId,
        );

    // The role picker is a TomSelect whose dropdown is attached to <body>.
    const pickRole = (roleId) => {
        cy.get("#groupViewModal .ts-control").click();
        cy.get(`.ts-dropdown .option[data-value="${roleId}"]`).click();
        cy.get("#gvModalConfirmBtn").click();
    };

    const showPageTwoOfFive = () => {
        cy.window().then((win) => {
            win.CRM.DataTableAPI.page.len(5).draw();
        });
        cy.get("#membersTable_wrapper .dt-paging-button").contains(/^2$/).click();
        cy.window().its("CRM.DataTableAPI").invoke("page").should("eq", 1);
    };

    before(() => {
        cy.makePrivateAdminAPICall("POST", "/api/groups/", { groupName: `Bulk Role ${Date.now()}` }, 200).then(
            (resp) => {
                groupId = resp.body.Id;
                cy.makePrivateAdminAPICall("POST", `/api/groups/${groupId}/roles`, { roleName: "Leader" }, 200).then(
                    (roleResp) => {
                        leaderRoleId = roleResp.body.newRole.roleID;
                    },
                );
                memberIds.forEach((personId) => {
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `/api/groups/${groupId}/addperson/${personId}`,
                        { RoleID: memberRoleId },
                        200,
                    );
                });
            },
        );
    });

    beforeEach(() => {
        cy.makePrivateAdminAPICall(
            "POST",
            `/api/groups/${groupId}/members/role`,
            { personIds: memberIds, roleID: memberRoleId },
            200,
        );
        cy.setupStandardSession();
    });

    after(() => {
        if (groupId) {
            cy.makePrivateAdminAPICall("DELETE", `/api/groups/${groupId}`, null, [200, 404]);
        }
    });

    it("moves the ticked members to the chosen role", () => {
        cy.intercept("POST", `**/api/groups/${groupId}/members/role`).as("bulkRole");
        cy.visit(`/groups/view/${groupId}`);
        cy.get("#bulkRoleBar").should("have.class", "d-none");

        cy.get("#membersTable .member-select", { timeout: 10000 }).should("have.length.at.least", 3);
        cy.get("#membersTable .member-select").eq(0).check().invoke("attr", "data-personid").as("first");
        cy.get("#membersTable .member-select").eq(1).check().invoke("attr", "data-personid").as("second");
        cy.get("#membersTable .member-select").eq(2).invoke("attr", "data-personid").as("untouched");
        cy.get("#bulkRoleBar").should("not.have.class", "d-none");
        cy.get("#bulkSelectedCount").should("have.text", "2");
        cy.get("#selectAllMembers").should("have.prop", "indeterminate", true);

        cy.get("#bulkChangeRole").click();
        pickRole(leaderRoleId);
        cy.wait("@bulkRole").its("response.statusCode").should("eq", 200);

        cy.get("#bulkRoleBar").should("have.class", "d-none");
        cy.get("#membersTable .member-select:checked").should("not.exist");
        cy.get(`.role-filter-pill[data-role-id="${leaderRoleId}"]`).should("contain", "2");
        cy.get("@first").then((id) => roleOf(Number(id)).should("eq", leaderRoleId));
        cy.get("@second").then((id) => roleOf(Number(id)).should("eq", leaderRoleId));
        cy.get("@untouched").then((id) => roleOf(Number(id)).should("eq", memberRoleId));
    });

    it("select all ticks every member the role filter shows, across pages", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            `/api/groups/${groupId}/members/role`,
            { personIds: memberIds.slice(0, 7), roleID: leaderRoleId },
            200,
        );
        cy.visit(`/groups/view/${groupId}`);
        cy.get(`.role-filter-pill[data-role-id="${leaderRoleId}"]`, { timeout: 10000 }).click();
        cy.window().then((win) => {
            win.CRM.DataTableAPI.page.len(5).draw();
        });

        cy.get("#selectAllMembers").check();
        cy.get("#bulkSelectedCount").should("have.text", "7");
        cy.get(`.role-filter-pill[data-role-id="${leaderRoleId}"]`).should("have.class", "active");

        cy.get("#bulkClearSelection").click();
        cy.get("#bulkRoleBar").should("have.class", "d-none");
        cy.get("#selectAllMembers").should("not.be.checked");
    });

    it("offers no ticking in a group with a single role", () => {
        cy.makePrivateAdminAPICall("GET", "/api/groups/9/roles", null, 200).its("body").should("have.length", 1);
        cy.visit("/groups/view/9");
        cy.get("#membersTable tbody a[href*='/people/view/']", { timeout: 10000 }).should("exist");
        cy.get("#membersTable .member-select").should("not.exist");
        cy.get("#selectAllMembers").should("not.exist");
        cy.get("#bulkRoleBar").should("have.class", "d-none");
    });

    it("stays on the current page after a role change", () => {
        cy.intercept("POST", `**/api/groups/${groupId}/userRole/*`).as("setRole");
        cy.intercept("POST", `**/api/groups/${groupId}/members/role`).as("bulkRole");
        cy.visit(`/groups/view/${groupId}`);
        cy.get("#membersTable .member-select", { timeout: 10000 }).should("exist");
        showPageTwoOfFive();

        cy.get("#membersTable tbody tr").first().find('[data-bs-toggle="dropdown"]').click();
        cy.get("#membersTable .dropdown-menu.show .changeMembership").click();
        pickRole(leaderRoleId);
        cy.wait("@setRole").its("response.statusCode").should("eq", 200);
        cy.window().its("CRM.DataTableAPI").invoke("page").should("eq", 1);

        cy.get("#membersTable .member-select").eq(1).check();
        cy.get("#bulkChangeRole").click();
        pickRole(leaderRoleId);
        cy.wait("@bulkRole").its("response.statusCode").should("eq", 200);
        cy.window().its("CRM.DataTableAPI").invoke("page").should("eq", 1);
    });
});
