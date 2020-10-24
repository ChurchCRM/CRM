/// <reference types="cypress" />

context('People Dashboard', () => {
    beforeEach(() => {
        cy.loginStandard();
    });

    it('Dashboard Page', () => {
        cy.visit('/PeopleDashboard.php');
        cy.contains("People Dashboard");
    });

    it('Geo Page', () => {
        cy.visit('/GeoPage.php');
        cy.contains("Family Geographic Utilities");
    });

    it('Update Lat & Long ', () => {
        cy.visit( '/UpdateAllLatLon.php');
        cy.contains("Update Latitude & Longitude");
    });


});
