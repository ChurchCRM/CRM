/// <reference types="cypress" />

/**
 * UI tests for group property assignment/removal on /groups/view/{id}
 * and property definition deletion on PropertyList.php.
 *
 * Requires: Docker / local environment with seeded data.
 * - Group with ID=1 must exist
 * - At least one group property definition must exist (pro_Class='g')
 *
 * Design rule: NO cy.request() / makePrivateAdminAPICall() calls after the
 * user is logged in. All API-based data setup runs BEFORE freshAdminLogin(),
 * and any teardown runs at the END of the test (after UI assertions).
 */

/**
 * Direct login helper — bypasses cy.session() cache entirely.
 * Call this after all API setup is complete; it clears cookies so the
 * PHP session created by cy.request() is discarded and a real browser
 * session is established instead.
 */
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(
        Cypress.env("admin.password") + "{enter}"
    );
    cy.url().should("not.include", "/session/begin");
}

// ------------------------------------------------------------------ //
// Group Property Assignment — /groups/view/{id}
// ------------------------------------------------------------------ //
describe("UI: Group Property Assignment (/groups/view/{id})", () => {
    const groupID = 1;

    // ------------------------------------------------------------------
    // Page-load tests — no data setup needed, use session cache
    // ------------------------------------------------------------------
    describe("Page load", () => {
        beforeEach(() => {
            cy.setupAdminSession();
        });

        it("loads GroupView and shows the Properties sidebar card", () => {
            cy.visit(`/groups/view/${groupID}`);
            cy.contains("h3.card-title", "Properties").should("be.visible");
        });

        it("shows the assign select and button when unassigned properties exist", () => {
            cy.visit(`/groups/view/${groupID}`);
            cy.get("body").then(($body) => {
                if ($body.find("#group-property-select").length) {
                    cy.get("#group-property-select").should("be.visible");
                    cy.get("#assign-group-property-btn").should("be.visible");
                } else {
                    cy.log(
                        "All group properties already assigned — skipping"
                    );
                }
            });
        });
    });

    // ------------------------------------------------------------------
    // Assign a property — API setup BEFORE login
    // ------------------------------------------------------------------
    describe("Assign a property", () => {
        it("assigns a no-prompt property and it appears in the list", () => {
            // Step 1: API setup (pre-login — PHP session resets don't matter)
            cy.wrap(null).as("prop");

            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${groupID}/properties`,
                null,
                200
            ).then((resp) => {
                resp.body.forEach((p) =>
                    cy.makePrivateAdminAPICall(
                        "DELETE",
                        `/api/groups/${groupID}/properties/${p.id}`,
                        null,
                        [200, 404]
                    )
                );
            });

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
                    cy.log(
                        "No prompt-free group property definition — skipping"
                    );
                    return;
                }
                cy.wrap({
                    id: noprompt.ProId ?? noprompt.pro_ID,
                    name: noprompt.ProName ?? noprompt.pro_Name,
                }).as("prop");
            });

            // Step 2: Login after all API setup
            freshAdminLogin();

            // Step 3: UI only from here
            cy.get("@prop").then((prop) => {
                if (!prop) return;
                cy.visit(`/groups/view/${groupID}`);
                cy.get("#group-property-select").select(String(prop.id));
                cy.get("#assign-group-property-btn").click();
                cy.get("#group-properties-card").contains(prop.name).should("be.visible");
            });

            // Step 4: Cleanup at end
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${groupID}/properties`,
                null,
                200
            ).then((resp) => {
                resp.body.forEach((p) =>
                    cy.makePrivateAdminAPICall(
                        "DELETE",
                        `/api/groups/${groupID}/properties/${p.id}`,
                        null,
                        [200, 404]
                    )
                );
            });
        });
    });

    // ------------------------------------------------------------------
    // Remove an assigned property — API setup BEFORE login each time
    // ------------------------------------------------------------------
    describe("Remove an assigned property", () => {
        beforeEach(() => {
            // API setup: ensure at least one property is assigned
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/groups/properties",
                null,
                200
            ).then((resp) => {
                if (!resp.body.length) {
                    cy.log("No group property definitions — skipping setup");
                    return;
                }
                const propId = resp.body[0].ProId ?? resp.body[0].pro_ID;
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/groups/${groupID}/properties/${propId}`,
                    {},
                    [200, 409]
                );
            });

            // Login AFTER API setup
            freshAdminLogin();
        });

        afterEach(() => {
            // Teardown: clear all assignments
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${groupID}/properties`,
                null,
                200
            ).then((resp) => {
                resp.body.forEach((p) =>
                    cy.makePrivateAdminAPICall(
                        "DELETE",
                        `/api/groups/${groupID}/properties/${p.id}`,
                        null,
                        [200, 404]
                    )
                );
            });
        });

        it("shows a Remove button for each assigned property", () => {
            cy.visit(`/groups/view/${groupID}`);
            cy.get(".remove-group-property-btn").should("have.length.gte", 1);
        });

        it("clicking Remove opens a bootbox confirm; Cancel leaves property intact", () => {
            cy.visit(`/groups/view/${groupID}`);

            cy.get(".remove-group-property-btn").first().then(($btn) => {
                const name = $btn.data("pro-name");

                cy.wrap($btn).click();
                cy.get(".bootbox").should("be.visible");

                // Click cancel — dismiss without confirming
                cy.get(".bootbox .btn-secondary").click({ force: true });

                // The meaningful assertion: property was NOT removed
                cy.get(`.remove-group-property-btn[data-pro-name="${name}"]`).should("exist");
            });
        });

        it("confirming Remove fires DELETE and the property disappears", () => {
            cy.visit(`/groups/view/${groupID}`);

            cy.intercept(
                "DELETE",
                `**/api/groups/${groupID}/properties/*`
            ).as("removeProperty");

            cy.get(".remove-group-property-btn")
                .first()
                .then(($btn) => {
                    const name = $btn.data("pro-name");

                    cy.wrap($btn).click();
                    cy.get(".bootbox-accept")
                        .should("be.visible")
                        .click();

                    cy.wait("@removeProperty")
                        .its("response.statusCode")
                        .should("eq", 200);
                    cy.get(
                        `.remove-group-property-btn[data-pro-name="${name}"]`
                    ).should("not.exist");
                });
        });
    });

    // ------------------------------------------------------------------
    // Edit a property value — observational only, no data setup needed
    // ------------------------------------------------------------------
    describe("Edit a property value (has prompt)", () => {
        beforeEach(() => {
            cy.setupAdminSession();
        });

        it("Edit Value button opens a bootbox prompt; Cancel dismisses it", () => {
            cy.visit(`/groups/view/${groupID}`);

            cy.get("body").then(($body) => {
                if (!$body.find(".edit-group-property-btn").length) {
                    cy.log(
                        "No editable (prompt-based) properties assigned — skipping"
                    );
                    return;
                }
                cy.get(".edit-group-property-btn").first().click();
                cy.get(".bootbox").should("be.visible");
                cy.get(".bootbox .btn-secondary").click({ force: true });
                cy.get(".bootbox").should("not.be.visible");
            });
        });
    });
});

// ------------------------------------------------------------------ //
// Property Definition Delete — PropertyList.php
// ------------------------------------------------------------------ //
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

            cy.intercept("DELETE", "**/api/people/properties/definition/*").as(
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
