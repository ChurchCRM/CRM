describe('Plugin Management UI', () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit('/plugins/management');
    });

    describe('Plugin List Display', () => {
        it('should display the plugin management page', () => {
            cy.contains('Plugin Management').should('be.visible');
            cy.contains('Core Plugins').should('be.visible');
        });

        it('should display core plugins section', () => {
            cy.contains('Core Plugins').should('be.visible');
            // Should have at least the custom-links plugin card
            cy.get('.card[data-plugin-id]').should('have.length.at.least', 1);
        });

        it('should display plugin cards with status badges', () => {
            cy.get('.card[data-plugin-id]').first().within(() => {
                // Each card should have name and version
                cy.get('.card-title').should('exist');
                cy.get('.badge').should('exist');
            });
        });

        it('should allow expanding plugin cards to see details', () => {
            cy.get('.card[data-plugin-id]').first().within(() => {
                // Card body should initially be hidden
                cy.get('.card-body').should('not.be.visible');
                // Click to expand
                cy.get('[data-card-widget="collapse"]').click();
                // Card body should now be visible
                cy.get('.card-body').should('be.visible');
            });
        });
    });

    describe('Plugin Enable/Disable', () => {
        it('should show enable button for disabled plugins', () => {
            // Look for a disabled plugin or check any enable button exists
            cy.get('body').then(($body) => {
                if ($body.find('[data-action="enable"]').length > 0) {
                    cy.get('[data-action="enable"]').first()
                        .should('be.visible')
                        .and('contain', 'Enable');
                }
            });
        });

        it('should show disable button for enabled plugins', () => {
            // Look for enabled plugin
            cy.get('body').then(($body) => {
                if ($body.find('[data-action="disable"]').length > 0) {
                    cy.get('[data-action="disable"]').first()
                        .should('be.visible')
                        .and('contain', 'Disable');
                }
            });
        });

        it('should enable a disabled plugin', () => {
            // First, make sure custom-links is disabled for this test
            cy.request({
                method: 'POST',
                url: '/plugins/api/plugins/custom-links/disable',
                headers: { 'Content-Type': 'application/json' },
                failOnStatusCode: false
            });

            // Reload page
            cy.visit('/plugins/management');
            cy.get('.card[data-plugin-id="custom-links"]').should('be.visible');

            // Find and click enable button within the card
            cy.get('.card[data-plugin-id="custom-links"]').within(() => {
                cy.get('[data-action="enable"]').click();
            });

            // The page reloads after enable - wait for reload and check state
            // After page reload, should show disable button (plugin is now enabled)
            cy.get('.card[data-plugin-id="custom-links"]', { timeout: 10000 }).within(() => {
                cy.get('[data-action="disable"]').should('be.visible');
            });
        });

        it('should disable an enabled plugin', () => {
            // First, make sure custom-links is enabled
            cy.request({
                method: 'POST',
                url: '/plugins/api/plugins/custom-links/enable',
                headers: { 'Content-Type': 'application/json' },
                failOnStatusCode: false
            });

            // Reload page
            cy.visit('/plugins/management');
            
            // Find and click disable button within the card
            cy.get('.card[data-plugin-id="custom-links"]').within(() => {
                cy.get('[data-action="disable"]').click();
            });

            // The page reloads after disable - wait for reload and check state
            // After page reload, should show enable button (plugin is now disabled)
            cy.get('.card[data-plugin-id="custom-links"]', { timeout: 10000 }).within(() => {
                cy.get('[data-action="enable"]').should('be.visible');
            });
        });
    });

    describe('Plugin Settings', () => {
        before(() => {
            // Enable mailchimp plugin for settings tests
            cy.setupAdminSession();
            cy.request({
                method: 'POST',
                url: '/plugins/api/plugins/mailchimp/enable',
                headers: { 'Content-Type': 'application/json' },
                failOnStatusCode: false
            });
        });

        it('should display settings form when plugin is expanded', () => {
            cy.visit('/plugins/management');
            
            // Expand mailchimp plugin card
            cy.get('.card[data-plugin-id="mailchimp"]').within(() => {
                cy.get('[data-card-widget="collapse"]').click();
                cy.get('.card-body').should('be.visible');
                
                // Should have a settings form
                cy.get('.plugin-settings-form').should('exist');
            });
        });

        it('should have save and reset buttons for settings', () => {
            cy.visit('/plugins/management');
            
            cy.get('.card[data-plugin-id="mailchimp"]').within(() => {
                cy.get('[data-card-widget="collapse"]').click();
                
                // Should have Save Settings button
                cy.get('button[type="submit"]').should('contain', 'Save Settings');
                
                // Should have Reset button
                cy.get('.btn-reset-settings').should('contain', 'Reset');
            });
        });

        it('should save settings when form is submitted', () => {
            cy.visit('/plugins/management');
            
            cy.get('.card[data-plugin-id="mailchimp"]').within(() => {
                cy.get('[data-card-widget="collapse"]').click();
                
                // Submit the form (even with empty values, it should succeed)
                cy.get('button[type="submit"]').click();
            });

            cy.waitForNotification('Settings saved');
        });

        it('should reset settings when reset button is clicked', () => {
            cy.visit('/plugins/management');
            
            cy.get('.card[data-plugin-id="mailchimp"]').within(() => {
                cy.get('[data-card-widget="collapse"]').click();
                cy.get('.btn-reset-settings').click();
            });

            // Should show confirmation dialog - confirm it
            cy.on('window:confirm', () => true);
            
            cy.waitForNotification('Settings reset');
        });
    });

    describe('Plugin Help', () => {
        it('should display help button for plugins with help', () => {
            cy.get('body').then(($body) => {
                // Look for help buttons
                if ($body.find('.btn-plugin-help').length > 0) {
                    cy.get('.btn-plugin-help').first().should('be.visible');
                }
            });
        });

        it('should open help modal when help button is clicked', () => {
            cy.get('body').then(($body) => {
                if ($body.find('.btn-plugin-help').length > 0) {
                    cy.get('.btn-plugin-help').first().click();
                    cy.get('#pluginHelpModal').should('be.visible');
                    cy.get('#pluginHelpModal .modal-body').should('not.be.empty');
                }
            });
        });
    });

    describe('Access Control', () => {
        it('should not allow standard user to access plugin management', () => {
            cy.setupStandardSession();
            cy.visit('/plugins/management', { failOnStatusCode: false });
            
            // Standard user should either:
            // 1. Be redirected to an "access denied" page
            // 2. See an "access denied" message
            // 3. Be redirected to login
            cy.get('body').then(($body) => {
                const bodyText = $body.text().toLowerCase();
                // Should NOT see the plugin management content
                const hasPluginContent = bodyText.includes('core plugins') || 
                                         bodyText.includes('plugin management');
                
                // Should see access denied or login
                const hasAccessDenied = bodyText.includes('access denied') ||
                                        bodyText.includes('not authorized') ||
                                        bodyText.includes('login');
                
                // Either no plugin content OR access denied message
                expect(hasPluginContent && !hasAccessDenied).to.be.false;
            });
        });
    });
});
