describe('Maps Plugin API', () => {
    before(() => {
        cy.setupAdminSession();
        // Enable maps plugin for all tests in this suite
        cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/maps/enable');
    });

    after(() => {
        // Leave plugin enabled so other tests are not affected
    });

    describe('Plugin Discovery', () => {
        it('should include the maps plugin in the plugin list', () => {
            cy.makePrivateAdminAPICall('GET', '/plugins/api/plugins').then((response) => {
                expect(response.status).to.eq(200);
                const mapsPlugin = response.body.data.find(p => p.id === 'maps');
                expect(mapsPlugin).to.exist;
                expect(mapsPlugin).to.have.property('name');
                expect(mapsPlugin).to.have.property('isActive', true);
            });
        });
    });

    describe('Plugin Settings', () => {
        it('should save the Google Maps API key setting', () => {
            const payload = {
                settings: {
                    googleMapsGeocodeKey: 'test-geocode-key-placeholder'
                }
            };

            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/maps/settings', payload).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property('success', true);
            });
        });

        it('should save the hideLatLon setting', () => {
            const payload = {
                settings: {
                    hideLatLon: '1'
                }
            };

            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/maps/settings', payload).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property('success', true);
            });
        });

        it('should reset plugin settings', () => {
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/maps/reset').then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property('success', true);
            });
        });
    });

    describe('Connection Test (testWithSettings)', () => {
        it('should return an error when no API key is provided', () => {
            // Reset settings first to ensure no saved key
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/maps/reset');

            const payload = { settings: {} };

            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/maps/test', payload, 400).then((response) => {
                expect(response.body).to.have.property('success', false);
                expect(response.body.message).to.be.a('string').and.not.be.empty;
            });
        });

        it('should return an error when an invalid API key is used', () => {
            const payload = {
                settings: {
                    googleMapsGeocodeKey: 'invalid-key-that-will-fail'
                }
            };

            // The geocode call will fail with an invalid key — expect a 400 response
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/maps/test', payload, 400).then((response) => {
                expect(response.body).to.have.property('success', false);
                expect(response.body.message).to.be.a('string').and.not.be.empty;
            });
        });
    });
});
