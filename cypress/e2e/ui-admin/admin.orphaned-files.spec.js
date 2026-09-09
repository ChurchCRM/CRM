describe('Admin Orphaned Files', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should load the orphaned files page', () => {
        cy.visit('/admin/system/orphaned-files');
        cy.contains('Orphaned Files').should('be.visible');
    });

    it('should display orphaned files management interface', () => {
        cy.visit('/admin/system/orphaned-files');
        
        // Should have a card with orphaned files info
        cy.get('.card').should('exist');
        
        // Should show count or empty state message
        cy.get('.card-body').should('exist');
    });
});
