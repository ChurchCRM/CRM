/// <reference types="cypress" />

context('Admin People', () => {

    it('Update Lat & Long ', () => {
        cy.loginAdmin('UpdateAllLatLon.php');
        cy.contains("Update Latitude & Longitude");
    });
});
