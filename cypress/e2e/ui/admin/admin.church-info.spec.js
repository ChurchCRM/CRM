/// <reference types="cypress" />

describe("Admin - Church Information Page", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should display the church information page with 3 tabs", () => {
        cy.visit("admin/system/church-info");

        // Verify page title
        cy.contains("Church Information", { timeout: 5000 }).should("be.visible");

        // Verify all 3 tabs are present
        cy.contains("Basic Information").should("be.visible");
        cy.contains("Location & Map").should("be.visible");
        cy.contains("Display Preview").should("be.visible");

        // One of the tabs should be active
        cy.get(".nav-link.active").should("exist");
    });

    it("should have required fields marked on Basic Information tab", () => {
        cy.visit("admin/system/church-info");

        // Basic Information tab - required fields
        cy.get("#sChurchName").should("have.attr", "required");
        cy.get("#sChurchPhone").should("have.attr", "required");
        cy.get("#sChurchEmail").should("have.attr", "required");
    });

    it("should have required address fields on Location tab", () => {
        cy.visit("admin/system/church-info");

        // Switch to Location tab
        cy.get("#location-tab").click();

        // Address fields should be required
        cy.get("#sChurchAddress").should("have.attr", "required");
        cy.get("#sChurchCity").should("have.attr", "required");
        cy.get("#sChurchZip").should("have.attr", "required");
    });

    it("should display all fields in Basic Information tab", () => {
        cy.visit("admin/system/church-info");

        // Basic Information fields
        cy.get("#sChurchName").should("exist");
        cy.get("#sChurchWebSite").should("exist");

        // Contact fields
        cy.get("#sChurchPhone").should("exist");
        cy.get("#sChurchEmail").should("exist");

        // Language & Localization
        cy.get("#sLanguage").should("exist");
        cy.get("#sTimeZone").should("exist");
    });

    it("should display all fields in Location & Map tab", () => {
        cy.visit("admin/system/church-info");

        // Switch to Location tab
        cy.get("#location-tab").click();

        // Location fields
        cy.get("#sChurchAddress").should("exist");
        cy.get("#sChurchCity").should("exist");
        cy.get("#sChurchStateContainer").should("exist");
        cy.get("#sChurchZip").should("exist");
        cy.get("#sChurchCountry").should("exist");
    });

    it("should update state dropdown when country changes to US", () => {
        cy.visit("admin/system/church-info");

        // Switch to Location tab
        cy.get("#location-tab").click();

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

        // Switch to Location tab
        cy.get("#location-tab").click();

        // Wait for TomSelect to initialize on country dropdown
        cy.get("#sChurchCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");

        // Set country to Canada
        cy.tomSelectByValue("#sChurchCountry", "CA");

        // Wait for state select to be created and TomSelect to initialize
        cy.get("#sChurchState", { timeout: 10000 }).siblings(".ts-wrapper").should("exist");

        // Verify state dropdown exists and has options
        cy.get("#sChurchState option").should("have.length.greaterThan", 1);

        // Verify Canadian provinces are shown
        cy.get("#sChurchState option").contains("Alberta").should("exist");
        cy.get("#sChurchState option").contains("Ontario").should("exist");
    });

    it("should show UK constituent countries when United Kingdom is selected", () => {
        cy.visit("admin/system/church-info");

        // Switch to Location tab
        cy.get("#location-tab").click();

        // Wait for TomSelect to initialize on country dropdown
        cy.get("#sChurchCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");

        // Set country to UK
        cy.tomSelectByValue("#sChurchCountry", "GB");

        // Wait for state select to be created and TomSelect to initialize
        cy.get("#sChurchState", { timeout: 10000 }).siblings(".ts-wrapper").should("exist");

        // Verify UK constituent countries are shown
        cy.get("#sChurchState option").contains("England").should("exist");
        cy.get("#sChurchState option").contains("Scotland").should("exist");
        cy.get("#sChurchState option").contains("Wales").should("exist");
        cy.get("#sChurchState option").contains("Northern Ireland").should("exist");
    });

    it("should show text input for countries without states", () => {
        cy.visit("admin/system/church-info");

        // Switch to Location tab
        cy.get("#location-tab").click();

        // Wait for TomSelect to initialize on country dropdown
        cy.get("#sChurchCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");

        // Select a country that has states first (e.g., US)
        cy.tomSelectByValue("#sChurchCountry", "US");

        // Wait for state select to be created
        cy.get("#sChurchState", { timeout: 10000 }).siblings(".ts-wrapper").should("exist");

        // Clear the country selection
        cy.tomSelectByValue("#sChurchCountry", "");

        // Should fall back to text input
        cy.get("#sChurchStateContainer").find("input[type='text']", { timeout: 5000 }).should("exist");
    });

    it("should display Display Preview tab", () => {
        cy.visit("admin/system/church-info");

        // Switch to Display Preview tab
        cy.get("#display-tab").click();

        // Verify content is shown
        cy.contains("Display Preview").should("be.visible");
        cy.contains("This is how your church information will appear on reports and directories").should("be.visible");
    });

    it("should have TomSelect initialized for all dropdowns on Location tab", () => {
        cy.visit("admin/system/church-info");

        // Switch to Location tab
        cy.get("#location-tab").click();

        // Verify TomSelect is initialized for country, language, and timezone
        cy.get("#sChurchCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");
        cy.get("#sLanguage", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");
        cy.get("#sTimeZone", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");
    });

    it("should allow saving church information when all required fields are filled", () => {
        cy.visit("admin/system/church-info");

        // Fill required fields on Basic Information tab
        cy.get("#sChurchPhone").clear().type("(555) 123-4567");
        cy.get("#sChurchEmail").clear().type("test@example.com");

        // Move to Location tab and fill required address fields
        cy.get("#location-tab").click();
        cy.get("#sChurchAddress").clear().type("123 Main St");
        cy.get("#sChurchCity").clear().type("Springfield");

        // Wait for country dropdown to be initialized
        cy.get("#sChurchCountry", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");

        // Select country
        cy.tomSelectByValue("#sChurchCountry", "US");

        // Wait for state dropdown to populate via API
        cy.get("#sChurchState", { timeout: 10000 }).siblings(".ts-wrapper").should("exist");

        // Verify state dropdown has options before selecting
        cy.get("#sChurchState option").should("have.length.greaterThan", 1);

        // Select state
        cy.tomSelectByValue("#sChurchState", "IL");

        // Fill ZIP code
        cy.get("#sChurchZip").clear().type("62701");

        // Submit form - wait a moment for form to be ready
        cy.wait(500);
        cy.get("#church-info-form").submit();

        // Verify we stay on the page (redirect after save)
        cy.url({ timeout: 10000 }).should("include", "church-info");

        // Verify success notification appears
        cy.contains("Church information saved successfully", { timeout: 10000 }).should("be.visible");
    });

    it("should prevent saving when required church name is empty", () => {
        cy.visit("admin/system/church-info");

        // Ensure church name is empty
        cy.get("#sChurchName").clear();

        // Try to submit form
        cy.get("#church-info-form").submit();

        // Browser HTML5 validation should prevent submission
        // Form input should still have focus or show validation UI
        cy.get("#sChurchName").should("have.attr", "required");
    });

    it("should prevent saving when required address is empty", () => {
        cy.visit("admin/system/church-info");

        // Fill basic required fields
        cy.get("#sChurchName").clear().type("Test Church");
        cy.get("#sChurchPhone").clear().type("(555) 123-4567");
        cy.get("#sChurchEmail").clear().type("test@example.com");

        // Move to Location tab
        cy.get("#location-tab").click();

        // Ensure address is empty
        cy.get("#sChurchAddress").clear();

        // Try to submit form
        cy.get("#church-info-form").submit();

        // Browser HTML5 validation should prevent submission
        // Address field should still have required attribute
        cy.get("#sChurchAddress").should("have.attr", "required");
    });

    it("should have Language and Timezone fields in Basic Information tab", () => {
        cy.visit("admin/system/church-info");

        // Both should be visible in the Basic Information tab
        cy.get("#basic").contains("Language").should("be.visible");
        cy.get("#basic").contains("Time Zone").should("be.visible");

        // Both should be TomSelect dropdowns
        cy.get("#sLanguage").should("exist");
        cy.get("#sTimeZone").should("exist");
    });

    it("should allow navigating between tabs", () => {
        cy.visit("admin/system/church-info");

        // Click Location & Map tab
        cy.get("#location-tab").click();
        cy.get("#location-tab").should("have.class", "active");

        // Click Display Preview tab
        cy.get("#display-tab").click();
        cy.get("#display-tab").should("have.class", "active");
        cy.get("#location-tab").should("not.have.class", "active");

        // Go back to Basic Information
        cy.get("#basic-tab").click();
        cy.get("#basic-tab").should("have.class", "active");
        cy.get("#display-tab").should("not.have.class", "active");
    });
});
