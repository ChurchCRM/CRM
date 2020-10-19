/// <reference types="cypress" />

context('Reports', () => {
    beforeEach(() => {
        cy.loginStandard();
    });

    it('Gen Newsletter Labels ', () => {

        cy.visit('LettersAndLabels.php');
        cy.contains("Letters and Mailing Labels");
        cy.contains("Newsletter labels")
        //  TODO    cy.get('.btn-default:nth-child(1)').click();

    });


    it('Gen data labels"', () => {

        cy.visit('LettersAndLabels.php');
        cy.contains("Letters and Mailing Labels");
        cy.contains("Confirm data labels")
        //   TODO     cy.get('.btn-default:nth-child(2)').click();

    });

});
