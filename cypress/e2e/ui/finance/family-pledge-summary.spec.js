describe('Finance: Pledge Dashboard', () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit('/finance/pledge/dashboard');
    });

    it('should load the Pledge Dashboard page', () => {
        // Verify fiscal year selector is present (main page element)
        cy.contains('label', 'Fiscal Year').should('be.visible');
        cy.get('select#fyid').should('be.visible');
    });

    it('should display fiscal year selector', () => {
        cy.contains('label', 'Fiscal Year').should('be.visible');
        cy.get('select#fyid').should('be.visible');
        cy.get('select#fyid option').should('have.length.greaterThan', 0);
        cy.contains('Current Fiscal Year').should('be.visible');
    });

    it('should display created pledges from different families', () => {
        // Select current fiscal year (first option)
        cy.get('select#fyid').then($select => {
            const options = $select.find('option');
            cy.get('select#fyid').select(options.eq(0).val());
        });
        
        cy.wait(500);
        
        // Verify table has rows (pledges)
        cy.get('table tbody tr').should('have.length.greaterThan', 0);
        
        // Check table headers
        cy.get('th').contains('Family Name').should('be.visible');
        cy.get('th').contains('Fund Name').should('be.visible');
        cy.get('th').contains('Pledge Amount').should('be.visible');
    });

    it('should display family with multiple pledges correctly', () => {
        // Select current fiscal year
        cy.get('select#fyid').then($select => {
            const options = $select.find('option');
            cy.get('select#fyid').select(options.eq(0).val());
        });
        
        cy.wait(500);
        
        // Family 1 should have 2 pledges (one for each fund)
        // Verify rows exist in table
        cy.get('table tbody tr').first().should('be.visible');
        
        // Check for currency formatting
        cy.get('table tbody').contains('$').should('be.visible');
    });

    it('should display pledge amounts formatted as currency', () => {
        cy.get('select#fyid').then($select => {
            const options = $select.find('option');
            cy.get('select#fyid').select(options.eq(0).val());
        });
        
        cy.wait(500);
        
        // Verify currency formatting (should contain $ symbol and decimal format)
        cy.get('table tbody td.text-right').first().invoke('text').should('match', /\$[\d,]+\.\d{2}/);
    });

    it('should display pledge data in table format', () => {
        cy.get('select#fyid').then($select => {
            const options = $select.find('option');
            cy.get('select#fyid').select(options.eq(0).val());
        });
        
        cy.wait(500);
        
        // Verify table structure and data
        cy.get('table tbody tr').first().within(() => {
            cy.get('td').should('have.length.greaterThan', 2);
        });
    });

    it('should show family badge count', () => {
        cy.get('select#fyid').then($select => {
            const options = $select.find('option');
            cy.get('select#fyid').select(options.eq(0).val());
        });
        
        cy.wait(500);
        
        // Badge should contain numeric count
        cy.get('.badge').should('be.visible').invoke('text').should('match', /^\d+$/);
    });

    it('should allow switching between fiscal years', () => {
        cy.get('select#fyid').then($select => {
            const options = $select.find('option');
            
            if (options.length > 1) {
                // Select a different fiscal year
                const secondValue = options.eq(1).val();
                
                cy.get('select#fyid').select(secondValue);
                cy.wait(500);
                
                // Verify selection changed
                cy.get('select#fyid').should('have.value', secondValue);
            }
        });
    });

    it('should have clickable family names linking to family view', () => {
        cy.get('select#fyid').then($select => {
            const options = $select.find('option');
            cy.get('select#fyid').select(options.eq(0).val());
        });
        
        cy.wait(500);
        
        // Check for family name link (modern v2 URL format)
        cy.get('table tbody a').first().should('have.attr', 'href').and('include', '/v2/family/');
    });

    it('should work with responsive table layout', () => {
        cy.get('select#fyid').then($select => {
            const options = $select.find('option');
            cy.get('select#fyid').select(options.eq(0).val());
        });
        
        cy.wait(500);
        
        // Verify table has responsive wrapper
        cy.get('.table-responsive').should('be.visible');
        cy.get('table').should('have.class', 'table');
    });
});
