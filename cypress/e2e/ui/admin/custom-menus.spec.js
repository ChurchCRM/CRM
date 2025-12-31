describe('Custom Menus UI - Security & Functionality', () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit('/admin/system/menus');
    });

    /**
     * Helper to create a menu and verify it appears
     */
    const createMenu = (name, url) => {
        cy.get('#MENU_NAME').type(name);
        cy.get('#MENU_LINK').type(url);
        cy.get('#add-Menu').click();
        cy.waitForNotification('Menu added successfully');
    };

    /**
     * Helper to delete a menu by name (searches table for exact match)
     */
    const deleteMenu = (name) => {
        cy.get('#menus tbody tr').contains(name).parent('tr').find('.delete-menu').click();
        cy.get('.bootbox-confirm').within(() => {
            cy.contains('Yes').click();
        });
        cy.waitForNotification('Menu deleted successfully');
    };

    describe('HTML5 Inline Validation & XSS Protection', () => {
        it('should show Bootstrap validation when required fields are empty', () => {
            cy.get('#add-Menu').click();
            cy.get('#menu-form').should('have.class', 'was-validated');
        });

        it('should enforce HTML5 minlength constraint on menu name', () => {
            cy.get('#MENU_NAME').invoke('prop', 'minLength').should('equal', 2);
        });

        it('should enforce HTML5 maxlength constraint on menu name', () => {
            cy.get('#MENU_NAME').invoke('prop', 'maxLength').should('equal', 50);
        });

        it('should enforce HTML5 url type validation on link field', () => {
            cy.get('#MENU_LINK').invoke('prop', 'type').should('equal', 'url');
        });

        it('should reject HTML tags in menu name', () => {
            cy.get('#MENU_NAME').type('<img src=x onerror=alert(1)>');
            cy.get('#MENU_LINK').type('https://example.com');
            cy.get('#add-Menu').click();

            cy.waitForNotification('Menu name cannot contain HTML tags');
            cy.get('#MENU_NAME').should('have.value', '');
        });

        it('should reject HTML tags in link address', () => {
            cy.get('#MENU_NAME').type('Valid Menu');
            cy.get('#MENU_LINK').type('<script>alert(1)</script>');
            cy.get('#add-Menu').click();

            cy.waitForNotification('Link address cannot contain HTML tags');
            cy.get('#MENU_LINK').should('have.value', '');
        });
    });

});
