/// <reference types="cypress" />

describe("Admin - Localization & Formats Page", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should display the localization page with all sections", () => {
        cy.visit("admin/system/localization");

        cy.contains("Localization & Formats", { timeout: 5000 }).should("be.visible");
        cy.contains("Language & Region").should("be.visible");
        cy.contains("Date & Time Formats").should("be.visible");
        cy.contains("Phone Number Formats").should("be.visible");
        cy.contains("Display Preview").should("be.visible");
    });

    it("should display language, timezone, and distance unit fields", () => {
        cy.visit("admin/system/localization");

        cy.get("#sLanguage").should("exist");
        cy.get("#sTimeZone").should("exist");
        cy.get("#sDistanceUnit").should("exist");
    });

    it("should display all date & time format fields", () => {
        cy.visit("admin/system/localization");

        cy.get("#sDateFormatLong").should("exist");
        cy.get("#sDateFormatNoYear").should("exist");
        cy.get("#sDateTimeFormat").should("exist");
        cy.get("#sDateFilenameFormat").should("exist");
        cy.get("#sDatePickerFormat").should("exist");
        cy.get("#sDatePickerPlaceHolder").should("exist");
    });

    it("should display all phone number format fields", () => {
        cy.visit("admin/system/localization");

        cy.get("#sPhoneFormat").should("exist");
        cy.get("#sPhoneFormatCell").should("exist");
        cy.get("#sPhoneFormatWithExt").should("exist");
    });

    it("should have TomSelect initialized for language and timezone", () => {
        cy.visit("admin/system/localization");

        cy.get("#sLanguage", { timeout: 8000 }).siblings(".ts-wrapper").should("exist");
        cy.get("#sTimeZone", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");
    });

    it("should populate language dropdown grouped by region with native names", () => {
        cy.visit("admin/system/localization");

        // Wait for JS to populate and TomSelect to init
        cy.get("#sLanguage", { timeout: 8000 }).siblings(".ts-wrapper").should("exist");

        // Underlying <select> must have region optgroups
        cy.get("#sLanguage optgroup").should("have.length.greaterThan", 2);
        cy.get("#sLanguage optgroup[label='Americas']").should("exist");
        cy.get("#sLanguage optgroup[label='Europe']").should("exist");

        // Options must include locale code in brackets
        cy.get("#sLanguage optgroup[label='Americas'] option").first()
            .invoke("text")
            .should("match", /\[.+\]$/);

        // English (US) base locale must be present
        cy.get("#sLanguage option[value='en_US']").should("exist");
    });

    it("should show a live date preview when a date format is entered", () => {
        cy.visit("admin/system/localization");

        cy.get("#sDateFormatLong", { timeout: 5000 }).clear().type("Y-m-d");
        // The consolidated Display Preview mirrors the input value.
        cy.get(".format-preview-value[data-source='sDateFormatLong']")
            .invoke("text")
            .should("match", /^\d{4}-\d{2}-\d{2}$/);
    });

    it("should show a live phone preview when a phone format is entered", () => {
        cy.visit("admin/system/localization");

        cy.get("#sPhoneFormat", { timeout: 5000 }).clear().type("(999) 999-9999");
        cy.get(".format-preview-value[data-source='sPhoneFormat']")
            .invoke("text")
            .should("match", /^\(\d{3}\) \d{3}-\d{4}$/);
    });

    it("should allow saving localization settings", () => {
        cy.visit("admin/system/localization");

        cy.get("#sDistanceUnit", { timeout: 5000 }).select("kilometers");
        cy.get("#localization-form").submit();

        cy.url({ timeout: 10000 }).should("include", "localization");
        cy.contains("Localization settings saved successfully", { timeout: 10000 }).should("be.visible");
    });
});
