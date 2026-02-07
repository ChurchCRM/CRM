describe('Plugin Management API', () => {
    describe('GET /plugins/api/plugins', () => {
        it('should return list of all plugins for admin', () => {
            cy.makePrivateAdminAPICall('GET', '/plugins/api/plugins').then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property('success', true);
                expect(response.body).to.have.property('data');
                expect(response.body.data).to.be.an('array');
                expect(response.body.data.length).to.be.greaterThan(0);
                
                // Check plugin structure
                const plugin = response.body.data[0];
                expect(plugin).to.have.property('id');
                expect(plugin).to.have.property('name');
                expect(plugin).to.have.property('version');
                expect(plugin).to.have.property('isActive');
            });
        });

        it('should require admin authentication', () => {
            cy.request({
                method: 'GET',
                url: '/plugins/api/plugins',
                failOnStatusCode: false
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 302]);
            });
        });

        it('should not allow standard user access', () => {
            cy.setupStandardSession();
            cy.request({
                method: 'GET',
                url: '/plugins/api/plugins',
                failOnStatusCode: false
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 403, 302]);
            });
        });
    });

    describe('POST /plugins/api/plugins/{pluginId}/enable', () => {
        it('should enable a disabled plugin', () => {
            // First disable the plugin
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/custom-links/disable');
            
            // Now enable it
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/custom-links/enable').then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property('success', true);
            });
            
            // Verify it's enabled
            cy.makePrivateAdminAPICall('GET', '/plugins/api/plugins').then((response) => {
                const plugin = response.body.data.find(p => p.id === 'custom-links');
                expect(plugin).to.exist;
                expect(plugin.isActive).to.eq(true);
            });
        });

        it('should return error for non-existent plugin', () => {
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/non-existent-plugin/enable', null, 400);
        });

        it('should require admin authentication', () => {
            cy.request({
                method: 'POST',
                url: '/plugins/api/plugins/custom-links/enable',
                failOnStatusCode: false
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 302]);
            });
        });
    });

    describe('POST /plugins/api/plugins/{pluginId}/disable', () => {
        it('should disable an enabled plugin', () => {
            // First enable the plugin
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/custom-links/enable');
            
            // Now disable it
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/custom-links/disable').then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property('success', true);
            });
            
            // Verify it's disabled
            cy.makePrivateAdminAPICall('GET', '/plugins/api/plugins').then((response) => {
                const plugin = response.body.data.find(p => p.id === 'custom-links');
                expect(plugin).to.exist;
                expect(plugin.isActive).to.eq(false);
            });
        });

        it('should return error for non-existent plugin', () => {
            // Non-existent plugin ID causes a Throwable error when trying to set config
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/non-existent-plugin/disable', null, 500);
        });
    });

    describe('POST /plugins/api/plugins/{pluginId}/settings', () => {
        before(() => {
            // Enable mailchimp plugin for settings tests
            cy.setupAdminSession();
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/mailchimp/enable');
        });

        it('should save plugin settings', () => {
            const payload = {
                settings: {
                    apiKey: 'test-api-key-12345'
                }
            };
            
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/mailchimp/settings', payload).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property('success', true);
            });
        });

        it('should return error for empty settings', () => {
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/mailchimp/settings', { settings: {} }, 400);
        });

        it('should return error for non-existent plugin', () => {
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/non-existent-plugin/settings', { settings: { key: 'value' } }, 404);
        });
    });

    describe('POST /plugins/api/plugins/{pluginId}/reset', () => {
        before(() => {
            cy.setupAdminSession();
            // Enable mailchimp and set some settings
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/mailchimp/enable');
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/mailchimp/settings', {
                settings: { apiKey: 'test-api-key-for-reset' }
            });
        });

        it('should reset all plugin settings', () => {
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/mailchimp/reset').then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property('success', true);
            });
        });

        it('should return error for non-existent plugin', () => {
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/non-existent-plugin/reset', null, 404);
        });

        it('should require admin authentication', () => {
            cy.request({
                method: 'POST',
                url: '/plugins/api/plugins/mailchimp/reset',
                failOnStatusCode: false
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 302]);
            });
        });
    });

    describe('GET /plugins/status/{pluginId}', () => {
        it('should return plugin status for enabled plugin', () => {
            // First enable the plugin via admin API
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/custom-links/enable');
            
            // Check status endpoint using admin session
            cy.makePrivateAdminAPICall('GET', '/plugins/status/custom-links').then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property('success', true);
                expect(response.body).to.have.property('isActive', true);
            });
        });

        it('should return plugin status for disabled plugin', () => {
            // First disable the plugin
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/custom-links/disable');
            
            // Check status endpoint
            cy.makePrivateAdminAPICall('GET', '/plugins/status/custom-links').then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property('success', true);
                expect(response.body).to.have.property('isActive', false);
            });
        });

        it('should return isActive false for non-existent plugin', () => {
            // The endpoint returns isActive: false for unknown plugins (doesn't 404)
            cy.makePrivateAdminAPICall('GET', '/plugins/status/non-existent-plugin').then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property('isActive', false);
            });
        });
    });
});
