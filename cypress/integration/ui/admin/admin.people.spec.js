/// <reference types="cypress" />

context('Admin People', () => {

    it('Update Lat & Long ', () => {
        cy.loginAdmin('UpdateAllLatLon.php');
        cy.contains("Update Latitude & Longitude");
    });

    it('Person Classifications Editor', () => {
        cy.loginAdmin('OptionManager.php?mode=classes');
        cy.get('.box-body > form').submit();
        cy.contains("Person Classifications Editor");
    });

    it('Family Roles Editor', () => {
        cy.loginAdmin('OptionManager.php?mode=famroles');
        cy.get('.box-body > form').submit();
        cy.contains("Family Roles Editor");
    });

    it('Family Property List', () => {
        cy.loginAdmin('PropertyList.php?Type=f');
        cy.contains('Family Property List');
        cy.get('p > .btn').click();
        cy.url().should('contains', 'PropertyEditor.php');
        cy.get('.row:nth-child(1) .form-control').select('2');
        cy.get('.row:nth-child(2) .form-control').type('Test');
        cy.get('.row:nth-child(3) .form-control').type('Who');
        cy.get('.row:nth-child(4) .form-control').type('What do you want');
        cy.get('#save').click();
        cy.url().should('contains', 'PropertyList.php');
    });

    it('Custom Family Fields Editor', () => {
        cy.loginAdmin('FamilyCustomFieldsEditor.php');
        cy.get('.box-body > form').submit();
        cy.contains('Custom Family Fields Editor');
    });

    it('Person Property List', () => {
        cy.loginAdmin('PropertyList.php?Type=p');
        cy.contains('Person Property List');
        cy.get('p > .btn').click();
        cy.url().should('contains', 'PropertyEditor.php');
        cy.get('.row:nth-child(1) .form-control').select('1');
        cy.get('.row:nth-child(2) .form-control').type('Test');
        cy.get('.row:nth-child(3) .form-control').type('Who');
        cy.get('.row:nth-child(4) .form-control').type('What do you want');
        cy.get('#save').click();
        cy.url().should('contains', 'PropertyList.php');
    });

    it('Custom Person Fields Editor', () => {
        cy.loginAdmin('PersonCustomFieldsEditor.php');
        cy.get('.box-body > form').submit();
        cy.contains('Custom Person Fields Editor');
    });

    it('Volunteer Opportunity Editor', () => {
        cy.loginAdmin('VolunteerOpportunityEditor.php');
        cy.get('.box-body > form').submit();
        cy.contains('Volunteer Opportunity Editor');
    });
});
