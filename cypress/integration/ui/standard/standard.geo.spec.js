/// <reference types="cypress" />

context('Geo Pages', () => {

    it('Geo Page', () => {
        cy.loginStandard('GeoPage.php');
        cy.contains("Family Geographic Utilities");
    });

    it('Update Lat & Long ', () => {
        cy.loginStandard( 'UpdateAllLatLon.php');
        cy.contains("Update Latitude & Longitude");
    });


});
