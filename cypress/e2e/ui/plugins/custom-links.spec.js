describe('Custom Links Plugin UI - Security & Functionality', () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit('/plugins/custom-links/manage');
    });

    describe('HTML5 Inline Validation & XSS Protection', () => {
        it('should show Bootstrap validation when required fields are empty', () => {
            cy.get('#add-link').click();
            cy.get('#link-form').should('have.class', 'was-validated');
        });

        it('should enforce HTML5 minlength constraint on link name', () => {
            cy.get('#LINK_NAME').invoke('prop', 'minLength').should('equal', 2);
        });

        it('should enforce HTML5 maxlength constraint on link name', () => {
            cy.get('#LINK_NAME').invoke('prop', 'maxLength').should('equal', 50);
        });

        it('should enforce HTML5 url type validation on URL field', () => {
            cy.get('#LINK_URL').invoke('prop', 'type').should('equal', 'url');
        });

        it('should reject HTML tags in link name', () => {
            cy.get('#LINK_NAME').type('<img src=x onerror=alert(1)>');
            cy.get('#LINK_URL').type('https://example.com');
            cy.get('#add-link').click();

            cy.waitForNotification('Link name cannot contain HTML tags');
            cy.get('#LINK_NAME').should('have.value', '');
        });

        it('should reject HTML tags in URL', () => {
            cy.get('#LINK_NAME').type('Valid Link');
            cy.get('#LINK_URL').type('<script>alert(1)</script>');
            cy.get('#add-link').click();

            cy.waitForNotification('URL cannot contain HTML tags');
            cy.get('#LINK_URL').should('have.value', '');
        });
    });

});
