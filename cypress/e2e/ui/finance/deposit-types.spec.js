describe('Deposit Types - Configurable from Database', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should load deposit types from API in FindDepositSlip page', () => {
        cy.visit('/FindDepositSlip.php');

        // Wait for deposit types to load
        cy.get('#depositType option', { timeout: 10000 }).should('have.length.greaterThan', 0);

        // Verify all expected deposit types are present
        const expectedTypes = [
            'Bank',
            'Cash',
            'Credit Card',
            'Bank Draft',
            'eGive',
            'Check',
            'Stock',
            'Property',
            'Cryptocurrency',
            'Other'
        ];

        expectedTypes.forEach(type => {
            cy.get('#depositType').contains(type).should('exist');
        });

        // Verify Bank is selected by default
        cy.get('#depositType').should('have.value', 'Bank');
    });

    it('should create a new deposit with Stock type', () => {
        cy.visit('/FindDepositSlip.php');

        // Wait for deposit types to load
        cy.get('#depositType option', { timeout: 10000 }).should('have.length.greaterThan', 0);

        // Fill in deposit form
        cy.get('#depositComment').type('Test Stock Donation');
        cy.get('#depositType').select('Stock');
        cy.get('#depositDate').type('2024-01-15');

        // Create the deposit
        cy.get('#addNewDeposit').click();

        // Should redirect to DepositSlipEditor
        cy.url().should('include', 'DepositSlipEditor.php');
        cy.url().should('include', 'DepositSlipID=');
    });

    it('should create a new deposit with Cryptocurrency type', () => {
        cy.visit('/FindDepositSlip.php');

        // Wait for deposit types to load
        cy.get('#depositType option', { timeout: 10000 }).should('have.length.greaterThan', 0);

        // Fill in deposit form
        cy.get('#depositComment').type('Test Cryptocurrency Donation');
        cy.get('#depositType').select('Cryptocurrency');
        cy.get('#depositDate').type('2024-01-15');

        // Create the deposit
        cy.get('#addNewDeposit').click();

        // Should redirect to DepositSlipEditor
        cy.url().should('include', 'DepositSlipEditor.php');
        cy.url().should('include', 'DepositSlipID=');
    });

    it('should handle missing deposit types gracefully', () => {
        // Intercept API call and return empty array
        cy.intercept('GET', '/api/system/list-options/deposit-types', {
            statusCode: 200,
            body: { depositTypes: [] }
        }).as('getDepositTypes');

        cy.visit('/FindDepositSlip.php');

        // Wait for API call
        cy.wait('@getDepositTypes');

        // Dropdown should be empty but not cause errors
        cy.get('#depositType option').should('have.length', 0);
    });

    it('should handle API error gracefully', () => {
        // Intercept API call and return error
        cy.intercept('GET', '/api/system/list-options/deposit-types', {
            statusCode: 500,
            body: { error: 'Internal Server Error' }
        }).as('getDepositTypesError');

        cy.visit('/FindDepositSlip.php');

        // Wait for API call
        cy.wait('@getDepositTypesError');

        // Should show error notification (using CRM.notify)
        // Dropdown should remain empty
        cy.get('#depositType option').should('have.length', 0);
    });
});
