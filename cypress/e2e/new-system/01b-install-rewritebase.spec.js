/// <reference types="cypress" />

/**
 * Install RewriteBase (#10563)
 *
 * Runs immediately after 01-setup-wizard in both always-on jobs:
 *   - test-new-system        base URL /
 *   - test-new-system-subdir base URL /churchcrm/
 *
 * The wizard writes $sRootPath into Config.php and HtaccessGenerator stamps
 * RewriteBase into every routing .htaccess. This spec checks the files the
 * installer actually wrote (the app directory is bind-mounted into the
 * container) and that Apache still routes with those directives in place.
 */
describe('01b - Install RewriteBase', () => {
    it('stamps RewriteBase for this install and still routes the API', () => {
        const pathname = new URL(Cypress.config('baseUrl')).pathname.replace(/\/$/, '');
        const expectedRoot = pathname === '' ? '/' : pathname;

        cy.task('htaccess:scan').then((files) => {
            const routing = files.filter((file) => file.hasRewriteEngine);
            expect(routing.length, 'routing .htaccess files discovered').to.be.greaterThan(5);

            routing.forEach((file) => {
                const dir = file.rel.replace(/^src\//, '').replace(/\/?\.htaccess$/, '');
                const expected = expectedRoot === '/'
                    ? (dir ? `/${dir}` : '/')
                    : `${expectedRoot}${dir ? `/${dir}` : ''}`;

                expect(file.rewriteBases, file.rel).to.deep.equal([expected]);
            });

            const includeFile = files.find((file) => file.rel === 'src/Include/.htaccess');
            expect(includeFile, 'src/Include/.htaccess').to.exist;
            expect(includeFile.hasRewriteEngine, 'Include is deny-only').to.eq(false);
            expect(includeFile.rewriteBases, 'deny-only file has no RewriteBase').to.deep.equal([]);
        });

        cy.request('/api/public/echo').its('status').should('eq', 200);
    });
});
