/**
 * Select2 UI Tests
 * Tests for Select2 dropdowns with Bootstrap 4 theme
 */

describe('Select2 Bootstrap 4 Theme', () => {
    beforeEach(() => {
        cy.loginAdmin('');
    });

    it('should apply Bootstrap 4 theme to Select2 dropdowns', () => {
        // Visit a page with Select2 dropdowns
        cy.visit('/FamilyEditor.php?FamilyID=1');
        
        // Wait for page to load
        cy.waitForPageLoad();
        
        // Verify Country dropdown has Bootstrap 4 theme
        cy.select2HasTheme('#Country', 'bootstrap4');
        
        // Verify State dropdown has Bootstrap 4 theme
        cy.select2HasTheme('#State', 'bootstrap4');
    });

    it('should select a country using Select2', () => {
        cy.visit('/FamilyEditor.php?FamilyID=1');
        cy.waitForPageLoad();
        
        // Select a country by text
        cy.select2ByText('#Country', 'Canada');
        
        // Verify the selection
        cy.select2GetSelected('#Country').should('contain', 'Canada');
    });

    it('should work with Select2 state dropdown', () => {
        cy.visit('/FamilyEditor.php?FamilyID=1');
        cy.waitForPageLoad();
        
        // First select US to ensure states are available
        cy.select2ByValue('#Country', 'us');
        
        // Wait a moment for states to load
        cy.wait(500);
        
        // Select a state
        cy.select2ByText('#State', 'California');
        
        // Verify the selection
        cy.select2GetSelected('#State').should('contain', 'California');
    });
});

describe('Select2 with AJAX Search', () => {
    beforeEach(() => {
        cy.loginAdmin('');
    });

    it('should search using the global multiSearch', () => {
        cy.visit('/');
        cy.waitForPageLoad();
        
        // Use the global search (multiSearch)
        cy.get('.multiSearch').should('exist');
        
        // Verify it has Bootstrap 4 theme
        cy.select2HasTheme('.multiSearch', 'bootstrap4');
        
        // Search for a person or family (this requires data in the system)
        // Uncomment if you have test data:
        // cy.select2Search('.multiSearch', 'test', 'Test Family');
    });

    it('should open search with "?" key and search for Smith', () => {
        cy.visit('/');
        cy.waitForPageLoad();
        
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

    it('should close search dropdown when pressing Escape after "?"', () => {
        cy.visit('/');
        cy.waitForPageLoad();
        
        // Open search with "?"
        cy.get('body').type('?');
        
        // Verify dropdown is open
        cy.get('.select2-container--open').should('be.visible');
        
        // Press Escape to close
        cy.get('.select2-search__field').type('{esc}');
        
        // Verify dropdown is closed
        cy.get('.select2-container--open').should('not.exist');
    });
});

describe('Select2 in System Settings', () => {
    beforeEach(() => {
        cy.loginAdmin('');
    });

    it('should handle Select2 dropdowns in SystemSettings', () => {
        cy.visit('/SystemSettings.php');
        cy.waitForPageLoad();
        
        // Find any choiceSelectBox (used in system settings)
        cy.get('.choiceSelectBox').first().then($select => {
            if ($select.length > 0) {
                // Verify it has Bootstrap 4 theme
                cy.wrap($select).siblings('.select2-container')
                    .should('have.class', 'select2-container--bootstrap4');
            }
        });
    });
});

describe('Select2 in Modals (Bootbox)', () => {
    beforeEach(() => {
        cy.loginAdmin('');
    });

    it('should work in group selection modals', () => {
        // Visit a person page
        cy.visit('/PersonView.php?PersonID=1');
        cy.waitForPageLoad();
        
        // Click "Add to Group" button
        cy.get('#addGroup').click({ force: true });
        
        // Wait for modal to appear
        cy.get('.bootbox').should('be.visible');
        
        // Verify Select2 in modal has Bootstrap 4 theme
        cy.get('.bootbox').within(() => {
            cy.get('#targetGroupSelection')
                .siblings('.select2-container')
                .should('have.class', 'select2-container--bootstrap4');
        });
        
        // Close modal
        cy.get('.bootbox .btn-danger').click();
    });
});

describe('Select2 on Registration Page (Not Logged In)', () => {
    it('should have Bootstrap 4 theme on family registration', () => {
        // Visit registration page without login
        cy.visit('/external/register/');
        cy.waitForPageLoad();
        
        // Verify Country dropdown has Bootstrap 4 theme
        cy.select2HasTheme('#familyCountry', 'bootstrap4');
        
        // Select a country
        cy.select2ByText('#familyCountry', 'United States');
        
        // Verify selection
        cy.select2GetSelected('#familyCountry').should('contain', 'United States');
    });
});
