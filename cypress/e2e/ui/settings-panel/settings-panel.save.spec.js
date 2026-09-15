/// <reference types="cypress" />

/**
 * Settings Panel — Save button lifecycle (#9852)
 *
 * The shared settings panel disables its Save button while the config POSTs
 * run. Panels whose onSave callback reloads the page hid the fact that the
 * button was never re-enabled on success; the Map Settings panel stays on the
 * page, so it is the natural place to assert the button comes back.
 */
describe("Settings Panel — Save button", () => {
    beforeEach(() => cy.setupAdminSession());

    it("Map Settings: Save re-enables the button after a successful save", () => {
        cy.intercept("POST", "**/admin/api/system/config/*").as("saveConfig");

        cy.visit("people/map");
        cy.contains("Map Settings").click();
        cy.get("#mapAdminSettings", { timeout: 10000 }).should("have.class", "show").and("not.have.class", "collapsing");

        cy.get("#mapAdminSettings #settingsPanelSaveBtn").should("not.be.disabled").click();
        cy.wait("@saveConfig").its("response.statusCode").should("eq", 200);

        cy.get("#mapAdminSettings #settingsPanelSaveBtn", { timeout: 10000 })
            .should("not.be.disabled")
            .and("contain.text", "Save Settings");

        // The pane collapses after a successful save, like clicking Map Settings again
        cy.get("#mapAdminSettings", { timeout: 10000 }).should("not.be.visible");
    });

    it("Map Settings: a failed save re-enables the button and reports the failure", () => {
        cy.intercept("POST", "**/admin/api/system/config/*", {
            statusCode: 500,
            body: { message: "simulated failure" },
        }).as("saveConfigFail");

        cy.visit("people/map");
        cy.contains("Map Settings").click();
        cy.get("#mapAdminSettings", { timeout: 10000 }).should("have.class", "show").and("not.have.class", "collapsing");

        cy.get("#mapAdminSettings #settingsPanelSaveBtn").click();
        cy.wait("@saveConfigFail");

        cy.get("#mapAdminSettings #settingsPanelSaveBtn", { timeout: 10000 })
            .should("not.be.disabled")
            .and("contain.text", "Save Settings");
        cy.contains("Failed to save settings").should("exist");

        // Nothing was saved, so the pane stays open for another attempt
        cy.get("#mapAdminSettings").should("be.visible");
    });

    it("Map Settings: a saved default zoom is applied to the map without a reload", () => {
        cy.intercept("POST", "**/admin/api/system/config/*").as("saveConfig");

        cy.visit("people/map");
        cy.get(".leaflet-tile-pane img", { timeout: 10000 }).should("exist");
        cy.contains("Map Settings").click();
        cy.get("#mapAdminSettings", { timeout: 10000 }).should("have.class", "show").and("not.have.class", "collapsing");

        // Pick a zoom level different from the default (10) and save
        cy.get("#mapAdminSettings select[name='iMapZoom']").select("14");
        cy.get("#mapAdminSettings #settingsPanelSaveBtn").click();
        cy.wait("@saveConfig");

        // Tile URLs carry the zoom level: /{z}/{x}/{y}.png — no cy.reload() here
        cy.get(".leaflet-tile-pane img[src*='/14/']", { timeout: 10000 }).should("exist");

        // Restore the default so other specs see the seeded value
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/iMapZoom", { value: "10" }, 200);
    });
});
