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

    // Helper function to login, handling forced password-change redirect on first login
    const loginAsAdmin = () => {
        const password = Cypress.env('newSystemAdminPassword') || adminCredentials.password;
        cy.visit('/login');
        cy.get('input[name=User]').type(adminCredentials.username);
        cy.get('input[name=Password]').type(password + '{enter}');
        cy.url({ timeout: 15000 }).should('not.include', '/session/begin');

        // Fresh-install admin has NeedPasswordChange=true; complete the forced form if needed
        cy.url().then((url) => {
            if (url.includes('/changepassword')) {
                const newPassword = 'Cypress@01!';
                cy.get('#OldPassword').type(password);
                cy.get('#NewPassword1').type(newPassword);
                cy.get('#NewPassword2').type(newPassword);
                cy.get('button[type=submit]').click();
                cy.contains('Password Changed', { timeout: 10000 }).should('be.visible');
                Cypress.env('newSystemAdminPassword', newPassword);
            }
        });
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
            
            // Give page time to settle after import
            cy.wait(1000);
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

            // Should have table with people data
            cy.get('table', { timeout: 10000 }).should('exist');

            // Wait for table to populate (DataTable lazy loading)
            cy.wait(2000);

            // Should have rows with people
            cy.get('table tbody tr').should('have.length.at.least', 1);
        });
    });

    describe('Verify Finance Demo Data', () => {
        beforeEach(() => {
            loginAsAdmin();
        });

        it('should have deposits in the system after demo import', () => {
            cy.request({
                method: 'GET',
                url: '/api/deposits',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                // /api/deposits returns { Deposits: [...] } (Propel collection format)
                expect(response.body).to.have.property('Deposits');
                expect(response.body.Deposits).to.be.an('array');
                expect(response.body.Deposits.length).to.be.greaterThan(0);
                cy.log(`Found ${response.body.Deposits.length} deposit(s)`);
            });
        });

        it('should have at least one open deposit', () => {
            cy.request({
                method: 'GET',
                url: '/api/deposits',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                // /api/deposits returns { Deposits: [...] } (Propel collection format)
                expect(response.body).to.have.property('Deposits');
                expect(response.body.Deposits).to.be.an('array');

                // Closed is stored as bool (false = open, true = closed)
                const openDeposits = response.body.Deposits.filter(d => !d.Closed);
                expect(openDeposits.length).to.be.greaterThan(0);
                cy.log(`Found ${openDeposits.length} open deposit(s) out of ${response.body.Deposits.length} total`);
            });
        });

        it('should have payments in the system after demo import', () => {
            cy.request({
                method: 'GET',
                url: '/api/deposits',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('Deposits');
                const deposits = response.body.Deposits;
                expect(deposits.length).to.be.greaterThan(0);

                // Use the first deposit (oldest, closed) — it always has payments linked via dep_id
                const depositId = deposits[0].Id;
                return cy.request({
                    method: 'GET',
                    url: `/api/deposits/${depositId}/payments`,
                    timeout: 30000
                });
            }).then((paymentsResponse) => {
                expect(paymentsResponse.status).to.equal(200);
                expect(paymentsResponse.body).to.be.an('array');
                expect(paymentsResponse.body.length).to.be.greaterThan(0);
                cy.log(`Found ${paymentsResponse.body.length} payment(s) in deposit`);
            });
        });

        it('should show finance dashboard after demo import', () => {
            cy.visit('/finance/');

            // Should see the finance dashboard title
            cy.contains('Finance Dashboard').should('be.visible');
        });

        it('should reset admin password back to changeme for subsequent tests', () => {
            // Tests 03-04 expect 'changeme' as the default password
            // Change password from 'Cypress@01!' back to 'changeme'
            cy.visit('/v2/user/current/changepassword');

            const currentPassword = 'Cypress@01!';
            const newPassword = 'changeme';

            // Fill in change password form
            cy.get('#OldPassword').type(currentPassword);
            cy.get('#NewPassword1').type(newPassword);
            cy.get('#NewPassword2').type(newPassword);
            cy.get('input[type=submit]').click();

            // Should show success message
            cy.contains('Password Change Successful', { timeout: 10000 }).should('be.visible');
        });
    });
});
