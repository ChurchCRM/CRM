/// <reference types="cypress" />

describe("Fund Raiser", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("View All ", () => {
        cy.visit("FundRaiserEditor.php?FundRaiserID=-1");
        cy.contains("Create New Fund Raiser");
    });

    it("View By Filter Date ", () => {
        cy.visit("FindFundRaiser.php?DateStart=2015-01-01&DateEnd=2017-01-01");
        cy.contains("Fundraiser Listing");
        cy.contains("2016 Car Wash");
    });
    
    
    it("New Fund Raiser with url param -1 ", () => {
        cy.visit("FundRaiserEditor.php?FundRaiserID=-1");
        cy.contains("Create New Fund Raiser");
    });

    it("Create new FundRaiser  ", () => {
        cy.visit("FundRaiserEditor.php");
        cy.contains("Create New Fund Raiser");

        cy.get('#Title').type('Summer Car Wash');
        cy.get('#Description').type('This is the youth carwash ');
        // Click the form submit input (Save)
        cy.get('input[name="FundRaiserSubmit"]').click();

        cy.url().should('include', 'FundRaiserEditor.php');
        // Click the Add Donated Item link (avoid relying on an id)
        cy.contains('a', 'Add Donated Item').click();

        cy.url().should('contains', 'DonatedItemEditor.php');
        cy.get('#Item').type('Soap for the Car wash');
        cy.get('#Title').type('Soap');
        cy.get('#EstPrice').type('20');
        cy.get('input[name="DonatedItemSubmit"]').click();
        cy.url().should('contains', 'FundRaiserEditor.php');
        cy.contains("Soap for the Car wash");
    });

    it("DonatedItemEditor loads existing item without errors", () => {
        // First create a fundraiser and item to edit
        cy.visit("FundRaiserEditor.php");
        cy.get('#Title').type('Edit Test Fundraiser');
        cy.get('#Description').type('Testing donated item edit');
        cy.get('input[name="FundRaiserSubmit"]').click();
        cy.url().should('include', 'FundRaiserEditor.php');

        cy.contains('a', 'Add Donated Item').click();
        cy.url().should('contains', 'DonatedItemEditor.php');
        cy.get('#Item').type('TestItem');
        cy.get('#Title').type('Test Donated Item');
        cy.get('#EstPrice').clear().type('50');
        cy.get('input[name="DonatedItemSubmit"]').click();

        // Now click the edit link on the donated item to load it in the editor
        cy.contains('a', 'TestItem').click();
        cy.url().should('contain', 'DonatedItemEditor.php');
        cy.url().should('contain', 'DonatedItemID=');

        // Verify the page loads without errors and fields are populated
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Warning:");
        cy.get('#Item').should('have.value', 'TestItem');
        cy.get('#Title').should('have.value', 'Test Donated Item');
    });

    it("DonatedItemEditor new item page loads without errors", () => {
        cy.visit("DonatedItemEditor.php?DonatedItemID=0&CurrentFundraiser=1");
        cy.contains("Donated Item Editor");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Warning:");
        cy.get('#Item').should("exist");
        cy.get('#Title').should("exist");
    });
});
