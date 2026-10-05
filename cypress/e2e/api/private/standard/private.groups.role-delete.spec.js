/// <reference types="cypress" />

// Each test builds its own group: roles Member (1, the default), Leader (2) and
// Helper (3), with one seeded person in each.
describe("Deleting a group role (#10338)", () => {
    const people = { member: 2, leader: 3, helper: 4 };
    const roleIds = { Member: 1, Leader: 2, Helper: 3 };
    let groupId;

    const groupApi = (method, path, body, status) =>
        cy.makePrivateAdminAPICall(method, `/api/groups/${groupId}${path}`, body, status);

    const getRoles = () => groupApi("GET", "/roles", null, 200).then((resp) => resp.body);

    const getDefaultRoleName = () =>
        groupApi("GET", "", null, 200).then((groupResp) =>
            getRoles().then(
                (roles) =>
                    roles.find((role) => role.OptionId === groupResp.body.DefaultRole)?.OptionName ?? "(none)",
            ),
        );

    const getRoleNamesByPerson = () =>
        getRoles().then((roles) =>
            groupApi("GET", "/members", null, 200).then((resp) => {
                const names = {};
                resp.body.Person2group2roleP2g2rs.forEach((member) => {
                    names[member.PersonId] =
                        roles.find((role) => role.OptionId === member.RoleId)?.OptionName ?? "(no role)";
                });
                return names;
            }),
        );

    beforeEach(() => {
        cy.makePrivateAdminAPICall("POST", "/api/groups/", { groupName: `Role delete ${Date.now()}` }, 200).then(
            (resp) => {
                groupId = resp.body.Id;
                groupApi("POST", "/roles", { roleName: "Leader" }, 200);
                groupApi("POST", "/roles", { roleName: "Helper" }, 200);
                groupApi("POST", `/addperson/${people.member}`, { RoleID: roleIds.Member }, 200);
                groupApi("POST", `/addperson/${people.leader}`, { RoleID: roleIds.Leader }, 200);
                groupApi("POST", `/addperson/${people.helper}`, { RoleID: roleIds.Helper }, 200);
            },
        );
    });

    afterEach(() => {
        if (groupId) {
            cy.makePrivateAdminAPICall("DELETE", `/api/groups/${groupId}`, null, [200, 404]);
        }
    });

    it("moves the deleted role's members to the default and leaves everyone else alone", () => {
        groupApi("DELETE", `/roles/${roleIds.Leader}`, null, 200).then((resp) => {
            expect(resp.body.map((role) => role.lst_OptionName)).to.have.members(["Member", "Helper"]);
        });

        getRoleNamesByPerson().should("deep.equal", {
            [people.member]: "Member",
            [people.leader]: "Member",
            [people.helper]: "Helper",
        });
        getRoles().then((roles) => {
            const helper = roles.find((role) => role.OptionName === "Helper");
            expect(helper.OptionId, "Helper keeps its id").to.equal(roleIds.Helper);
            expect(helper.OptionSequence, "Helper moves up in the order").to.equal(2);
        });
        getDefaultRoleName().should("eq", "Member");
    });

    it("keeps the default on its role when an earlier role is deleted", () => {
        groupApi("POST", "/defaultRole", { roleID: roleIds.Helper }, 200);

        groupApi("DELETE", `/roles/${roleIds.Leader}`, null, 200);

        getDefaultRoleName().should("eq", "Helper");
        getRoleNamesByPerson().should("deep.equal", {
            [people.member]: "Member",
            [people.leader]: "Helper",
            [people.helper]: "Helper",
        });
    });

    it("makes the first remaining role the default when the default role is deleted", () => {
        groupApi("DELETE", `/roles/${roleIds.Member}`, null, 200);

        getDefaultRoleName().should("eq", "Leader");
        getRoleNamesByPerson().should("deep.equal", {
            [people.member]: "Leader",
            [people.leader]: "Leader",
            [people.helper]: "Helper",
        });
    });

    it("does the same through the admin list options API", () => {
        getRoles().then((roles) => {
            cy.makePrivateAdminAPICall("DELETE", `/admin/api/options/${roles[0].Id}/${roleIds.Member}`, null, 200);
        });

        getDefaultRoleName().should("eq", "Leader");
        getRoleNamesByPerson().should("deep.equal", {
            [people.member]: "Leader",
            [people.leader]: "Leader",
            [people.helper]: "Helper",
        });
    });

    it("refuses a role the group does not have and the group's last role", () => {
        groupApi("DELETE", "/roles/99", null, 404);
        groupApi("DELETE", "/roles/0", null, 404);
        getRoles().then((roles) => {
            expect(roles.map((role) => role.OptionId)).to.have.members([1, 2, 3]);
        });

        groupApi("DELETE", `/roles/${roleIds.Leader}`, null, 200);
        groupApi("DELETE", `/roles/${roleIds.Helper}`, null, 200);
        groupApi("DELETE", `/roles/${roleIds.Member}`, null, 400);
        getRoles().then((roles) => {
            expect(roles.map((role) => role.OptionName)).to.deep.equal(["Member"]);
        });
    });
});
