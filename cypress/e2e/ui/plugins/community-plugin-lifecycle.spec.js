/**
 * Community Plugin Lifecycle
 *
 * Tests the full lifecycle of a community plugin:
 *   1. Enable via management UI
 *   2. Create a person (fires PERSON_CREATED hook)
 *   3. Uninstall via management UI confirm modal
 *
 * Uses hello-world (tracked in git at src/plugins/community/hello-world).
 * The after() hook restores the plugin directory via git so subsequent runs work.
 */
describe('Community Plugin Lifecycle', () => {
    const PLUGIN_ID = 'hello-world';
    const uniqueSeed = Date.now().toString();
    let createdPersonId = null;

    before(() => {
        // Restore hello-world from git if a previous run deleted it
        cy.exec('git -C . checkout -- src/plugins/community/hello-world 2>/dev/null; true', {
            failOnNonZero: false,
        });

        // Start with a clean disabled state
        cy.setupAdminSession();
        cy.makePrivateAdminAPICall('POST', `/plugins/api/plugins/${PLUGIN_ID}/disable`);
    });

    after(() => {
        // Restore plugin files deleted by the uninstall test
        cy.exec('git -C . checkout -- src/plugins/community/hello-world 2>/dev/null; true', {
            failOnNonZero: false,
        });

        // Clean up the test person
        if (createdPersonId) {
            cy.setupAdminSession();
            cy.makePrivateAdminAPICall('DELETE', `/api/people/${createdPersonId}`, null, [200, 204, 404]);
        }
    });

    // ─────────────────────────────────────────────────────────────────────────
    it('enables the hello-world plugin from the management page', () => {
        cy.setupAdminSession({ forceLogin: true });
        cy.visit('/plugins/management');

        // Plugin card should exist and show Enable button (currently disabled)
        cy.get(`.card[data-plugin-id="${PLUGIN_ID}"]`).as('card').should('exist');
        cy.get('@card').within(() => {
            cy.get('[data-action="enable"]').should('be.visible').click();
        });

        // Page reloads after toggle — plugin should now show Disable button
        cy.get(`.card[data-plugin-id="${PLUGIN_ID}"]`, { timeout: 10000 }).within(() => {
            cy.get('[data-action="disable"]').should('be.visible');
        });

        // Confirm via API that the plugin is active
        cy.makePrivateAdminAPICall('GET', '/plugins/api/plugins').then((response) => {
            const plugin = response.body.data.find((p) => p.id === PLUGIN_ID);
            expect(plugin, 'hello-world plugin in list').to.exist;
            expect(plugin.isActive, 'isActive').to.be.true;
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    it('fires the PERSON_CREATED hook when a new person is saved', () => {
        const firstName = 'HookTest';
        const lastName = `Spec${uniqueSeed}`;

        cy.setupAdminSession({ forceLogin: true });
        cy.visit('PersonEditor.php');

        cy.get('#FirstName').type(firstName);
        cy.get('#LastName').type(lastName);
        cy.get('button[name="PersonSubmit"]').click();

        cy.url().should('contain', 'PersonView.php');
        cy.contains(firstName).should('be.visible');

        // Capture person ID from redirect URL for cleanup
        cy.url().then((url) => {
            const match = url.match(/PersonID=(\d+)/);
            if (match) {
                createdPersonId = parseInt(match[1], 10);
            }
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    it('uninstalls the plugin via the management UI confirm modal', () => {
        cy.setupAdminSession({ forceLogin: true });
        cy.visit('/plugins/management');

        cy.get(`.card[data-plugin-id="${PLUGIN_ID}"]`).as('card').should('exist');

        // Uninstall button lives in the card header toolbar
        cy.get('@card').within(() => {
            cy.get('.btn-plugin-uninstall').should('be.visible').click();
        });

        // Confirm via the Tabler modal (not window.confirm)
        cy.get('#pluginConfirmModal').should('be.visible');
        cy.get('#pluginConfirmOk').click();

        // After reload the plugin card should no longer exist
        cy.get(`.card[data-plugin-id="${PLUGIN_ID}"]`, { timeout: 10000 }).should('not.exist');

        // Confirm via API — plugin should be absent from the list
        cy.makePrivateAdminAPICall('GET', '/plugins/api/plugins').then((response) => {
            const plugin = response.body.data?.find((p) => p.id === PLUGIN_ID);
            expect(plugin, 'hello-world should be absent after uninstall').to.not.exist;
        });
    });
});
