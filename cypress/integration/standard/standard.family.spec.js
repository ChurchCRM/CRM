/// <reference types="cypress" />

context('Family', () => {
    beforeEach(() => {

    })

    it('View a Family', () => {
        cy.loginAdmin();
        cy.visit("v2/family/1");
        cy.contains('Campbell - Family');
        cy.contains('Darren Campbell');
        cy.contains('Music Ministry');
    });

    it('Entering a new Family', () => {
        cy.loginStandard();
        cy.visit("/FamilyEditor.php");
        cy.contains('Family Info');
        cy.get('#FamilyName').type("Troy");
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

