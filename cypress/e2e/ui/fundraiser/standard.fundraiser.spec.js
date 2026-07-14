/// <reference types="cypress" />

describe("Fund Raiser", () => {
    beforeEach(() => cy.setupStandardSession());

    it("View All", () => {
        cy.visit("/fundraiser/");
        cy.contains("Fundraiser Listing");
    });

    it("View By Filter Date ", () => {
        cy.visit("/fundraiser/?dateStart=2015-01-01&dateEnd=2017-01-01");
        cy.contains("Fundraiser Listing");
        cy.contains("2016 Car Wash");
    });

    it("Create form renders at /fundraiser/editor", () => {
        cy.visit("/fundraiser/editor");
        cy.contains("Create New Fund Raiser");
    });

    it("Create new FundRaiser  ", () => {
        cy.visit("/fundraiser/editor");
        cy.contains("Create New Fund Raiser");

        cy.get('#Title').type('Summer Car Wash');
        cy.get('#Description').type('This is the youth carwash ');
        // Click the form submit input (Save)
        cy.get('input[name="FundRaiserSubmit"]').click();

        // After a successful save the editor reloads at /fundraiser/editor/{id}
        cy.url().should('include', '/fundraiser/editor/');
        // Click the Add Donated Item link (avoid relying on an id)
        cy.contains('a', 'Add Donated Item').click();

        cy.url().should('contains', '/donated-items/editor');
        cy.get('#Item').type('Soap for the Car wash');
        cy.get('#Title').type('Soap');
        cy.get('#EstPrice').type('20');
        cy.get('input[name="DonatedItemSubmit"]').click();
        cy.url().should('contains', '/fundraiser/editor/');
        cy.contains("Soap for the Car wash");
    });
});
