describe('Admin System Reset', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should load the system reset page', () => {
        cy.visit('/admin/system/reset');
        cy.contains('Database Reset').should('be.visible');
    });

    it('should display reset options with warnings', () => {
        cy.visit('/admin/system/reset');
        
        // Should have cards with reset options
        cy.get('.card').should('exist');
        
        // Should have warning indicators for dangerous operations
        cy.get('.btn-danger, .text-danger, .alert-danger, .badge-danger').should('exist');
    });
});
