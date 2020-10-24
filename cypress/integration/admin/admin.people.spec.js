/// <reference types="cypress" />

context('People Admin', () => {
    beforeEach(() => {
        cy.loginAdmin();
    });

    it('Update Lat & Long ', () => {
        cy.visit('/UpdateAllLatLon.php');
        cy.contains("Update Latitude & Longitude");
    });
});
