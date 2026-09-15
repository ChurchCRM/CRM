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
        cy.get("#mapAdminSettings", { timeout: 10000 }).should("be.visible");

        cy.get("#mapAdminSettings #settingsPanelSaveBtn").should("not.be.disabled").click();
        cy.wait("@saveConfig").its("response.statusCode").should("eq", 200);

        cy.get("#mapAdminSettings #settingsPanelSaveBtn", { timeout: 10000 })
            .should("not.be.disabled")
            .and("contain.text", "Save Settings");
    });

    it("Map Settings: a failed save re-enables the button and reports the failure", () => {
        cy.intercept("POST", "**/admin/api/system/config/*", {
            statusCode: 500,
            body: { message: "simulated failure" },
        }).as("saveConfigFail");

        cy.visit("people/map");
        cy.contains("Map Settings").click();
        cy.get("#mapAdminSettings", { timeout: 10000 }).should("be.visible");

        cy.get("#mapAdminSettings #settingsPanelSaveBtn").click();
        cy.wait("@saveConfigFail");

        cy.get("#mapAdminSettings #settingsPanelSaveBtn", { timeout: 10000 })
            .should("not.be.disabled")
            .and("contain.text", "Save Settings");
        cy.contains("Failed to save settings").should("exist");
    });
});
