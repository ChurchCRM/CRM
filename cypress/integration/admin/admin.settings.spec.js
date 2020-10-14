/// <reference types="cypress" />

context('Actions', () => {
    before(() => {
        cy.login("admin", "changeme");
        cy.visit("/SystemSettings.php");
        cy.location('pathname').should('include', "/SystemSettings.php");
    })

    it('View system settings', () => {
        cy.contains('Church Information');
        cy.contains('User Setup');
        cy.contains('Email Setup');
        cy.contains('People Setup');
        cy.contains('System Settings');
        cy.contains('Map Settings');
        cy.contains('Report Settings');
        cy.contains('Localization');
        cy.contains('Financial Settings');
        cy.contains('Integration');
        cy.contains('Backup');
    });

    it('Enter Non-Text System Setting', () => {
        let newValue = "New Test Value " + Cypress._.random(0, 1e6)
        cy.get("input[name='new_value[1003]']").clear().type(newValue);
        cy.get("#SystemSettingsForm").click();
        cy.location('pathname').should('include', "/SystemSettings.php");
        cy.request("/v2/admin/debug");
        cy.request("/SystemSettings.php");
        cy.get("input[name='new_value[1003]']").should('have.value', newValue);
    });
});

