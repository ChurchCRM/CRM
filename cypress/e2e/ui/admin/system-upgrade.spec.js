describe('System Upgrade Page', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should load and display version information', () => {
        cy.visit('/admin/system/upgrade');
        
        // Check version information card exists
        cy.contains('Version Information').should('be.visible');
        
        // Verify current version is displayed
        cy.contains('Current Version:').should('be.visible');
        cy.get('.badge.badge-info').should('be.visible').and('not.be.empty');
        
        // Verify available version or up-to-date status
        cy.contains('Latest GitHub Version:').should('be.visible');
        
        // Verify pre-release toggle and refresh button exist
        cy.contains('Allow Pre-release Upgrades').should('be.visible');
        cy.get('#bAllowPrereleaseUpgrade').should('exist');
        cy.get('#refreshFromGitHub').should('be.visible').and('contain', 'Refresh from GitHub');
    });
});
