/// <reference types="cypress" />

/**
 * A first-time admin explores the demo data, backs it up, starts fresh, then takes the
 * Restore a Backup path from the Setup Dashboard.
 * Runs after 05, which leaves a clean install with admin / changeme and church info saved.
 * Start Fresh returns it to a new install: default password, no church name.
 */

describe('06 - Explore demo data, start fresh, restore a backup', () => {
    const username = 'admin';
    const newPassword = 'Cypress@01!';
    let backupFile;

    const signIn = (password) => {
        cy.clearCookies();
        cy.visit('/session/begin');
        cy.get('input[name=User]', { timeout: 15000 }).type(username);
        cy.get('input[name=Password]').type(`${password}{enter}`);
    };

    it('imports the demo data from the Setup Dashboard, backs it up, and starts fresh from the demo banner', () => {
        signIn('changeme');
        cy.location('pathname', { timeout: 30000 }).should('match', /\/admin\/setup$/);

        cy.get('#importDemoDataSetup').click();
        cy.get('#demoImportConfirmBtn').click();
        cy.location('pathname', { timeout: 120000 }).should('match', /\/people\/dashboard$/);
        cy.request('/api/families/latest').its('body.families').should('have.length.greaterThan', 0);

        cy.request({ method: 'POST', url: '/admin/api/database/backup', body: { BackupType: 2 }, timeout: 60000 }).then(({ body }) => {
            const name = body.BackupDownloadFileName.replace(/^.*[\\/]/, '');
            backupFile = `${Cypress.config('downloadsFolder')}/${name}`;
            cy.sessionCsrfToken().then((csrfToken) =>
                cy.request({
                    method: 'POST',
                    url: `/admin/api/database/download/${name}`,
                    form: true,
                    body: { csrf_token: csrfToken },
                    encoding: 'binary',
                    timeout: 30000,
                }).then((download) => cy.writeFile(backupFile, download.body, 'binary')),
            );
        });

        cy.visit('/people/dashboard');
        cy.get('#demoModeBanner .js-start-fresh').click();
        cy.get('.bootbox .btn-danger').click();
        cy.get('.bootbox', { timeout: 60000 }).should('contain.text', 'Ready for your church');
        cy.get('.bootbox .btn-primary').click();
        cy.location('pathname', { timeout: 30000 }).should('match', /\/session\/begin$/);
    });

    it('leaves a clean production install in setup mode', () => {
        signIn('changeme');
        cy.location('pathname', { timeout: 30000 }).should('match', /\/account\/password$/);
        cy.get('#OldPassword').type('changeme');
        cy.get('#NewPassword1').type(newPassword);
        cy.get('#NewPassword2').type(newPassword);
        cy.get('button[type=submit]').click();

        cy.location('pathname', { timeout: 15000 }).should('match', /\/admin\/setup$/);
        cy.get('#demoModeBanner').should('not.exist');
        cy.get('#sidebar').should('not.exist');
        cy.get('#setupChoiceDemo #importDemoDataSetup').should('be.visible');

        cy.request('/admin/api/system/config/sInstallDataProfile').its('body.value').should('eq', 'production');
        cy.request('/admin/api/system/config/sChurchName').its('body.value').should('eq', '');
        cy.request('/admin/api/system/config/sTelemetryLevel').its('body.value').should('eq', 'default');
        cy.request('/api/persons/latest').its('body.people').should('have.length.lessThan', 2);
        cy.request('/api/families/latest').its('body.families').should('have.length', 0);
        cy.request('/api/groups/').its('body').should('have.length', 0);
        cy.request('/api/deposits').its('body.Deposits').should('have.length', 0);
    });

    it('restores the demo backup from the Setup Dashboard', () => {
        signIn(newPassword);
        cy.location('pathname', { timeout: 30000 }).should('match', /\/admin\/setup$/);
        cy.get('#setupChoiceRestore a').click();
        cy.location('pathname').should('match', /\/admin\/system\/restore$/);

        cy.get('#restoreFile').selectFile(backupFile, { force: true });
        cy.get('#submitRestore').click();
        cy.get('#restoreSuccessModal', { timeout: 120000 }).should('be.visible');
        cy.location('pathname', { timeout: 30000 }).should('match', /\/session\/begin$/);

        signIn('changeme');
        cy.get('#demoModeBanner', { timeout: 30000 }).should('be.visible');
        cy.request('/admin/api/system/config/sChurchName').its('body.value').should('eq', 'Main St. Cathedral');
        cy.request('/api/families/latest').its('body.families').should('have.length.greaterThan', 0);
    });
});
