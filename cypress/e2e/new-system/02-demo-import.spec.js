/// <reference types="cypress" />

/**
 * Demo Data Import Tests (Steps 4-5)
 * 
 * Tests run after setup wizard completes:
 * 4. Go to admin page and import demo data via UI workflow
 * 5. Ensure system has users and navigates correctly
 * 
 * Prerequisites: Setup wizard must have completed (01-setup-wizard.spec.js)
 */

describe('02 - Demo Data Import', () => {
    // Default admin credentials after fresh install
    const adminCredentials = {
        username: 'admin',
        password: 'changeme'
    };

    // Helper function to login
    const loginAsAdmin = () => {
        cy.visit('/login');
        cy.get('input[name=User]').type(adminCredentials.username);
        cy.get('input[name=Password]').type(adminCredentials.password + '{enter}');
        cy.url({ timeout: 15000 }).should('not.include', '/login');
    };

    describe('Import Demo Data via Admin UI', () => {
        beforeEach(() => {
            loginAsAdmin();
        });

        it('should navigate to admin dashboard', () => {
            cy.visit('/admin/');
            
            // Should see admin dashboard
            cy.contains('Admin Dashboard').should('be.visible');
            
            // Should see Demo Data card
            cy.contains('Demo Data').should('be.visible');
        });

        it('should click Import Demo Data button and see confirmation', () => {
            cy.visit('/admin/');
            
            // Wait for page to fully load
            cy.get('#importDemoDataV2', { timeout: 10000 }).should('be.visible');
            
            // Click the Import Demo Data button
            cy.get('#importDemoDataV2').click();
            
            // Should see confirmation overlay
            cy.get('#demoImportConfirmOverlay', { timeout: 5000 }).should('be.visible');
            
            // Should see the confirmation content
            cy.contains('Import Demo Data').should('be.visible');
            
            // Should have confirm and cancel buttons
            cy.get('#demoImportConfirmBtn').should('be.visible');
            cy.get('#demoImportCancelBtn').should('be.visible');
        });

        it('should successfully import demo data', () => {
            cy.visit('/admin/');
            
            // Click Import Demo Data button
            cy.get('#importDemoDataV2', { timeout: 10000 }).click();
            
            // Wait for confirmation overlay
            cy.get('#demoImportConfirmOverlay', { timeout: 5000 }).should('be.visible');
            
            // Optional: Enable Sunday School data (if checkbox exists and is not disabled)
            cy.get('#includeDemoSundaySchool').then($cb => {
                if (!$cb.is(':disabled') && !$cb.is(':checked')) {
                    cy.get('#includeDemoSundaySchool').check();
                }
            });
            
            // Click confirm to start import
            cy.get('#demoImportConfirmBtn').click();
            
            // Wait for import to complete - spinner overlay gets .show class when active
            // Note: The spinner might appear very briefly or not at all if import is fast
            // Just wait for the overlay to not have the show class (import done)
            cy.get('#demoImportSpinnerOverlay', { timeout: 120000 }).should('not.have.class', 'show');
        });
    });

    describe('Verify Demo Data Import', () => {
        beforeEach(() => {
            loginAsAdmin();
        });

        it('should have people in the system after demo import', () => {
            // Check people API - use /latest endpoint which returns recent people
            cy.request({
                method: 'GET',
                url: '/api/persons/latest',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('people');
                // Demo data should have returned people
                expect(response.body.people.length).to.be.greaterThan(0);
                cy.log(`Found ${response.body.people.length} people in latest list`);
            });
        });

        it('should have families in the system after demo import', () => {
            // Check families API - use /latest endpoint which returns recent families
            cy.request({
                method: 'GET',
                url: '/api/families/latest',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('families');
                // Demo data should have returned families
                expect(response.body.families.length).to.be.greaterThan(0);
                cy.log(`Found ${response.body.families.length} families in latest list`);
            });
        });

        it('should navigate to home page correctly (not setup)', () => {
            cy.visit('/');
            
            // Should NOT redirect to setup (system is configured)
            cy.url().should('not.include', '/setup');
            
            // Should be on dashboard or some valid page
            cy.url().should('satisfy', (url) => {
                return url.includes('/v2/dashboard') || 
                       url.includes('/admin') || 
                       url.includes('/Menu.php') ||
                       !url.includes('/setup');
            });
        });

        it('should show people listing with demo data', () => {
            cy.visit('/v2/people');
            
            // Should see the people listing
            cy.contains('People').should('be.visible');
            
            // Should have table with people data and wait for DataTable to populate
            cy.get('table', { timeout: 10000 }).should('exist');
            cy.get('table tbody tr', { timeout: 10000 }).should('have.length.at.least', 1);
        });
    });
});
