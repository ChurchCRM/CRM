const card = '.card[data-plugin-id="migration-example"]';
const coreCard = '.card[data-plugin-id="custom-links"]';

function fixture(action) {
    return cy.getCookies().then((cookies) => {
        const session = cookies.find((cookie) => cookie.name.startsWith('CRM-'));
        expect(session, 'real Admin login cookie').to.exist;
        return cy.task('pluginMigrationFixture', { action, sessionId: session.value }, { log: false });
    });
}

function enable(expectedStatus = 200) {
    cy.intercept('POST', '**/plugins/api/plugins/migration-example/enable').as('enableMigration');
    cy.get(card).find('[data-action="enable"]').click();
    cy.wait('@enableMigration').then(({ request, response }) => {
        expect(request.headers['x-csrf-token'], 'browser supplies CSRF token').to.be.a('string').and.not.be.empty;
        expect(response.statusCode).to.equal(expectedStatus);
    });
}

describe('Persistent plugin management in a real browser', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('retains ordinary core plugin enable/disable behavior', () => {
        fixture('fresh');
        cy.visit('/plugins/management');
        cy.intercept('POST', '**/plugins/api/plugins/custom-links/enable').as('enableCore');
        cy.get(coreCard).find('[data-action="enable"]').click();
        cy.wait('@enableCore').its('response.statusCode').should('eq', 200);
        cy.get(coreCard).find('[data-action="disable"]').should('be.visible');
        cy.intercept('POST', '**/plugins/api/plugins/custom-links/disable').as('disableCore');
        cy.get(coreCard).find('[data-action="disable"]').click();
        cy.wait('@disableCore').its('response.statusCode').should('eq', 200);
        cy.get(coreCard).find('[data-action="enable"]').should('be.visible');
    });

    it('enables an approved fresh plugin with a CSRF-protected browser request', () => {
        fixture('fresh');
        cy.visit('/plugins/management');
        enable();
        cy.get(card).find('[data-action="disable"]').should('be.visible');
        fixture('inspect').should('include', { applied: 1, uncertain: 0, rows: 0 });
        cy.reload();
        fixture('inspect').should('include', { applied: 1, uncertain: 0 });
    });

    it('shows pending migrations and applies only the new resource', () => {
        fixture('pending');
        cy.visit('/plugins/management');
        cy.get(card).should('contain.text', 'migrations are pending');
        enable();
        cy.get(card).find('[data-action="disable"]').should('be.visible');
        fixture('inspect').should('include', { applied: 2, uncertain: 0, rows: 1 });
    });

    it('surfaces failure and blocks replay of the uncertain migration', () => {
        fixture('failure');
        cy.visit('/plugins/management');
        enable(409);
        cy.get('.notyf').should('contain.text', 'automatic replay is disabled');
        cy.reload();
        cy.get(card).should('contain.text', 'interrupted or failed');
        enable(409);
        fixture('inspect').should('include', { applied: 1, uncertain: 1, rows: 1 });
        cy.get(card).find('[data-action="disable"]').should('not.exist');
    });

    it('explains retained data and preserves rows/history after confirmed uninstall', () => {
        fixture('retained');
        cy.visit('/plugins/management');
        cy.get(card).find('.btn-expand-plugin').click();
        cy.get(card).should('contain.text', 'Uninstalling preserves its application tables and migration history.');
        cy.get(card).find('.btn-plugin-uninstall').click();
        cy.get('#pluginConfirmModal').should('be.visible');
        cy.intercept('DELETE', '**/plugins/api/plugins/migration-example').as('uninstall');
        cy.get('#pluginConfirmOk').click();
        cy.wait('@uninstall').its('response.statusCode').should('eq', 200);
        cy.get(card).should('not.exist');
        fixture('inspect').should('include', { applied: 1, uncertain: 0, rows: 1, settings: 0, installed: false });
    });
});
