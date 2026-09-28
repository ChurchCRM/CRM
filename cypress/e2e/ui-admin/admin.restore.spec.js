describe('Admin Restore Database', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should load the restore page', () => {
        cy.visit('/admin/system/restore');
        cy.contains('Restore Database').should('be.visible');
    });

    it('should display restore options and file upload', () => {
        cy.visit('/admin/system/restore');
        
        // Should have file input or restore interface
        cy.get('.card').should('exist');
    });

    it('should show the standard warning card on the normal restore page', () => {
        cy.visit('/admin/system/restore');
        cy.contains('Important Warning').should('be.visible');
        cy.contains('CAUTION').should('be.visible');
    });

    it('should show the onboarding welcome card when context=onboarding', () => {
        cy.visit('/admin/system/restore?context=onboarding');
        cy.contains('Welcome Back!').should('be.visible');
        cy.contains("Let's restore your previous ChurchCRM data.").should('be.visible');
        // Standard warning should NOT appear
        cy.contains('Important Warning').should('not.exist');
    });

    it('should still show the file upload form in onboarding context', () => {
        cy.visit('/admin/system/restore?context=onboarding');
        cy.get('#dropzone').should('be.visible');
        cy.get('#restoreFile').should('exist');
        cy.get('#submitRestore').should('be.visible');
    });
});
