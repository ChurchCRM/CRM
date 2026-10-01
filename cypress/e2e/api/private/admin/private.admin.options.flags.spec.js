/// <reference types="cypress" />

/**
 * API tests for the OptionManager default-role and inactive-classification flags
 *
 * Covers:
 *   POST /admin/api/options/{listId}/{optionId}/default
 *   POST /admin/api/options/{listId}/{optionId}/inactive
 *
 * The inactive setting is captured up front and put back afterwards. The
 * default-role test re-selects the role the group already has.
 */
describe("API Private Admin OptionManager — flags", () => {
    const classificationsList = 1;
    let originalInactive;

    before(() => {
        cy.getSystemConfig("sInactiveClassification").then((value) => {
            originalInactive = value;
        });
    });

    after(() => {
        cy.restoreSystemConfig("sInactiveClassification", originalInactive);
    });

    describe("POST /{listId}/{optionId}/inactive", () => {
        it("toggles an option in and out of the inactive list", () => {
            const url = `/admin/api/options/${classificationsList}/1/inactive`;
            cy.makePrivateAdminAPICall("POST", url, {}, 200).then((first) => {
                expect(first.body.inactive).to.be.an("array");
                const nowInactive = first.body.inactive.includes(1);
                cy.makePrivateAdminAPICall("POST", url, {}, 200).then((second) => {
                    expect(second.body.inactive.includes(1)).to.eq(!nowInactive);
                });
            });
        });

        it("rejects lists other than classifications with 400", () => {
            cy.makePrivateAdminAPICall("POST", "/admin/api/options/2/1/inactive", {}, 400).then(
                (resp) => {
                    expect(resp.body.success).to.eq(false);
                    expect(resp.body.message).to.be.a("string").and.not.be.empty;
                },
            );
        });

        it("returns 404 for an unknown option", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/admin/api/options/${classificationsList}/999999/inactive`,
                {},
                404,
            ).then((resp) => {
                expect(resp.body.success).to.eq(false);
            });
        });
    });

    describe("POST /{listId}/{optionId}/default", () => {
        it("sets the default role of the group that owns the role list", () => {
            cy.makePrivateAdminAPICall("GET", "/api/groups/1", null, 200).then((group) => {
                const { RoleListId, DefaultRole } = group.body;
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/admin/api/options/${RoleListId}/${DefaultRole}/default`,
                    {},
                    200,
                ).then((resp) => {
                    expect(resp.body.success).to.eq(true);
                });
                cy.makePrivateAdminAPICall("GET", "/api/groups/1", null, 200).then((after) => {
                    expect(after.body.DefaultRole).to.eq(DefaultRole);
                });
            });
        });

        it("returns 404 when no group uses the role list", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/options/999999/1/default",
                {},
                404,
            ).then((resp) => {
                expect(resp.body.success).to.eq(false);
            });
        });
    });
});
