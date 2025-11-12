describe('Custom Menus UI - Security & Functionality', () => {
    beforeEach(() => {
        cy.loginAdmin();
        cy.visit('/v2/admin/menus');
    });

    describe('XSS Protection in UI', () => {
        it('should reject HTML tags in menu name with client-side validation', () => {
            cy.get('#MENU_NAME').type('<img src=x onerror=alert(1)>');
            cy.get('#MENU_LINK').type('https://example.com');
            cy.get('#add-Menu').click();

            // Should show error notification
            cy.get('.notyf').should('contain', 'Menu name cannot contain HTML tags');
            
            // Field should be cleared
            cy.get('#MENU_NAME').should('have.value', '');
        });

        it('should reject HTML tags in link address with client-side validation', () => {
            cy.get('#MENU_NAME').type('Valid Menu');
            cy.get('#MENU_LINK').type('<script>alert(1)</script>');
            cy.get('#add-Menu').click();

            // Should show error notification
            cy.get('.notyf').should('contain', 'Link address cannot contain HTML tags');
            
            // Field should be cleared
            cy.get('#MENU_LINK').should('have.value', '');
        });

        it('should display menus safely without executing XSS', () => {
            // Create a menu through API (bypassing client validation for test)
            const testName = 'Safe Test Menu';
            const testUri = 'https://example.com';
            
            cy.makePrivateAdminAPICall('PUT', '/api/system/menu', {
                Name: testName,
                Uri: testUri,
                Order: 0
            }, 200).then((response) => {
                const menuId = response.body.Id;

                // Reload page to see the menu in the table
                cy.visit('/v2/admin/menus');

                // Verify menu is displayed with escaped content
                cy.get('#menus').should('contain', testName);
                cy.get('#menus').should('contain', testUri);

                // Clean up
                cy.makePrivateAdminAPICall('DELETE', `/api/system/menu/${menuId}`, null, 200);
            });
        });
    });

    describe('Form Validation', () => {
        it('should validate required fields', () => {
            cy.get('#add-Menu').click();

            // Bootstrap validation should show
            cy.get('#menu-form').should('have.class', 'was-validated');
        });

        it('should validate minimum length for menu name', () => {
            cy.get('#MENU_NAME').type('x');
            cy.get('#MENU_LINK').type('https://example.com');
            cy.get('#add-Menu').click();

            // Should show validation error
            cy.get('.notyf').should('exist');
        });

        it('should validate maximum length for menu name', () => {
            cy.get('#MENU_NAME').type('a'.repeat(51));
            cy.get('#MENU_LINK').type('https://example.com');
            cy.get('#add-Menu').click();

            // Should show validation error
            cy.get('.notyf').should('contain', '50 characters');
        });

        it('should validate URL format', () => {
            cy.get('#MENU_NAME').type('Valid Name');
            cy.get('#MENU_LINK').type('not a url');
            cy.get('#add-Menu').click();

            // Should show validation error
            cy.get('.notyf').should('contain', 'http://');
        });

        it('should require http or https protocol', () => {
            cy.get('#MENU_NAME').type('Valid Name');
            cy.get('#MENU_LINK').type('ftp://example.com');
            cy.get('#add-Menu').click();

            // Should show validation error
            cy.get('.notyf').should('contain', 'http://');
        });

        it('should trim whitespace from inputs', () => {
            const menuName = 'Trimmed Menu';
            const menuUri = 'https://example.com';

            cy.get('#MENU_NAME').type(`  ${menuName}  `);
            cy.get('#MENU_LINK').type(`  ${menuUri}  `);
            cy.get('#add-Menu').click();

            // Wait for success notification
            cy.get('.notyf').should('contain', 'successfully');

            // Verify in table
            cy.get('#menus').should('contain', menuName);
            cy.get('#menus').should('contain', menuUri);

            // Clean up
            cy.get('#menus .delete-menu').first().click();
            cy.get('.bootbox-confirm').within(() => {
                cy.contains('Yes').click();
            });
        });
    });

    describe('Menu Creation', () => {
        it('should create a menu with valid inputs', () => {
            const menuName = 'Test Menu ' + Date.now();
            const menuUri = 'https://churchcrm.io';

            cy.get('#MENU_NAME').type(menuName);
            cy.get('#MENU_LINK').type(menuUri);
            cy.get('#add-Menu').click();

            // Should show success notification
            cy.get('.notyf').should('contain', 'successfully');

            // Form should be reset
            cy.get('#MENU_NAME').should('have.value', '');
            cy.get('#MENU_LINK').should('have.value', '');

            // Menu should appear in table
            cy.get('#menus').should('contain', menuName);
            cy.get('#menus').should('contain', menuUri);

            // Clean up
            cy.get('#menus').contains('tr', menuName).find('.delete-menu').click();
            cy.get('.bootbox-confirm').within(() => {
                cy.contains('Yes').click();
            });
            cy.get('.notyf').should('contain', 'deleted');
        });

        it('should accept URLs with query parameters', () => {
            const menuName = 'Query Test ' + Date.now();
            const menuUri = 'https://example.com?param=value&test=123';

            cy.get('#MENU_NAME').type(menuName);
            cy.get('#MENU_LINK').type(menuUri);
            cy.get('#add-Menu').click();

            cy.get('.notyf').should('contain', 'successfully');
            cy.get('#menus').should('contain', menuName);

            // Clean up
            cy.get('#menus').contains('tr', menuName).find('.delete-menu').click();
            cy.get('.bootbox-confirm').within(() => {
                cy.contains('Yes').click();
            });
        });

        it('should accept URLs with special characters', () => {
            const menuName = 'Special Chars ' + Date.now();
            const menuUri = 'https://www.cnn.com?test###$%';

            cy.get('#MENU_NAME').type(menuName);
            cy.get('#MENU_LINK').type(menuUri);
            cy.get('#add-Menu').click();

            cy.get('.notyf').should('contain', 'successfully');
            cy.get('#menus').should('contain', menuName);

            // Clean up
            cy.get('#menus').contains('tr', menuName).find('.delete-menu').click();
            cy.get('.bootbox-confirm').within(() => {
                cy.contains('Yes').click();
            });
        });

        it('should prevent double submission', () => {
            const menuName = 'Double Click Test ' + Date.now();
            const menuUri = 'https://example.com';

            cy.get('#MENU_NAME').type(menuName);
            cy.get('#MENU_LINK').type(menuUri);
            
            // Click button multiple times rapidly
            cy.get('#add-Menu').click().click().click();

            // Button should be disabled during submission
            cy.get('#add-Menu').should('be.disabled');
            cy.get('#add-Menu').should('contain', 'Adding');

            // Wait for completion
            cy.get('.notyf', { timeout: 10000 }).should('contain', 'successfully');

            // Only one menu should be created
            cy.get('#menus').contains('tr', menuName).should('have.length', 1);

            // Clean up
            cy.get('#menus').contains('tr', menuName).find('.delete-menu').click();
            cy.get('.bootbox-confirm').within(() => {
                cy.contains('Yes').click();
            });
        });
    });

    describe('Menu Deletion', () => {
        it('should delete a menu with confirmation', () => {
            const menuName = 'Delete Test ' + Date.now();
            const menuUri = 'https://example.com';

            // Create menu
            cy.get('#MENU_NAME').type(menuName);
            cy.get('#MENU_LINK').type(menuUri);
            cy.get('#add-Menu').click();
            cy.get('.notyf').should('contain', 'successfully');

            // Delete menu
            cy.get('#menus').contains('tr', menuName).find('.delete-menu').click();

            // Confirmation dialog should appear
            cy.get('.bootbox-confirm').should('be.visible');
            cy.get('.bootbox-confirm').should('contain', 'Are you sure');

            // Confirm deletion
            cy.get('.bootbox-confirm').within(() => {
                cy.contains('Yes').click();
            });

            // Should show success notification
            cy.get('.notyf').should('contain', 'deleted');

            // Menu should be removed from table
            cy.get('#menus').should('not.contain', menuName);
        });

        it('should cancel deletion when clicking No', () => {
            const menuName = 'Cancel Test ' + Date.now();
            const menuUri = 'https://example.com';

            // Create menu
            cy.get('#MENU_NAME').type(menuName);
            cy.get('#MENU_LINK').type(menuUri);
            cy.get('#add-Menu').click();
            cy.get('.notyf').should('contain', 'successfully');

            // Try to delete menu but cancel
            cy.get('#menus').contains('tr', menuName).find('.delete-menu').click();
            cy.get('.bootbox-confirm').within(() => {
                cy.contains('No').click();
            });

            // Menu should still be in table
            cy.get('#menus').should('contain', menuName);

            // Clean up
            cy.get('#menus').contains('tr', menuName).find('.delete-menu').click();
            cy.get('.bootbox-confirm').within(() => {
                cy.contains('Yes').click();
            });
        });
    });

    describe('DataTable Rendering', () => {
        it('should display menus in DataTable', () => {
            // Create multiple menus
            const menu1 = 'Menu 1 ' + Date.now();
            const menu2 = 'Menu 2 ' + Date.now();

            cy.get('#MENU_NAME').type(menu1);
            cy.get('#MENU_LINK').type('https://example1.com');
            cy.get('#add-Menu').click();
            cy.get('.notyf').should('contain', 'successfully');

            cy.get('#MENU_NAME').type(menu2);
            cy.get('#MENU_LINK').type('https://example2.com');
            cy.get('#add-Menu').click();
            cy.get('.notyf').should('contain', 'successfully');

            // Both should appear in table
            cy.get('#menus').should('contain', menu1);
            cy.get('#menus').should('contain', menu2);

            // Clean up
            cy.get('#menus').contains('tr', menu1).find('.delete-menu').click();
            cy.get('.bootbox-confirm').within(() => {
                cy.contains('Yes').click();
            });
            cy.get('#menus').contains('tr', menu2).find('.delete-menu').click();
            cy.get('.bootbox-confirm').within(() => {
                cy.contains('Yes').click();
            });
        });

        it('should have delete buttons with proper styling', () => {
            const menuName = 'Button Test ' + Date.now();

            cy.get('#MENU_NAME').type(menuName);
            cy.get('#MENU_LINK').type('https://example.com');
            cy.get('#add-Menu').click();
            cy.get('.notyf').should('contain', 'successfully');

            // Check delete button styling
            cy.get('#menus').contains('tr', menuName).find('.delete-menu')
                .should('have.class', 'btn-danger')
                .should('have.class', 'btn-sm');

            // Clean up
            cy.get('#menus').contains('tr', menuName).find('.delete-menu').click();
            cy.get('.bootbox-confirm').within(() => {
                cy.contains('Yes').click();
            });
        });
    });
});
