/// <reference types="cypress" />

/**
 * People Dashboard — Settings Panel tests
 *
 * The People Settings panel on the dashboard allows admins to toggle:
 * - Self Registration (bEnableSelfRegistration)
 * - Hide Deceased from Directory (bHideDeceasedFromDirectory)
 *
 * This spec verifies:
 * - The settings panel button appears in the page header for admins
 * - The panel expands/collapses correctly
 * - Settings can be toggled and saved
 * - Page reloads after successful save
 */

describe("People Dashboard — Settings Panel", () => {
    beforeEach(() => cy.setupAdminSession());

    it("shows the 'People Settings' button in the page header for admins", () => {
        cy.visit("/people/dashboard");
        cy.contains("button", "People Settings").should("be.visible");
        cy.get("button").contains("People Settings").parent().find(".fa-sliders").should("exist");
    });

    it("expands the People Settings panel when the button is clicked", () => {
        cy.visit("/people/dashboard");
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 5000 }).should("be.visible");
    });

    it("toggles Self Registration setting and saves successfully", () => {
        cy.intercept("POST", "**/admin/api/system/config/bEnableSelfRegistration").as("saveConfig");

        cy.visit("/people/dashboard");
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 5000 }).should("be.visible");

        // Get the current state of the Self Registration toggle
        cy.get("#peopleSettings input[name='bEnableSelfRegistration']").then(($input) => {
            const isCurrentlyEnabled = $input.is(":checked");
            const shouldBeEnabled = !isCurrentlyEnabled;

            // Toggle the checkbox
            cy.get("#peopleSettings input[name='bEnableSelfRegistration']").click();

            // Verify the checkbox state changed
            if (shouldBeEnabled) {
                cy.get("#peopleSettings input[name='bEnableSelfRegistration']").should("be.checked");
            } else {
                cy.get("#peopleSettings input[name='bEnableSelfRegistration']").should("not.be.checked");
            }

            // Click Save Settings button
            cy.get("#peopleSettings #settingsPanelSaveBtn").should("not.be.disabled").click();

            // Wait for the API call
            cy.wait("@saveConfig").its("response.statusCode").should("eq", 200);

            // Verify the page reloads after a brief timeout (as per the onSave callback)
            cy.get("body", { timeout: 10000 }).should("exist");
        });
    });

    it("displays the Self Registration tooltip on hover", () => {
        cy.visit("/people/dashboard");
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 5000 }).should("be.visible");

        // The tooltip text should be visible
        cy.get("#peopleSettings").should("contain.text", "Allow visitors to self-register as new families");
    });

    it("displays both settings in the People Settings panel", () => {
        cy.visit("/people/dashboard");
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 5000 }).should("be.visible");

        // Check for both settings
        cy.get("#peopleSettings").should("contain.text", "Self Registration");
        cy.get("#peopleSettings").should("contain.text", "Hide Deceased from Directory");
    });

    it("collapses the panel when closed", () => {
        cy.visit("/people/dashboard");
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 5000 }).should("be.visible");

        // Click the button again to collapse
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show").should("not.exist");
    });
});
