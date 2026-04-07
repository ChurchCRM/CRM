/// <reference types="cypress" />

describe("Standard User Settings Page", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Loads settings page with all tabs", () => {
        cy.visit("/v2/user/3");
        cy.contains("Settings");

        // Verify all nav tabs exist
        cy.get('#settingsNav a[href="#tab-account"]').should("be.visible");
        cy.get('#settingsNav a[href="#tab-appearance"]').should("be.visible");
        cy.get('#settingsNav a[href="#tab-localization"]').should("be.visible");
        cy.get('#settingsNav a[href="#tab-api"]').should("be.visible");
        cy.get('#settingsNav a[href="#tab-permissions"]').should("be.visible");

        // Account tab is active by default
        cy.get("#tab-account").should("be.visible");
    });

    it("Shows profile and security on My Account tab", () => {
        cy.visit("/v2/user/3");
        cy.get("#tab-account").within(() => {
            cy.contains("My Account");
            cy.get("#uploadPhotoBtn").should("exist");
            cy.contains("Security");
            cy.get("#editSettings").should("exist");
        });
    });

    it("Navigates to Appearance tab and shows all controls", () => {
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-appearance"]').click();
        cy.get("#tab-appearance").should("be.visible");

        // Theme mode radios
        cy.get("#themeModeLight").should("exist");
        cy.get("#themeModeDark").should("exist");

        // Primary color swatches
        cy.get("#primaryColorPicker .btn-color-swatch").should(
            "have.length.gte",
            10,
        );

        // Layout controls
        cy.get("#boxedLayout").should("exist");
        cy.get("#toggleSidebar").should("exist");
        cy.get("#navbarOverlap").should("exist");

        // Table settings
        cy.get("#tablePageLength").should("exist");
    });

    it("Navigates to Localization tab and shows locale controls", () => {
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-localization"]').click();
        cy.get("#tab-localization").should("be.visible");

        cy.get("#user-locale-setting").should("exist");
        cy.get("#tab-localization").contains("Help Improve Translations");
        cy.get("#tab-localization")
            .find('a[href*="poeditor.com"]')
            .should("exist");
    });

    it("Navigates to API Access tab and shows API key", () => {
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-api"]').click();
        cy.get("#tab-api").should("be.visible");

        cy.get("#apiKey").should("exist");
        cy.get("#regenApiKey").should("exist");
        cy.get("#tab-api").contains("x-api-key");
    });

    it("Navigates to Permissions tab and shows permissions", () => {
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-permissions"]').click();
        cy.get("#tab-permissions").should("be.visible");
        cy.get("#tab-permissions").contains("Permissions");
    });

    it("Can toggle dark mode", () => {
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-appearance"]').click();

        // Enable dark mode
        cy.get("#themeModeDark").check({ force: true });
        cy.get("html").should("have.attr", "data-bs-theme", "dark");

        // Switch back to light
        cy.get("#themeModeLight").check({ force: true });
        cy.get("html").should("not.have.attr", "data-bs-theme");
    });

    it("Can select a primary color", () => {
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-appearance"]').click();

        cy.get('#primaryColorPicker .btn-color-swatch[data-color="purple"]').click();
        cy.get("html").should("have.attr", "data-bs-theme-primary", "purple");

        // Reset to default
        cy.get('#primaryColorPicker .btn-color-swatch[data-color=""]').click();
        cy.get("html").should("not.have.attr", "data-bs-theme-primary");
    });

    it("Advanced Settings link goes to legacy settings page", () => {
        cy.visit("/v2/user/3");
        cy.get("#editSettings")
            .should("have.attr", "href")
            .and("contain", "SettingsIndividual.php");
    });
});
