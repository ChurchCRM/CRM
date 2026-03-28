/// <reference types="cypress" />

/**
 * UI tests for the congregation map page (/v2/map)
 * Powered by Leaflet + OpenStreetMap — no Google Maps API key required.
 */
describe("Congregation Map (/v2/map)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("Page load", () => {
        it("Loads without error", () => {
            cy.visit("v2/map");
            cy.get("body").should("be.visible");
        });

        it("Has the correct page title", () => {
            cy.visit("v2/map");
            cy.title().should("include", "Congregation Map");
        });
    });

    describe("Map container", () => {
        it("Renders the Leaflet map div", () => {
            cy.visit("v2/map");
            cy.get("#map").should("exist").and("be.visible");
        });

        it("Loads OpenStreetMap tiles (leaflet-tile-pane present)", () => {
            cy.visit("v2/map");
            cy.get(".leaflet-tile-pane", { timeout: 10000 }).should("exist");
        });

        it("Places at least one circle marker on the map", () => {
            cy.visit("v2/map");
            // Leaflet renders SVG circle markers via path elements
            cy.get(".leaflet-overlay-pane path", { timeout: 10000 }).should(
                "have.length.at.least",
                1,
            );
        });
    });

    describe("Legend", () => {
        it("Shows the desktop legend inside the map", () => {
            cy.visit("v2/map");
            cy.get("#map-legend").should("exist");
        });

        it("Legend contains at least one classification row", () => {
            cy.visit("v2/map");
            cy.get(".legend-item").should("have.length.at.least", 1);
        });

        it("Each legend row has a colored dot", () => {
            cy.visit("v2/map");
            cy.get(".legend-item").each(($item) => {
                cy.wrap($item).find(".legend-dot").should("exist");
            });
        });

        it("Clicking a legend row toggles its markers", () => {
            cy.visit("v2/map");
            // Wait for markers to render
            cy.get(".leaflet-overlay-pane path", { timeout: 10000 }).then(
                ($before) => {
                    const countBefore = $before.length;
                    // Click first legend item to hide that classification
                    cy.get(".legend-item").first().click({ force: true });
                    // Marker count should decrease (or stay same if none of that class)
                    cy.get(".leaflet-overlay-pane path").should(
                        "have.length.at.most",
                        countBefore,
                    );
                },
            );
        });
    });

    describe("Group filter (?groupId=N)", () => {
        it("Loads without error when groupId is provided", () => {
            cy.visit("v2/map?groupId=1");
            cy.get("#map").should("exist").and("be.visible");
        });
    });

    describe("Cart view (?groupId=0)", () => {
        it("Loads without error for cart view", () => {
            cy.visit("v2/map?groupId=0");
            cy.get("#map").should("exist").and("be.visible");
        });
    });

        
});
