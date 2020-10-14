/// <reference types="cypress" />

context('Family', () => {
    before(() => {
        cy.loginAdmin();

    })

    it('View Inactive Family List', () => {
        cy.visit("v2/family?mode=inactive");
        cy.contains('Inactive Family List');
        cy.contains('Lewis').should('not.exist');
        cy.visit("v2/family/3");
        cy.contains('This Family is Deactivated').should('not.be.visible');
        cy.get("#activateDeactivate").click();
        cy.get("body > div.bootbox.modal.fade.bootbox-confirm.in > div > div > div.modal-footer > button.btn.btn-primary.bootbox-accept").click();
        cy.wait(300);
        cy.visit("v2/family?mode=inactive");
        cy.contains('Lewis');
        cy.visit("v2/family/3");
        cy.contains('This Family is Deactivated').should('be.visible');
        cy.get("#activateDeactivate").click();
        cy.visit("v2/family?mode=inactive");
        cy.contains('Lewis').should('not.exist');
    });


    it('View a Family', () => {
        cy.visit("v2/family/1");
        cy.contains('Campbell - Family');
        cy.contains('Darren Campbell');
        cy.contains('Music Ministry');

        cy.visit("v2/family/20");
        cy.contains('Black - Family');
        cy.contains('New Building Fund');
    });


});

