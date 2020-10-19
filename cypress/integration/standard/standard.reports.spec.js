/// <reference types="cypress" />

context('Reports', () => {
    beforeEach(() => {
        cy.loginStandard();
    });

    it('Gen Newsletter Labels ', () => {

        cy.visit('LettersAndLabels.php');
        cy.contains("Letters and Mailing Labels");
        cy.contains("Newsletter labels")
  //      cy.get('.btn-default:nth-child(1)').click();

    });


    it('Confirm data ables', () => {

        cy.visit('LettersAndLabels.php');
        cy.contains("Letters and Mailing Labels");
        cy.contains("Confirm data labels")
//        cy.get('.btn-default:nth-child(2)').click();

    });

});
