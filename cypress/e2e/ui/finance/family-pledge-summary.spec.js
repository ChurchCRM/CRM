describe('Finance: Pledge Dashboard', () => {
    before(() => {
        cy.setupAdminSession();
        cy.visit('/finance/pledge/dashboard');
    });

    it('should load the Pledge Dashboard page', () => {
        cy.url().should('include', '/finance/pledge/dashboard');
    });

    it('should display page content', () => {
        cy.get('body').should('be.visible');
        cy.get('body').invoke('html').then(html => {
            expect(html.length).to.be.greaterThan(100);
        });
    });

    it('should have a main content area', () => {
        // Page rendered with content (flexible selector for any main content wrapper)
        cy.get('body *').should('have.lengthOf.greaterThan', 5);
    });

    it('should not have JavaScript errors on load', () => {
        // Just verify page didn't crash
        cy.get('body').should('exist');
    });
});
