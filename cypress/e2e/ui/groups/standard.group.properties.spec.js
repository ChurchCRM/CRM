/// <reference types="cypress" />

/**
 * UI tests for group property assignment/removal on /groups/view/{id}
 * and property definition deletion on PropertyList.php.
 *
 * Requires: Docker / local environment with seeded data.
 * - Group with ID=1 must exist
 * - At least one group property definition must exist (pro_Class='g')
 */

describe("UI: Group Property Assignment (/groups/view/{id})", () => {
    const groupID = 1;

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("loads GroupView and shows the Properties sidebar card", () => {
        cy.visit(`/groups/view/${groupID}`);
        cy.contains(".card-title", "Properties").should("be.visible");
    });

    describe("Assign a property to a group", () => {
        it("shows the assign select and button when group properties exist", () => {
            cy.visit(`/groups/view/${groupID}`);
            // Either the select is shown (properties available) or a no-properties message
            cy.get("body").then(($body) => {
                if ($body.find("#group-property-select").length) {
                    cy.get("#group-property-select").should("be.visible");
                    cy.get("#assign-group-property-btn").should("be.visible");
                } else {
                    // All properties already assigned — that's a valid state
                    cy.log("All group properties already assigned — skipping assign test");
                }
            });
        });

        it("assigns a property without a prompt via API and reloads", () => {
            // First remove all existing assignments so we have something to assign
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${groupID}/properties`,
                null,
                200
            ).then((assignedResp) => {
                // Remove all current assignments
                const removals = assignedResp.body.map((p) =>
                    cy.makePrivateAdminAPICall(
                        "DELETE",
                        `/api/groups/${groupID}/properties/${p.id}`,
                        null,
                        [200, 404]
                    )
                );

                cy.makePrivateAdminAPICall(
                    "GET",
                    "/api/groups/properties",
                    null,
                    200
                ).then((defsResp) => {
                    const noprompt = defsResp.body.find(
                        (p) => !p.ProPrompt && !p.pro_Prompt
                    );
                    if (!noprompt) {
                        cy.log("No prompt-free group property definition found — skipping");
                        return;
                    }
                    const propertyId = noprompt.ProId ?? noprompt.pro_ID;

                    cy.visit(`/groups/view/${groupID}`);
                    cy.get("#group-property-select").select(String(propertyId));
                    cy.get("#assign-group-property-btn").click();
                    // Page should reload and show the assigned property name
                    cy.contains(noprompt.ProName ?? noprompt.pro_Name).should("be.visible");
                });
            });
        });
    });

    describe("Remove an assigned property", () => {
        let testPropertyId = null;

        before(() => {
            // Ensure at least one property is assigned before the suite runs
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/groups/properties",
                null,
                200
            ).then((resp) => {
                if (resp.body.length === 0) return;
                testPropertyId = resp.body[0].ProId ?? resp.body[0].pro_ID;
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/groups/${groupID}/properties/${testPropertyId}`,
                    {},
                    200
                );
            });
        });

        it("shows a Remove button for assigned properties", function () {
            if (!testPropertyId) this.skip();

            cy.visit(`/groups/view/${groupID}`);
            cy.get(".remove-group-property-btn").should("have.length.gte", 1);
        });

        it("clicking Remove opens a bootbox confirm dialog", function () {
            if (!testPropertyId) this.skip();

            cy.visit(`/groups/view/${groupID}`);
            cy.waitForLocales();
            cy.get(".remove-group-property-btn").first().click();
            cy.get(".bootbox").should("be.visible");
            // Cancel — do not actually remove
            cy.get(".bootbox .btn-secondary").click();
            cy.get(".bootbox").should("not.exist");
        });

        it("confirming Remove calls DELETE API and reloads page", function () {
            if (!testPropertyId) this.skip();

            cy.visit(`/groups/view/${groupID}`);
            cy.waitForLocales();

            // Get property name before removal
            cy.get(".remove-group-property-btn")
                .first()
                .then(($btn) => {
                    const name = $btn.data("pro-name");

                    cy.intercept("DELETE", `/api/groups/${groupID}/properties/*`).as(
                        "removeProperty"
                    );

                    $btn.trigger("click");
                    cy.get(".bootbox").should("be.visible");
                    cy.get(".bootbox .btn-danger").click();

                    cy.wait("@removeProperty").its("response.statusCode").should("eq", 200);
                    // After reload, the property should be gone from the sidebar
                    cy.get(".remove-group-property-btn[data-pro-name='" + name + "']").should(
                        "not.exist"
                    );
                });
        });
    });

    describe("Edit a property value (has prompt)", () => {
        it("Edit Value button opens bootbox prompt", function () {
            cy.visit(`/groups/view/${groupID}`);
            cy.waitForLocales();

            cy.get("body").then(($body) => {
                if (!$body.find(".edit-group-property-btn").length) {
                    cy.log("No editable (prompt-based) properties assigned — skipping");
                    return;
                }
                cy.get(".edit-group-property-btn").first().click();
                cy.get(".bootbox").should("be.visible");
                cy.get(".bootbox .btn-secondary, .bootbox .bootbox-cancel").click();
            });
        });
    });
});

// ---------------------------------------------------------------------- //
// PropertyList.php — property definition delete
// ---------------------------------------------------------------------- //
describe("UI: Property Definition Delete (PropertyList.php)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("loads PropertyList for groups and shows the actions dropdown", () => {
        cy.visit("/PropertyList.php?Type=g");
        cy.get("body").then(($body) => {
            if ($body.find(".delete-property-btn").length) {
                cy.get(".delete-property-btn").should("exist");
            } else {
                cy.log("No group property definitions exist yet — dropdown not shown");
            }
        });
    });

    it("clicking Delete opens a bootbox confirmation with warning text", () => {
        cy.visit("/PropertyList.php?Type=g");
        cy.waitForLocales();

        cy.get("body").then(($body) => {
            if (!$body.find(".delete-property-btn").length) {
                cy.log("No deletable properties — skipping");
                return;
            }

            // Open the action dropdown first
            cy.get('[data-bs-toggle="dropdown"]').first().click();
            cy.get(".delete-property-btn").first().click();

            cy.get(".bootbox").should("be.visible");
            cy.get(".bootbox .modal-body").should(
                "contain.text",
                "will also remove all its assignments"
            );

            // Cancel — do not actually delete
            cy.get(".bootbox .btn-secondary").click();
            cy.get(".bootbox").should("not.exist");
        });
    });

    it("confirming delete calls DELETE API and removes the table row", () => {
        // Create a throwaway property definition via direct API call is not available,
        // so we verify the API + row removal indirectly by intercepting the request.
        cy.visit("/PropertyList.php?Type=g");
        cy.waitForLocales();

        cy.get("body").then(($body) => {
            if (!$body.find(".delete-property-btn").length) {
                cy.log("No deletable properties — skipping");
                return;
            }

            cy.intercept("DELETE", "/api/people/properties/definition/*").as("deleteDef");

            cy.get('[data-bs-toggle="dropdown"]').first().click();

            cy.get(".delete-property-btn")
                .first()
                .then(($btn) => {
                    const propertyId = $btn.data("property-id");
                    const rowSelector = `tr:has([data-property-id="${propertyId}"])`;

                    $btn.trigger("click");
                    cy.get(".bootbox .btn-danger").click();

                    cy.wait("@deleteDef").its("response.statusCode").should("eq", 200);
                    cy.get(rowSelector).should("not.exist");
                });
        });
    });
});
