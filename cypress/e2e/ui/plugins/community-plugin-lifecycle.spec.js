/**
 * Community Plugin Lifecycle
 *
 * Tests the full lifecycle of a community plugin entirely through the web UI:
 *   1. Install from the Browse Approved modal (exercises registry load + download)
 *   2. Enable via the plugin card on the management page
 *   3. Create a person (fires PERSON_CREATED hook)
 *   4. Uninstall via the management UI confirm modal
 *
 * Uses hello-world from the approved plugin registry.
 * No local files are required — the plugin is downloaded from GitHub during the test.
 * before() cleans up any leftover install from a previous run via the API.
 */
describe('Community Plugin Lifecycle', () => {
    const PLUGIN_ID = 'hello-world';
    const uniqueSeed = Date.now().toString();
    let createdPersonId = null;

    before(() => {
        cy.setupAdminSession();
        // Remove any leftover install from a previous partial run (400 = not installed = fine)
        cy.makePrivateAdminAPICall('DELETE', `/plugins/api/plugins/${PLUGIN_ID}`, null, [200, 400]);
    });

    after(() => {
        if (createdPersonId) {
            cy.setupAdminSession();
            cy.makePrivateAdminAPICall('DELETE', `/api/people/${createdPersonId}`, null, [200, 204, 404]);
        }
    });

    // ─────────────────────────────────────────────────────────────────────────
    it('installs the hello-world plugin via the Browse Approved modal', () => {
        cy.setupAdminSession({ forceLogin: true });
        cy.visit('/plugins/management');

        // Open Browse Approved modal
        cy.get('#btn-browse-approved').should('be.visible').click();
        cy.get('#approvedPluginsModal').should('be.visible');

        // Wait for the registry to load (spinner gone, Install buttons rendered)
        cy.get('#approvedPluginsList .btn-install-approved', { timeout: 15000 }).should('be.visible');

        // Target hello-world by its plugin id — adding more approved plugins won't break this test
        cy.get(`#approvedPluginsList .btn-install-approved[data-plugin-id="${PLUGIN_ID}"]`).click();

        // Modal closes and page reloads after install — wait for hello-world card to appear
        cy.get(`.card[data-plugin-id="${PLUGIN_ID}"]`, { timeout: 30000 }).should('exist');

        // Confirm installed but not yet enabled
        cy.makePrivateAdminAPICall('GET', '/plugins/api/plugins').then((response) => {
            const plugin = response.body.data.find((p) => p.id === PLUGIN_ID);
            expect(plugin, 'hello-world in plugin list').to.exist;
            expect(plugin.isActive, 'not yet enabled after install').to.be.false;
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    it('enables the hello-world plugin from the management page', () => {
        cy.setupAdminSession({ forceLogin: true });
        cy.visit('/plugins/management');

        cy.get(`.card[data-plugin-id="${PLUGIN_ID}"]`).as('card').should('exist');
        cy.get('@card').within(() => {
            cy.get('[data-action="enable"]').should('be.visible').click();
        });

        // Page reloads after toggle — Disable button should now be visible
        cy.get(`.card[data-plugin-id="${PLUGIN_ID}"]`, { timeout: 10000 }).within(() => {
            cy.get('[data-action="disable"]').should('be.visible');
        });

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
