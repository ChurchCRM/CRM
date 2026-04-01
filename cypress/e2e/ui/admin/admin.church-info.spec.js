/// <reference types="cypress" />

describe("Admin - Church Information Page", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should display the church information page with all sections", () => {
        cy.visit("admin/system/church-info");

        // Verify page title
        cy.contains("Church Information", { timeout: 5000 }).should("be.visible");

        // Verify all section headings are present (single page, no tabs)
        cy.contains("Church Identity").should("be.visible");
        cy.contains("Contact Information").should("be.visible");
        cy.contains("Location").should("be.visible");
        cy.contains("Language & Localization").should("be.visible");
        cy.contains("Address Defaults").should("be.visible");
        cy.contains("Display Preview").should("be.visible");
    });

    it("should have required fields marked", () => {
        cy.visit("admin/system/church-info");

        cy.get("#sChurchName").should("have.attr", "required");
        cy.get("#sChurchPhone").should("have.attr", "required");
        cy.get("#sChurchEmail").should("have.attr", "required");
        cy.get("#sChurchAddress").should("have.attr", "required");
        cy.get("#sChurchCity").should("have.attr", "required");
        cy.get("#sChurchZip").should("have.attr", "required");
    });

    it("should display all church identity and contact fields", () => {
        cy.visit("admin/system/church-info");

        cy.get("#sChurchName").should("exist");
        cy.get("#sChurchWebSite").should("exist");
        cy.get("#sChurchPhone").should("exist");
        cy.get("#sChurchEmail").should("exist");
    });

    it("should display all location fields", () => {
        cy.visit("admin/system/church-info");

        cy.get("#sChurchAddress").should("exist");
        cy.get("#sChurchCity").should("exist");
        cy.get("#sChurchStateContainer").should("exist");
        cy.get("#sChurchZip").should("exist");
        cy.get("#sChurchCountry").should("exist");
    });

    it("should display language, timezone, and distance unit fields", () => {
        cy.visit("admin/system/church-info");

        cy.get("#sLanguage").should("exist");
        cy.get("#sTimeZone").should("exist");
        cy.get("#sDistanceUnit").should("exist");
    });

    it("should display address default fields", () => {
        cy.visit("admin/system/church-info");

        cy.get("#sDefaultCity").should("exist");
        cy.get("#sDefaultStateContainer").should("exist");
        cy.get("#sDefaultZip").should("exist");
        cy.get("#sDefaultCountry").should("exist");
    });

    it("should update default state dropdown when default country changes to US", () => {
        cy.visit("admin/system/church-info");

        cy.get("#sDefaultCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");

        cy.tomSelectByValue("#sDefaultCountry", "US");

        cy.get("#sDefaultState", { timeout: 10000 }).siblings(".ts-wrapper").should("exist");
        cy.get("#sDefaultState option").should("have.length.greaterThan", 50);
    });

    it("should update state dropdown when country changes to US", () => {
        cy.visit("admin/system/church-info");

        // Wait for TomSelect to initialize on country dropdown
        cy.get("#sChurchCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");

        // Set country to US
        cy.tomSelectByValue("#sChurchCountry", "US");

        // Wait for state select to be created and TomSelect to initialize
        cy.get("#sChurchState", { timeout: 10000 }).siblings(".ts-wrapper").should("exist");

        // Verify state dropdown is populated with US states
        cy.get("#sChurchState option").should("have.length.greaterThan", 50);
        cy.get("#sChurchState option").contains("Alabama").should("exist");
        cy.get("#sChurchState option").contains("California").should("exist");
    });

    it("should populate provinces when Canada is selected", () => {
        cy.visit("admin/system/church-info");

        cy.get("#sChurchCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");

        cy.tomSelectByValue("#sChurchCountry", "CA");

        cy.get("#sChurchState", { timeout: 10000 }).siblings(".ts-wrapper").should("exist");

        cy.get("#sChurchState option").should("have.length.greaterThan", 1);
        cy.get("#sChurchState option").contains("Alberta").should("exist");
        cy.get("#sChurchState option").contains("Ontario").should("exist");
    });

    it("should show UK constituent countries when United Kingdom is selected", () => {
        cy.visit("admin/system/church-info");

        cy.get("#sChurchCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");

        cy.tomSelectByValue("#sChurchCountry", "GB");

        cy.get("#sChurchState", { timeout: 10000 }).siblings(".ts-wrapper").should("exist");

        cy.get("#sChurchState option").contains("England").should("exist");
        cy.get("#sChurchState option").contains("Scotland").should("exist");
        cy.get("#sChurchState option").contains("Wales").should("exist");
        cy.get("#sChurchState option").contains("Northern Ireland").should("exist");
    });

    it("should show text input for countries without states", () => {
        cy.visit("admin/system/church-info");

        cy.get("#sChurchCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");

        // Select a country that has states first (e.g., US)
        cy.tomSelectByValue("#sChurchCountry", "US");
        cy.get("#sChurchState", { timeout: 10000 }).siblings(".ts-wrapper").should("exist");

        // Clear the country selection
        cy.tomSelectByValue("#sChurchCountry", "");

        // Should fall back to text input
        cy.get("#sChurchStateContainer").find("input[type='text']", { timeout: 5000 }).should("exist");
    });

    it("should have TomSelect initialized for dropdowns", () => {
        cy.visit("admin/system/church-info");

        cy.get("#sChurchCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");
        cy.get("#sLanguage", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");
        cy.get("#sTimeZone", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");
        cy.get("#sDefaultCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");
    });

    it("should copy church address to default fields", () => {
        cy.visit("admin/system/church-info");

        // Wait for page to load
        cy.get("#sChurchCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");

        // Fill church address fields
        cy.get("#sChurchCity").clear().type("Springfield");
        cy.get("#sChurchZip").clear().type("62701");

        // Select church state (country defaults to US on page load)
        cy.get("#sChurchState", { timeout: 10000 }).siblings(".ts-wrapper").should("exist");
        cy.tomSelectByValue("#sChurchState", "IL");
        // Verify state value is actually set before proceeding
        cy.get("#sChurchState").should("have.value", "IL");

        // Click copy button
        cy.get("#copy-church-address").click();

        // Verify defaults were populated
        cy.get("#sDefaultCity").should("have.value", "Springfield");
        cy.get("#sDefaultZip").should("have.value", "62701");
        cy.get("#sDefaultCountry").should("have.value", "US");

        // Default state should now be a dropdown with IL selected
        cy.get("#sDefaultState", { timeout: 10000 }).should("have.value", "IL");
    });

    it("should display the preview section", () => {
        cy.visit("admin/system/church-info");

        cy.contains("Display Preview").should("be.visible");
        cy.contains("This is how your church information will appear on reports and directories").should("be.visible");
    });

    it("should allow saving church information when all required fields are filled", () => {
        cy.visit("admin/system/church-info");

        // Wait for page to fully load — ensure country dropdown is initialized (which populates state)
        cy.get("#sChurchCountry", { timeout: 10000 }).siblings(".ts-wrapper").should("exist");

        // Fill required fields
        cy.get("#sChurchName").clear().type("Test Church");
        cy.get("#sChurchPhone").clear().type("(555) 123-4567");
        cy.get("#sChurchEmail").clear().type("test@example.com");
        cy.get("#sChurchAddress").clear().type("123 Main St");
        cy.get("#sChurchCity").clear().type("Springfield");

        // Country defaults to US on page load — wait for state dropdown to populate
        cy.get("#sChurchState", { timeout: 10000 }).siblings(".ts-wrapper").should("exist");

        // Select state from default US country
        cy.tomSelectByValue("#sChurchState", "IL");

        // Fill ZIP code
        cy.get("#sChurchZip").clear().type("62701");

        // Submit form
        cy.wait(500);
        cy.get("#church-info-form").submit();

        // Verify redirect and success
        cy.url({ timeout: 10000 }).should("include", "church-info");
        cy.contains("Church information saved successfully", { timeout: 10000 }).should("be.visible");
    });

    it("should prevent saving when required church name is empty", () => {
        cy.visit("admin/system/church-info");

        cy.get("#sChurchName").clear();
        cy.get("#church-info-form").submit();

        cy.get("#sChurchName").should("have.attr", "required");
    });

    it("should prevent saving when required address is empty", () => {
        cy.visit("admin/system/church-info");

        cy.get("#sChurchName").clear().type("Test Church");
        cy.get("#sChurchPhone").clear().type("(555) 123-4567");
        cy.get("#sChurchEmail").clear().type("test@example.com");

        cy.get("#sChurchAddress").clear();
        cy.get("#church-info-form").submit();

        cy.get("#sChurchAddress").should("have.attr", "required");
    });
});
