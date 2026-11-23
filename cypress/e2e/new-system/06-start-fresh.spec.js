/// <reference types="cypress" />

/**
 * A first-time admin explores the demo data, then starts fresh for their own church.
 * Runs after 05, which leaves a clean install with admin / changeme and church info saved.
 * Start Fresh returns it to a new install: default password, no church name.
 */

describe('06 - Explore demo data, then start fresh', () => {
    const username = 'admin';
    const newPassword = 'Cypress@01!';

    const signIn = (password) => {
        cy.clearCookies();
        cy.visit('/session/begin');
        cy.get('input[name=User]', { timeout: 15000 }).type(username);
        cy.get('input[name=Password]').type(`${password}{enter}`);
    };

    it('imports the demo data from the Setup Dashboard and starts fresh from the demo banner', () => {
        signIn('changeme');
        cy.location('pathname', { timeout: 30000 }).should('match', /\/admin\/setup$/);

        cy.get('#importDemoDataSetup').click();
        cy.get('#demoImportConfirmBtn').click();
        cy.location('pathname', { timeout: 120000 }).should('match', /\/people\/dashboard$/);
        cy.request('/api/families/latest').its('body.families').should('have.length.greaterThan', 0);

        cy.get('#demoModeBanner .js-start-fresh').click();
        cy.get('.bootbox .btn-danger').click();
        cy.get('.bootbox', { timeout: 60000 }).should('contain.text', 'Ready for your church');
        cy.get('.bootbox .btn-primary').click();
        cy.location('pathname', { timeout: 30000 }).should('match', /\/session\/begin$/);
    });

    it('leaves a clean production install that opens on the Setup Dashboard', () => {
        signIn('changeme');
        cy.location('pathname', { timeout: 30000 }).should('match', /\/account\/password$/);
        cy.get('#OldPassword').type('changeme');
        cy.get('#NewPassword1').type(newPassword);
        cy.get('#NewPassword2').type(newPassword);
        cy.get('button[type=submit]').click();

        cy.location('pathname', { timeout: 15000 }).should('match', /\/admin\/setup$/);
        cy.get('#demoModeBanner').should('not.exist');
        cy.get('#sidebar-menu .nav-item').should('have.length', 4);
        cy.get('#setupChoiceDemo #importDemoDataSetup').should('be.visible');

        cy.request('/admin/api/system/config/sInstallDataProfile').its('body.value').should('eq', 'production');
        cy.request('/admin/api/system/config/sChurchName').its('body.value').should('eq', '');
        cy.request('/admin/api/system/config/sTelemetryLevel').its('body.value').should('eq', 'install');
        cy.request('/api/persons/latest').its('body.people').should('have.length.lessThan', 2);
        cy.request('/api/families/latest').its('body.families').should('have.length', 0);
        cy.request('/api/groups/').its('body').should('have.length', 0);
        cy.request('/api/deposits').its('body.Deposits').should('have.length', 0);
    });
});
