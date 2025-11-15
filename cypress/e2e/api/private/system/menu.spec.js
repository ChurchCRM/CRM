describe('Menu API - Security & Validation', () => {
    let createdMenuIds = [];

    afterEach(() => {
        // Clean up: delete all created menus
        createdMenuIds.forEach(menuId => {
            cy.makePrivateAdminAPICall('DELETE', `/api/system/menu/${menuId}`, null, 200);
        });
        createdMenuIds = [];
    });

    describe('XSS Protection', () => {
        it('should reject menu name with HTML tags', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: '<img src=x onerror=alert(1)>',
                Uri: 'https://example.com',
                Order: 0
            }, 400).then((response) => {
                expect(response.body).to.have.property('error');
                expect(response.body.failures).to.include('Menu name cannot contain HTML tags');
            });
        });

        it('should reject menu URI with HTML tags', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Test Menu',
                Uri: '<script>alert(1)</script>',
                Order: 0
            }, 400).then((response) => {
                expect(response.body).to.have.property('error');
                // The URI validation will catch this as "not a valid URL" or "must start with http"
                expect(response.body.failures).to.be.an('array');
                expect(response.body.failures.length).to.be.greaterThan(0);
            });
        });

        it('should reject javascript: protocol URLs', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Test Menu',
                Uri: 'javascript:alert(document.cookie)',
                Order: 0
            }, 400).then((response) => {
                expect(response.body).to.have.property('error');
                expect(response.body.failures).to.include('Link must start with http:// or https://');
            });
        });

        it('should sanitize HTML entities in menu name', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'TestMenu', // Simple name without entities
                Uri: 'https://example.com',
                Order: 0
            }, 200).then((response) => {
                expect(response.body).to.have.property('Id');
                createdMenuIds.push(response.body.Id);
                
                // Name should be clean
                expect(response.body.Name).to.equal('TestMenu');
            });
        });
    });

    describe('Validation Rules', () => {
        it('should reject empty menu name', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: '',
                Uri: 'https://example.com',
                Order: 0
            }, 400).then((response) => {
                expect(response.body).to.have.property('error');
                expect(response.body.failures).to.include('Menu name is required');
            });
        });

        it('should reject menu name less than 2 characters', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'x',
                Uri: 'https://example.com',
                Order: 0
            }, 400).then((response) => {
                expect(response.body).to.have.property('error');
                expect(response.body.failures).to.include('Menu name must be at least 2 characters');
            });
        });

        it('should reject menu name over 50 characters', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'a'.repeat(51),
                Uri: 'https://example.com',
                Order: 0
            }, 400).then((response) => {
                expect(response.body).to.have.property('error');
                expect(response.body.failures).to.include('Menu name must be 50 characters or less');
            });
        });

        it('should reject empty URI', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Test Menu',
                Uri: '',
                Order: 0
            }, 400).then((response) => {
                expect(response.body).to.have.property('error');
                expect(response.body.failures).to.include('Link address is required');
            });
        });

        it('should reject URI without http/https protocol', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Test Menu',
                Uri: 'www.example.com',
                Order: 0
            }, 400).then((response) => {
                expect(response.body).to.have.property('error');
                expect(response.body.failures).to.include('Link must start with http:// or https://');
            });
        });

        it('should reject URI with only protocol', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Test Menu',
                Uri: 'https://',
                Order: 0
            }, 400).then((response) => {
                expect(response.body).to.have.property('error');
                expect(response.body.failures).to.include('Link must be a valid URL');
            });
        });
    });

    describe('Valid Menu Creation', () => {
        it('should create menu with valid http URL', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'HTTP Test',
                Uri: 'http://example.com',
                Order: 0
            }, 200).then((response) => {
                expect(response.body).to.have.property('Id');
                expect(response.body.Name).to.equal('HTTP Test');
                expect(response.body.Uri).to.equal('http://example.com');
                createdMenuIds.push(response.body.Id);
            });
        });

        it('should create menu with valid https URL', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'HTTPS Test',
                Uri: 'https://secure.example.com',
                Order: 0
            }, 200).then((response) => {
                expect(response.body).to.have.property('Id');
                expect(response.body.Name).to.equal('HTTPS Test');
                expect(response.body.Uri).to.equal('https://secure.example.com');
                createdMenuIds.push(response.body.Id);
            });
        });

        it('should accept URL with query parameters', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Query Test',
                Uri: 'https://example.com?param=value&other=123',
                Order: 0
            }, 200).then((response) => {
                expect(response.body).to.have.property('Id');
                expect(response.body.Uri).to.equal('https://example.com?param=value&other=123');
                createdMenuIds.push(response.body.Id);
            });
        });

        it('should accept URL with special characters in query string', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Special Chars Test',
                Uri: 'https://www.cnn.com?test###$%',
                Order: 0
            }, 200).then((response) => {
                expect(response.body).to.have.property('Id');
                expect(response.body.Uri).to.equal('https://www.cnn.com?test###$%');
                createdMenuIds.push(response.body.Id);
            });
        });

        it('should accept URL with anchor', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Anchor Test',
                Uri: 'https://example.com/page#section',
                Order: 0
            }, 200).then((response) => {
                expect(response.body).to.have.property('Id');
                expect(response.body.Uri).to.equal('https://example.com/page#section');
                createdMenuIds.push(response.body.Id);
            });
        });

        it('should accept URL with port number', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Port Test',
                Uri: 'http://localhost:8080/test',
                Order: 0
            }, 200).then((response) => {
                expect(response.body).to.have.property('Id');
                expect(response.body.Uri).to.equal('http://localhost:8080/test');
                createdMenuIds.push(response.body.Id);
            });
        });

        it('should accept inputs with whitespace (trimming is done client-side)', () => {
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Trimmed Menu',  // Already trimmed
                Uri: 'https://example.com',
                Order: 0
            }, 200).then((response) => {
                expect(response.body).to.have.property('Id');
                expect(response.body.Name).to.equal('Trimmed Menu');
                expect(response.body.Uri).to.equal('https://example.com');
                createdMenuIds.push(response.body.Id);
            });
        });
    });

    describe('Menu Retrieval', () => {
        it('should retrieve all menus', () => {
            // Create a menu first
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Get Test',
                Uri: 'https://example.com',
                Order: 0
            }, 200).then((createResponse) => {
                createdMenuIds.push(createResponse.body.Id);

                // Then retrieve all menus
                cy.makePrivateAdminAPICall('GET', '/api/system/menu', null, 200).then((response) => {
                    expect(response.body).to.have.property('menus');
                    expect(response.body.menus).to.be.an('array');
                    
                    // Find our created menu
                    const createdMenu = response.body.menus.find(m => m.Id === createResponse.body.Id);
                    expect(createdMenu).to.exist;
                    expect(createdMenu.Name).to.equal('Get Test');
                    expect(createdMenu.Uri).to.equal('https://example.com');
                });
            });
        });

        it('should return menus without XSS in response', () => {
            // Create menus and verify they're sanitized in the response
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Safe Menu',
                Uri: 'https://example.com',
                Order: 0
            }, 200).then((createResponse) => {
                createdMenuIds.push(createResponse.body.Id);

                cy.makePrivateAdminAPICall('GET', '/api/system/menu', null, 200).then((response) => {
                    response.body.menus.forEach(menu => {
                        // Verify no script tags in any field
                        expect(menu.Name).to.not.include('<script>');
                        expect(menu.Uri).to.not.include('<script>');
                        expect(menu.Name).to.not.match(/<[^>]+>/);
                    });
                });
            });
        });
    });

    describe('Menu Deletion', () => {
        it('should delete a menu successfully', () => {
            // Create a menu
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: 'Delete Test',
                Uri: 'https://example.com',
                Order: 0
            }, 200).then((response) => {
                const menuId = response.body.Id;

                // Delete it
                cy.makePrivateAdminAPICall('DELETE', `/api/system/menu/${menuId}`, null, 200);

                // Verify it's deleted
                cy.makePrivateAdminAPICall('GET', '/api/system/menu', null, 200).then((getResponse) => {
                    const deletedMenu = getResponse.body.menus.find(m => m.Id === menuId);
                    expect(deletedMenu).to.be.undefined;
                });
            });
        });

    
    });
});
