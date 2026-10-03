/// <reference types="cypress" />

/**
 * API tests for the OptionManager default-role, inactive-classification and directory-classification flags
 *
 * Covers:
 *   POST /admin/api/options/{listId}/{optionId}/default
 *   POST /admin/api/options/{listId}/{optionId}/inactive
 *   POST /admin/api/options/{listId}/{optionId}/directory
 *
 * The inactive and directory settings are captured up front and put back afterwards. The
 * default-role test re-selects the role the group already has.
 */
describe("API Private Admin OptionManager — flags", () => {
    const classificationsList = 1;
    let originalInactive;
    let originalDirectory;

    before(() => {
        cy.getSystemConfig("sInactiveClassification").then((value) => {
            originalInactive = value;
        });
        cy.getSystemConfig("sDirClassifications").then((value) => {
            originalDirectory = value;
        });
    });

    after(() => {
        cy.restoreSystemConfig("sInactiveClassification", originalInactive);
        cy.restoreSystemConfig("sDirClassifications", originalDirectory);
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

    describe("POST /{listId}/{optionId}/directory", () => {
        it("toggles an option in and out of the directory list and persists it", () => {
            const url = `/admin/api/options/${classificationsList}/1/directory`;
            cy.makePrivateAdminAPICall("POST", url, {}, 200).then((first) => {
                expect(first.body.directory).to.be.an("array");
                const nowIncluded = first.body.directory.includes(1);
                cy.getSystemConfig("sDirClassifications").then((saved) => {
                    expect(saved.split(",").map(Number).includes(1)).to.eq(nowIncluded);
                });
                cy.makePrivateAdminAPICall("POST", url, {}, 200).then((second) => {
                    expect(second.body.directory.includes(1)).to.eq(!nowIncluded);
                });
            });
        });

        it("keeps a stored Unassigned (0) while other classifications are toggled", () => {
            cy.restoreSystemConfig("sDirClassifications", "0,1");
            const url = `/admin/api/options/${classificationsList}/1/directory`;
            cy.makePrivateAdminAPICall("POST", url, {}, 200).then((off) => {
                expect(off.body.directory).to.deep.eq([0]);
            });
            cy.makePrivateAdminAPICall("POST", url, {}, 200).then((on) => {
                expect(on.body.directory).to.deep.eq([0, 1]);
            });
            cy.getSystemConfig("sDirClassifications").should("eq", "0,1");
        });

        it("leaves the inactive list untouched", () => {
            cy.getSystemConfig("sInactiveClassification").then((before) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/admin/api/options/${classificationsList}/1/directory`,
                    {},
                    200,
                );
                cy.getSystemConfig("sInactiveClassification").then((after) => {
                    expect(after).to.eq(before);
                });
            });
        });

        it("rejects lists other than classifications with 400", () => {
            cy.makePrivateAdminAPICall("POST", "/admin/api/options/2/1/directory", {}, 400).then(
                (resp) => {
                    expect(resp.body.success).to.eq(false);
                    expect(resp.body.message).to.be.a("string").and.not.be.empty;
                },
            );
        });

        it("returns 404 for an unknown option", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/admin/api/options/${classificationsList}/999999/directory`,
                {},
                404,
            ).then((resp) => {
                expect(resp.body.success).to.eq(false);
            });
        });
    });

    describe("DELETE /{listId}/{optionId} flag cleanup", () => {
        it("drops a deleted classification from the directory and inactive lists", () => {
            cy.makePrivateAdminAPICall("POST", `/admin/api/options/${classificationsList}`, {
                name: `CypressFlagCleanup_${Date.now()}`,
            }, 200).then((created) => {
                const id = created.body.optionId ?? created.body.id;
                expect(id, "created option id").to.be.a("number");
                const flagged = (suffix) =>
                    cy.makePrivateAdminAPICall("POST", `/admin/api/options/${classificationsList}/${id}/${suffix}`, {}, 200);
                flagged("directory");
                flagged("inactive");
                cy.makePrivateAdminAPICall("DELETE", `/admin/api/options/${classificationsList}/${id}`, null, 200);
                cy.getSystemConfig("sDirClassifications").then((value) => {
                    expect(value.split(",").map(Number)).to.not.include(id);
                });
                cy.getSystemConfig("sInactiveClassification").then((value) => {
                    expect(value.split(",").map(Number)).to.not.include(id);
                });
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
