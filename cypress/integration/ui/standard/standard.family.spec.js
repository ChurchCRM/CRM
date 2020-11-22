/// <reference types="cypress" />

context('Standard Family', () => {


    it('View Family Lists', () => {
        cy.loginStandard("v2/family");
        cy.contains('Active Family List');

        cy.visit("v2/family?mode=inactive");
        cy.contains('Inactive Family List');
        cy.contains('Lewis').should('not.exist');

        cy.visit("v2/family/3");
        cy.contains('This Family is Deactivated').should('not.be.visible');
        cy.get("#activateDeactivate").click();
        cy.get("body > div.bootbox.modal.fade.bootbox-confirm.in > div > div > div.modal-footer > button.btn.btn-primary.bootbox-accept").click();
        cy.wait(2000);

        cy.visit("v2/family?mode=inactive");
        cy.contains('Lewis');

        cy.visit("v2/family/3");
        cy.contains('This Family is Deactivated').should('be.visible');
        cy.get("#activateDeactivate").click();
        cy.get("body > div.bootbox.modal.fade.bootbox-confirm.in > div > div > div.modal-footer > button.btn.btn-primary.bootbox-accept").click();
        cy.wait(2000);

        cy.visit("v2/family?mode=inactive");
        cy.contains('Lewis').should('not.exist');
    });


    it('View invalid Family', () => {
        cy.loginStandard("v2/family/9999", false);
        cy.location('pathname').should('include', "family/not-found");
        cy.contains('Oops! FAMILY 9999 Not Found');
    });

    it('Entering a new Family', () => {
        cy.loginStandard("FamilyEditor.php");
        cy.contains('Family Info');
        cy.get('#FamilyName').type("Troy" + Cypress._.random(0, 1e6));
        cy.get('input[name="Address1"').type("4222 Clinton Way");
        cy.get('input[name="City"]').clear().type("Los Angelas");
        cy.get('select[name="State"]').select("CA", { force: true });
        cy.get('input[name="FirstName1"]').type("Mike");
        cy.get('input[name="FirstName2"]').type("Carol");
        cy.get('input[name="FirstName3"]').type("Alice");
        cy.get('input[name="FirstName4"]').type("Greg");
        cy.get('input[name="FirstName5"]').type("Marcia");
        cy.get('input[name="FirstName6"]').type("Peter");
        cy.get('select[name="Classification1"]').select("1",{ force: true });
        cy.get('select[name="Classification2"]').select("1",{ force: true });
        cy.get('select[name="Classification3"]').select("1",{ force: true });
        cy.get('select[name="Classification4"]').select("2",{ force: true });
        cy.get('select[name="Classification5"]').select("1",{ force: true });
        cy.get('select[name="Classification6"]').select("2",{ force: true });
        cy.get("input[id='FamilySubmitBottom']").click();
        cy.location('pathname').should('include', "/v2/family/");
        cy.contains("Mike Troy");
        cy.contains("Carol Troy");
        cy.contains("Alice Troy");
        cy.contains("Greg Troy");
        cy.contains("Marcia Troy");
        cy.contains("Peter Troy");
        cy.contains("4222 Clinton Way Los Angelas, CA");
    });


});

