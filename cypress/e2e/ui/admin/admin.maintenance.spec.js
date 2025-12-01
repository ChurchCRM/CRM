describe('Admin System Maintenance', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should load the system maintenance page', () => {
        cy.visit('/admin/system/maintenance');
        cy.contains('System Maintenance').should('be.visible');
    });

    it('should display maintenance options', () => {
        cy.visit('/admin/system/maintenance');
        
        // Should have maintenance cards or options
        cy.get('.card').should('exist');
    });
});
