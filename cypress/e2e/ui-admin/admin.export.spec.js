describe('Admin Export Landing Page', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should load the export page', () => {
        cy.visit('/admin/export');
        cy.contains('Export').should('be.visible');
    });

    it('should display all three export cards', () => {
        cy.visit('/admin/export');
        cy.contains('CSV Export').should('be.visible');
        cy.contains('ChMeetings Export').should('be.visible');
        cy.contains('Database Backup').should('be.visible');
    });
});
