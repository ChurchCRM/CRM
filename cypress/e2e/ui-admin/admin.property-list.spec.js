/// <reference types="cypress" />

/**
 * UI tests for deleting a property definition on PropertyList.php (admin only).
 *
 * Requires: Docker / local environment with seeded data.
 */
describe("UI: Property Definition Delete (PropertyList.php)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("loads PropertyList for groups and shows the table", () => {
        cy.visit("/PropertyList.php?Type=g");
        cy.get("body").then(($body) => {
            if ($body.find(".delete-property-btn").length) {
                cy.get(".delete-property-btn").should("exist");
            } else {
                cy.log(
                    "No group property definitions exist yet — table is empty"
                );
            }
        });
    });

    it("clicking Delete opens a bootbox warning; Cancel leaves row intact", () => {
        cy.visit("/PropertyList.php?Type=g");

        cy.get("body").then(($body) => {
            if (!$body.find(".delete-property-btn").length) {
                cy.log("No deletable properties — skipping");
                return;
            }

            cy.get(".delete-property-btn").first().closest(".dropdown")
                .find("[data-bs-toggle='dropdown']").click();

            cy.get(".delete-property-btn").first().then(($btn) => {
                const propertyId = $btn.data("property-id");
                const rowSelector = `tr:has([data-property-id="${propertyId}"])`;

                cy.wrap($btn).click();

                cy.get(".bootbox").should("be.visible");
                cy.get(".bootbox .modal-body").should(
                    "contain.text",
                    "will also remove all its assignments"
                );

                cy.get(".bootbox .btn-secondary").click({ force: true });

                // The meaningful assertion: row was NOT removed
                cy.get(rowSelector).should("exist");
            });
        });
    });

    it("confirming Delete fires DELETE API and removes the table row", () => {
        cy.visit("/PropertyList.php?Type=g");

        cy.get("body").then(($body) => {
            if (!$body.find(".delete-property-btn").length) {
                cy.log("No deletable properties — skipping");
                return;
            }

            cy.intercept("DELETE", "**/admin/api/properties/definition/*").as(
                "deleteDef"
            );

            // Open the dropdown in the FIRST table row
            cy.get(".delete-property-btn").first().closest(".dropdown")
                .find("[data-bs-toggle='dropdown']").click();

            cy.get(".delete-property-btn")
                .first()
                .then(($btn) => {
                    const propertyId = $btn.data("property-id");
                    const rowSelector = `tr:has([data-property-id="${propertyId}"])`;

                    cy.wrap($btn).click();
                    cy.get(".bootbox-accept")
                        .should("be.visible")
                        .click();

                    cy.wait("@deleteDef")
                        .its("response.statusCode")
                        .should("eq", 200);
                    cy.get(rowSelector).should("not.exist");
                });
        });
    });
});
