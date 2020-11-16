context('Standard Person Add Group', () => {

    it('Add user then group', () => {
        cy.loginStandard("PersonEditor.php");
        cy.get('#Gender').select('1');
        cy.get('#FirstName').type('Boby');
        cy.get('#LastName').type('Hall');
        cy.get('#BirthMonth').select('12');
        cy.get('#BirthDay').select('21');
        cy.get('#BirthYear').clear().type('1950');
        cy.get('#Email').type('boby@example.com');
        cy.get('#Classification').select('1');
        cy.get('#PersonSaveButton').click();

        cy.url().should('contains', 'PersonView.php');
        cy.get('.nav-tabs > li:nth-child(4) > a').click();
        cy.get('#input-person-properties').select("Disabled", { force: true });
        cy.get('#assign-property-btn').click();
        cy.url().should('contains', 'PersonView.php');
        cy.contains("Disabled")

        // TODO: Group Selection
        cy.get('#addGroup > .fa').click();
        // cy.get('#select2-targetGroupSelection-container').click();
        // cy.get('#select2-targetRoleSelection-container').click();
        // cy.get('.btn-success').click();
        // cy.url().should('contains', 'PersonView.php');
        // cy.contains("Class 6-7");


    });

});
