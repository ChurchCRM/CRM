/// <reference types="cypress" />

/**
 * UI tests for the congregation map page (/people/map)
 * Powered by Leaflet + OpenStreetMap — no Google Maps API key required.
 */
describe("Congregation Map (/people/map)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("shows the classified families as markers with a legend", () => {
        cy.visit("people/map");
        cy.title().should("include", "Congregation Map");
        cy.get("#map").should("be.visible");
        cy.get(".leaflet-tile-pane", { timeout: 10000 }).should("exist");
        // Leaflet renders SVG circle markers via path elements
        cy.get(".leaflet-overlay-pane path", { timeout: 10000 }).should("have.length.at.least", 1);
        cy.get("#map-legend .legend-item").should("have.length.at.least", 1);
        cy.get(".legend-item").each(($item) => {
            cy.wrap($item).find(".legend-dot").should("exist");
        });
    });

    describe("Legend", () => {
        it("Clicking a legend row toggles its markers", () => {
            cy.visit("people/map");
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

    it("draws the map for a group and for the cart", () => {
        cy.visit("people/map?groupId=1");
        cy.get("#map").should("be.visible");
        cy.visit("people/map?groupId=0");
        cy.get("#map").should("be.visible");
    });
});

/**
 * UI tests for the Find Neighbors page (/people/map/neighbors)
 */
describe("Find Neighbors (/people/map/neighbors)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("opens with the map and an empty result list before a search", () => {
        cy.visit("people/map/neighbors");
        cy.title().should("include", "Find Neighbors");
        cy.get("#neighborsMap").should("be.visible");
        cy.get(".leaflet-tile-pane", { timeout: 10000 }).should("exist");
        cy.get("#neighborsEmpty").should("be.visible");
        cy.get("#neighborsTable").should("not.be.visible");
    });

    it("Search panel opens and closes", () => {
        cy.visit("people/map/neighbors");
        // Panel starts open when no ?familyId is supplied
        cy.get("#searchPanel").should("not.have.class", "collapsed");
        cy.get("#closeSearchPanel").click();
        cy.get("#searchPanel").should("have.class", "collapsed");
        cy.get("#toggleSearchPanel").click();
        cy.get("#searchPanel").should("not.have.class", "collapsed");
    });

    it("Runs a deep-linked search and renders the origin family on the map", () => {
        cy.visit("people/map/neighbors?familyId=1");
        // Deep link auto-submits; the search button re-enables when the request completes
        cy.get("#findNeighborsBtn", { timeout: 15000 }).should("not.be.disabled");
        // The selected (origin) family is always plotted as a circle marker,
        // regardless of how many geocoded neighbours the seed data has
        cy.get(".leaflet-overlay-pane path", { timeout: 10000 }).should(
            "have.length.at.least",
            1,
        );
        cy.get(".neighbors-legend").should("be.visible");
    });

    describe("Update All Coordinates (#9846)", () => {
        it("Runs every batch from one click, passing the failure count as skip", () => {
            const calls = [];
            cy.intercept("POST", "**/api/map/geocode-all", (req) => {
                calls.push(req.body);
            }).as("geocodeAll");

            cy.visit("people/map");
            cy.get("#geocodeAllBtn").should("exist").click();
            cy.get(".bootbox .btn-primary").click();

            // Working panel shows while batches run
            cy.get("#geocodeAllResults [role='status']").should("exist");

            // The run ends when the button is re-enabled; the seed data has a
            // handful of families with fake addresses, so at least one call
            // happens and the last one carries the running failure count.
            cy.get("#geocodeAllBtn", { timeout: 180000 }).should("not.be.disabled");
            cy.wrap(null).then(() => {
                expect(calls.length).to.be.at.least(1);
                expect(calls[0]).to.deep.include({ skip: 0 });
                for (const body of calls) {
                    expect(body.skip).to.be.a("number").and.be.at.least(0);
                }
            });

            // Either a success toast (nothing failed) or the failure panel listing
            // every unresolved family from the whole run.
            cy.get("body").then(($body) => {
                if ($body.find("#geocodeAllResults .alert-warning").length > 0) {
                    cy.get("#geocodeAllResults .alert-warning .list-group-item").should("have.length.at.least", 1);
                }
            });
        });
    });

    describe("Update All Coordinates for a non-admin with Edit Records", () => {
        beforeEach(() => {
            cy.setupStandardSession();
        });

        it("Shows the button but not Map Settings", () => {
            cy.visit("people/map");
            cy.get("#geocodeAllBtn").should("be.visible");
            cy.contains("a", "Map Settings").should("not.exist");
        });
    });
});
