/**
 * Select2 UI Tests
 * Tests for Select2 dropdowns with Bootstrap 4 theme
 */

describe('Select2 Bootstrap 4 Theme', () => {


    it('should apply Bootstrap 4 theme to Select2 dropdowns', () => {
        cy.loginStandard();

        // Visit a page with Select2 dropdowns
        cy.visit('/FamilyEditor.php?FamilyID=1');
        
        // Wait for page to load
        cy.waitForPageLoad();
        
        // Verify Country dropdown has Bootstrap 4 theme
        cy.select2HasTheme('#Country', 'bootstrap4');
        
        // Verify State dropdown has Bootstrap 4 theme
        cy.select2HasTheme('#State', 'bootstrap4');
    });

    
});

describe('Select2 with AJAX Search', () => {
    

    it('should open search with "?" key and search for Smith', () => {
        cy.loginStandard();
        
        // Press Shift + ? to open the search (as per Footer.js implementation)
        cy.get('body').type('?');
        
        // Verify Select2 dropdown is now open
        cy.get('.select2-container--open').should('be.visible');
        cy.get('.select2-search__field').should('be.visible').and('be.focused');
        
        // Type "Smith" in the search field
        cy.get('.select2-search__field').type('Smith');
        
        // Wait for AJAX results to load
        cy.get('.select2-results__option', { timeout: 5000 })
            .should('be.visible')
            .and('not.have.class', 'select2-results__option--loading');
        
        // Verify results contain "Smith"
        cy.get('.select2-results__option').should('contain', 'Smith');
        
        // Optional: Click on first Smith result
        cy.get('.select2-results__option')
            .contains('Smith')
            .first()
            .click({ force: true });
        
        // Verify navigation occurred (URL should change to PersonView or FamilyView)
        cy.url().should('match', /(PersonView|FamilyView)\.php/);
    });
});