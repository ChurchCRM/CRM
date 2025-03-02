/// <reference types="cypress" />

context("Fund Raiser", () => {
    
    it("View All ", () => {
        cy.loginStandard("FundRaiserEditor.php?FundRaiserID=-1");
        cy.contains("Create New Fund Raiser");
    });

    it("View By Filter Date ", () => {
        cy.loginStandard("FindFundRaiser.php?DateStart=2015-01-01&DateEnd=2017-01-01");
        cy.contains("Fundraiser Listing");
        cy.contains("2016 Car Wash");
    });
    
    
    it("New Fund Raiser with url param -1 ", () => {
        cy.loginStandard("FundRaiserEditor.php?FundRaiserID=-1");
        cy.contains("Create New Fund Raiser");
    });

    it("Create new FundRaiser  ", () => {
        cy.loginStandard("FundRaiserEditor.php");
        cy.contains("Create New Fund Raiser");

        cy.get('#Title').type('Summer Car Wash');
        cy.get('#Description').type('This is the youth carwash ');
        cy.get('td > .btn-primary').click();

        cy.url().should('contains', 'FundRaiserEditor.php');
        cy.get('#addItem').click();

        cy.url().should('contains', 'DonatedItemEditor.php');
        cy.get('#Item').type('Soap for the Car wash');
        cy.get('#Title').type('Soap');
        cy.get('#EstPrice').type('20');
        cy.get('.form-group > .btn:nth-child(1)').click();
        cy.url().should('contains', 'FundRaiserEditor.php');
        cy.contains("Soap for the Car wash");
    });
});
