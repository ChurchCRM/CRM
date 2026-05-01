/// <reference types="cypress" />

describe("Standard User Settings Page", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Loads settings page with all tabs", () => {
        cy.visit("/v2/user/3");
        cy.get("h2.page-title").contains("Settings");

        cy.get('#settingsNav a[href="#tab-account"]').should("be.visible");
        cy.get('#settingsNav a[href="#tab-appearance"]').should("be.visible");
        cy.get('#settingsNav a[href="#tab-localization"]').should("be.visible");
        cy.get('#settingsNav a[href="#tab-api"]').should("be.visible");
        cy.get('#settingsNav a[href="#tab-permissions"]').should("be.visible");

        cy.get("#tab-account").should("be.visible");
    });

    it("Shows profile and security on My Account tab", () => {
        cy.visit("/v2/user/3");
        cy.get("#tab-account").within(() => {
            cy.contains("My Account");
            cy.get("#userAvatar").should("exist");
            cy.get("#uploadPhotoBtn").should("exist");
            cy.contains("Username");
            cy.contains("Name");
            cy.contains("Email");
            cy.contains("Security");
            cy.get("#editSettings")
                .should("have.attr", "href")
                .and("contain", "SettingsIndividual.php");
        });
    });

    it("Navigates to Appearance tab and shows all controls", () => {
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-appearance"]').click();
        cy.get("#tab-appearance").should("be.visible");

        cy.get("#themeModeLight").should("exist");
        cy.get("#themeModeDark").should("exist");

        cy.get("#primaryColorPicker .btn-color-swatch").should(
            "have.length.gte",
            10,
        );

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

    it("Populates locale dropdown grouped by region with native names", () => {
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-localization"]').click();
        cy.get("#tab-localization").should("be.visible");

        // Wait for async populateLocaleDropdown to complete
        cy.get("#user-locale-setting optgroup", { timeout: 8000 }).should("have.length.greaterThan", 2);

        // Region groups must be present
        cy.get("#user-locale-setting optgroup[label='Americas']").should("exist");
        cy.get("#user-locale-setting optgroup[label='Europe']").should("exist");

        // Options must include locale code in brackets
        cy.get("#user-locale-setting optgroup[label='Europe'] option").first()
            .invoke("text")
            .should("match", /\[.+\]$/);

        // English (US) base locale must be present
        cy.get("#user-locale-setting option[value='en_US']").should("exist");
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

    it("Can toggle dark mode and it applies live", () => {
        cy.intercept("POST", "**/api/user/*/setting/ui.style").as("saveStyle");
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-appearance"]').click();

        cy.get("#themeModeDark").check({ force: true });
        cy.wait("@saveStyle");
        cy.get("html").should("have.attr", "data-bs-theme", "dark");

        cy.intercept("POST", "**/api/user/*/setting/ui.style").as("resetStyle");
        cy.get("#themeModeLight").check({ force: true });
        cy.wait("@resetStyle");
        cy.get("html").should("not.have.attr", "data-bs-theme");
    });

    it("Persists dark mode selection after reload", () => {
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-appearance"]').click();

        // Force to light first (no-op if already light, resets if dark).
        // Set up the dark intercept AFTER this click so we only capture the
        // intentional dark save — not an accidental light-reset API call.
        cy.get("#themeModeLight").check({ force: true });

        // Now switch to dark and wait for the API save
        cy.intercept("POST", "**/api/user/*/setting/ui.style").as("saveDark");
        cy.get("#themeModeDark").check({ force: true });
        cy.wait("@saveDark");

        // Reload and verify dark persisted
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-appearance"]').click();
        cy.get("#themeModeDark").should("be.checked");
        cy.get("#themeModeLight").should("not.be.checked");

        // Reset to light
        cy.intercept("POST", "**/api/user/*/setting/ui.style").as("resetStyle");
        cy.get("#themeModeLight").check({ force: true });
        cy.wait("@resetStyle");
    });

    it("Can select a primary color and it applies live", () => {
        cy.intercept("POST", "**/api/user/*/setting/ui.theme.primary").as("saveColor");
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-appearance"]').click();

        cy.get('#primaryColorPicker .btn-color-swatch[data-color="purple"]').click();
        cy.wait("@saveColor");
        cy.get("html").should("have.attr", "data-bs-theme-primary", "purple");
        cy.get('#primaryColorPicker .btn-color-swatch[data-color="purple"]').should(
            "have.class",
            "active",
        );

        // Reset to default
        cy.intercept("POST", "**/api/user/*/setting/ui.theme.primary").as("resetColor");
        cy.get('#primaryColorPicker .btn-color-swatch[data-color=""]').click();
        cy.wait("@resetColor");
        cy.get("html").should("not.have.attr", "data-bs-theme-primary");
    });

    it("Can change table page length", () => {
        // Intercepts before cy.visit() so no request can be missed
        cy.intercept("POST", "**/api/user/*/setting/ui.table.size").as("baselineSize");
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-appearance"]').click();

        // Ensure we start from "10" so selecting "50" always fires a change event
        cy.get("#tablePageLength").then(($sel) => {
            if ($sel.val() !== "10") {
                cy.wrap($sel).select("10");
                cy.wait("@baselineSize");
            }
        });

        cy.intercept("POST", "**/api/user/*/setting/ui.table.size").as("saveTableSize");
        cy.get("#tablePageLength").select("50");
        cy.wait("@saveTableSize");

        // Verify the select value persists on reload
        cy.visit("/v2/user/3");
        cy.get('#settingsNav a[href="#tab-appearance"]').click();
        cy.get("#tablePageLength").should("have.value", "50");

        // Reset — must also wait so the next test starts from a clean state
        cy.intercept("POST", "**/api/user/*/setting/ui.table.size").as("resetTableSize");
        cy.get("#tablePageLength").select("10");
        cy.wait("@resetTableSize");
    });
});
