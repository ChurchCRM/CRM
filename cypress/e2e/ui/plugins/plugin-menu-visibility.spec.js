const PLUGIN_ID = 'custom-links';
const MANAGE_LINKS_HREF = 'plugins/custom-links/manage';

// No core plugin declares a menu 'permission' today, so this spec covers the menu
// visibility contract through Custom Links (admin-only "Manage Links" entry) and
// checks that a non-admin still gets a working menu.
describe('Plugin menu visibility', () => {
    let wasActive;

    before(() => {
        cy.makePrivateAdminAPICall('GET', '/plugins/api/plugins').then((response) => {
            const plugin = response.body.data.find((p) => p.id === PLUGIN_ID);
            wasActive = plugin.isActive;
        });
        cy.makePrivateAdminAPICall('POST', `/plugins/api/plugins/${PLUGIN_ID}/enable`, null, [200, 400]);
    });

    after(() => {
        if (wasActive === false) {
            cy.makePrivateAdminAPICall('POST', `/plugins/api/plugins/${PLUGIN_ID}/disable`, null, [200, 400]);
        }
    });

    it('shows the plugin menu entry to an administrator', () => {
        cy.setupAdminSession();
        cy.visit('/v2/dashboard');
        cy.get(`a[href$="${MANAGE_LINKS_HREF}"]`).should('exist');
    });

    it('hides the admin-only plugin menu entry from a non-admin', () => {
        cy.setupStandardSession();
        cy.visit('/v2/dashboard');
        cy.contains('Families');
        cy.get(`a[href$="${MANAGE_LINKS_HREF}"]`).should('not.exist');
    });

    it('refuses the plugin route to a non-admin even though the menu entry is hidden', () => {
        cy.setupStandardSession();
        cy.visit(`/${MANAGE_LINKS_HREF}`);
        cy.url().should('include', '/v2/access-denied');
    });
});
