/// <reference types="cypress" />

context("People Tools", () => {
    it("Open the People Dashboard", () => {
        cy.loginStandard("PeopleDashboard.php");
        cy.contains("People Dashboard");
        cy.contains("People Functions");
        cy.contains("Reports");
        cy.contains("Family Roles");
        cy.contains("People Classification");
        cy.contains("Gender Demographics");
    });

    it("verify people", () => {
        cy.loginStandard("v2/people/verify");
        cy.contains("People Verify Dashboard");
    });

    it("self-register", () => {
        cy.loginStandard("members/self-register.php");
        cy.contains("Self Registrations");
        cy.contains("Persons");
        cy.contains("Families");
    });

    it("Geo Page", () => {
        cy.loginStandard("GeoPage.php");
        cy.contains("Family Geographic Utilities");
    });

    it("Update Lat & Long ", () => {
        cy.loginStandard("UpdateAllLatLon.php");
        cy.contains("Update Latitude & Longitude");
    });
});
