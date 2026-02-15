describe('Admin Backup Database', () => {
    beforeEach(() => {
        cy.setupAdminSessionFromEnv();
    });

    it('should load the backup page', () => {
        cy.visit('/admin/system/backup');
        cy.contains('Backup Database').should('be.visible');
    });

    it('should display backup options', () => {
        cy.visit('/admin/system/backup');
        
        // Should have backup type selection
        cy.get('select, input[type="radio"], .btn-group').should('exist');
    });
});
