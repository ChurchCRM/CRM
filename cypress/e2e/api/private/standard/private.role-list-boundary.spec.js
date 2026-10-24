/// <reference types="cypress" />

// #10682: list_lst option IDs are meaningful only within their parent list.
// Uses an isolated group and a seeded person's existing family role unchanged.
describe("Role IDs must belong to the destination role list", () => {
    const personId = 2;
    let groupId;
    let extendedGroupId;
    let validGroupRoleId;
    let familyOnlyRoleId;
    let groupOnlyRoleId;
    let originalFamilyRoleId;
    let familyIds;

    before(() => {
        cy.makePrivateAdminAPICall("GET", "/api/person/" + personId, null, 200).then((resp) => {
            originalFamilyRoleId = resp.body.FmrId;
        });
        cy.makePrivateAdminAPICall("GET", "/api/persons/roles", null, 200).then((resp) => {
            familyIds = resp.body.map((role) => Number(role.OptionId));
            expect(familyIds, "seeded family role IDs").to.have.length.at.least(2);
            expect(familyIds).to.include(Number(originalFamilyRoleId));
        });
        cy.makePrivateAdminAPICall(
            "POST", "/api/groups/",
            { groupName: "Role list boundary " + Date.now() }, 200
        ).then((resp) => {
            groupId = resp.body.Id;
            cy.makePrivateAdminAPICall("GET", "/api/groups/" + groupId + "/roles", null, 200)
                .then((rolesResp) => {
                    const groupIds = rolesResp.body.map((role) => Number(role.OptionId));
                    validGroupRoleId = groupIds[0];
                    familyOnlyRoleId = familyIds.find((id) => !groupIds.includes(id));
                    expect(validGroupRoleId, "valid group role").to.be.greaterThan(0);
                    expect(familyOnlyRoleId, "family-only role ID").to.exist;
                });
        });

        // Extend a separate group: adding options to groupId would make family IDs valid in it.
        const addUntilForeign = () =>
            cy.makePrivateAdminAPICall(
                "POST", "/api/groups/" + extendedGroupId + "/roles",
                { roleName: "Boundary option " + Date.now() }, 200
            ).then((resp) => {
                const optionId = Number(resp.body.newRole.roleID);
                if (!familyIds.includes(optionId)) {
                    groupOnlyRoleId = optionId;
                } else {
                    expect(optionId, "bounded role fixture").to.be.lessThan(30);
                    return addUntilForeign();
                }
            });
        cy.makePrivateAdminAPICall(
            "POST", "/api/groups/",
            { groupName: "Role list boundary ext " + Date.now() }, 200
        ).then((resp) => {
            extendedGroupId = resp.body.Id;
        });
        cy.then(addUntilForeign);
    });

    after(() => {
        for (const id of [groupId, extendedGroupId]) {
            if (id) {
                cy.makePrivateAdminAPICall("DELETE", "/api/groups/" + id, null, [200, 404]);
            }
        }
    });

    it("rejects an actual group-list option for a person's family role", () => {
        cy.makePrivateAdminAPICall(
            "POST", "/api/person/" + personId + "/role/" + groupOnlyRoleId, null, 404
        );
        cy.makePrivateAdminAPICall("GET", "/api/person/" + personId, null, 200)
            .then((resp) => expect(resp.body.FmrId).to.eq(originalFamilyRoleId));
        cy.makePrivateAdminAPICall(
            "POST", "/api/person/" + personId + "/role/" + originalFamilyRoleId, null, 200
        );
    });

    it("rejects an actual family-role option for group membership", () => {
        cy.makePrivateAdminAPICall(
            "POST", "/api/groups/" + groupId + "/addperson/" + personId,
            { RoleID: familyOnlyRoleId }, 400
        );
        cy.makePrivateAdminAPICall("GET", "/api/groups/" + groupId + "/members", null, 200)
            .then((resp) => expect(resp.body.Person2group2roleP2g2rs).to.have.length(0));
        cy.makePrivateAdminAPICall(
            "POST", "/api/groups/" + groupId + "/addperson/" + personId,
            { RoleID: validGroupRoleId }, 200
        );
        cy.makePrivateAdminAPICall("GET", "/api/groups/" + groupId + "/members", null, 200)
            .then((resp) => {
                expect(resp.body.Person2group2roleP2g2rs).to.have.length(1);
                expect(resp.body.Person2group2roleP2g2rs[0].RoleId).to.eq(validGroupRoleId);
            });
    });

    it("rejects foreign or malformed defaults, but accepts the group's own role", () => {
        for (const value of [familyOnlyRoleId, [validGroupRoleId], 999999]) {
            cy.makePrivateAdminAPICall(
                "POST", "/api/groups/" + groupId + "/defaultRole",
                { roleID: value }, 400
            );
        }
        cy.makePrivateAdminAPICall(
            "POST", "/api/groups/" + groupId + "/defaultRole",
            { roleID: validGroupRoleId }, 200
        );
    });
});
