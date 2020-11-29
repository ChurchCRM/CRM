/// <reference types="cypress" />

context('Standard People Dashboard', () => {

    it('Dashboard Page', () => {
        cy.loginStandard('PeopleDashboard.php');
        cy.contains("People Dashboard");
    });

    it('Geo Page', () => {
        cy.loginStandard('GeoPage.php');
        cy.contains("Family Geographic Utilities");
    });

    it('Update Lat & Long ', () => {
        cy.loginStandard( 'UpdateAllLatLon.php');
        cy.contains("Update Latitude & Longitude");
    });


});
