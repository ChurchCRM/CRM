/// <reference types="cypress" />

/**
 * System Reset Tests (Step 10)
 * 
 * Tests the complete reset workflow:
 * 10. Do a system reset, login, ensure everything is blank
 * 
 * Prerequisites: Previous tests must have run (backup/restore completed)
 * 
 * After reset:
 * - All database tables are recreated
 * - Config.php persists (no setup wizard)
 * - System starts fresh with admin/changeme credentials
 */

describe('04 - System Reset', () => {
    // Default admin credentials
    const adminCredentials = {
        username: 'admin',
        password: 'changeme'
    };

    // Helper to manually login, handling forced password-change redirect after a DB reset
    const manualLogin = () => {
        cy.clearCookies();
        cy.clearLocalStorage();
        // Admin password is 'changeme'. After a DB reset NeedPasswordChange=true,
        // which forces a redirect to /changepassword on first login.
        const password = adminCredentials.password;
        cy.visit('/login');
        cy.get('input[name=User]', { timeout: 15000 }).type(adminCredentials.username);
        cy.get('input[name=Password]').type(password);
        cy.get('input[name=Password]').type('{enter}');
        cy.url({ timeout: 30000 }).should('not.include', '/session/begin');
        // Give the session time to establish
        cy.wait(1000);

        // After a DB reset the admin has NeedPasswordChange=true; complete the forced form if needed.
        // The forced form uses button[type=submit] (login-box layout, not card layout).
        cy.url().then((url) => {
            if (url.includes('/changepassword')) {
                cy.get('#OldPassword').type(password);
                cy.get('#NewPassword1').type('Cypress@01!');
                cy.get('#NewPassword2').type('Cypress@01!');
                cy.get('button[type=submit]').click();
                cy.contains('Password Changed', { timeout: 10000 }).should('be.visible');
            }
        });
    };

    describe('Step 10a: Navigate to Reset Page', () => {
        it('should navigate to system reset page and show bootbox warning', () => {
            manualLogin();
            cy.visit('/admin/system/reset');
            
            // Wait for page to fully load and i18next to initialize
            // The bootbox appears after CRM.onLocalesReady fires
            cy.get('.bootbox', { timeout: 30000 }).should('be.visible');
            
            // Should show warning about reset
            cy.contains('Warning').should('be.visible');
        });

        it('should show reset button after typing I AGREE', () => {
            manualLogin();
            cy.visit('/admin/system/reset');
            
            // Wait for bootbox prompt
            cy.get('.bootbox', { timeout: 30000 }).should('be.visible');
            
            // Type "I AGREE" in the prompt
            cy.get('.bootbox input[type="text"]').type('I AGREE');
            
            // Click OK button on the bootbox
            cy.get('.bootbox .btn-danger').click();
            
            // Should now see the reset page content (bootbox dismissed)
            cy.get('#confirm-db', { timeout: 10000 }).should('be.visible');
            cy.contains('Reset Database').should('be.visible');
        });
    });

    describe('Step 10b: Perform System Reset', () => {
        it('should reset the database via API', () => {
            manualLogin();

            // Perform reset via API
            cy.request({
                method: 'DELETE',
                url: '/admin/api/database/reset',
                timeout: 60000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('success', true);
                expect(response.body).to.have.property('msg');
                expect(response.body).to.have.property('defaultUsername', 'admin');
                expect(response.body).to.have.property('defaultPassword', 'changeme');
                
                cy.log('Database reset successful');
            });
        });
    });

    describe('Step 10c: Verify System Reset and Login', () => {
        it('should redirect to login or db-upgrade after reset', () => {
            // Clear any cached sessions since we just reset the database
            cy.clearCookies();
            cy.clearLocalStorage();
            
            // Visit homepage - should redirect somewhere after reset
            cy.visit('/');
            
            // After reset, may go to:
            // - /session/begin or /login (if DB version matches code)
            // - /external/system/db-upgrade (if DB needs upgrade)
            cy.url({ timeout: 30000 }).should('satisfy', (url) => {
                return url.includes('/session/begin') || 
                       url.includes('/login') || 
                       url.includes('/db-upgrade');
            });
            
            // If on db-upgrade page, wait for it to complete and redirect
            cy.url().then((url) => {
                if (url.includes('/db-upgrade')) {
                    cy.log('DB upgrade required after reset');
                    cy.url({ timeout: 60000 }).should('satisfy', (newUrl) => {
                        return newUrl.includes('/session/begin') || newUrl.includes('/login');
                    });
                }
            });
        });

        it('should login with default credentials after reset', () => {
            // manualLogin() uses 'changeme', detects forced /changepassword redirect,
            // and completes it — leaving password as 'Cypress@01!' with NeedPasswordChange=false.
            manualLogin();

            // Now change it back to 'changeme' so Steps 10d/10e can login cleanly.
            // NeedPasswordChange=false so this shows the voluntary form (input[type=submit]).
            cy.visit('/v2/user/current/changepassword');
            cy.get('#OldPassword').type('Cypress@01!');
            cy.get('#NewPassword1').type('changeme');
            cy.get('#NewPassword2').type('changeme');
            cy.get('input[type=submit]').click();
            cy.contains('Password Change Successful', { timeout: 10000 }).should('be.visible');
        });
    });

    describe('Step 10d: Verify System is Blank', () => {
        beforeEach(() => {
            manualLogin();
        });

        it('should verify no people exist (except admin)', () => {
            cy.request({
                method: 'GET',
                url: '/api/persons/latest',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('people');
                
                // Should only have admin user (1 person max)
                const people = response.body.people;
                expect(people.length).to.be.lessThan(2);
                cy.log(`Found ${people.length} people after reset (expected 0-1)`);
            });
        });

        it('should verify no families exist', () => {
            cy.request({
                method: 'GET',
                url: '/api/families/latest',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('families');
                
                // Should have no families
                const families = response.body.families;
                expect(families.length).to.equal(0);
                cy.log('No families found after reset (as expected)');
            });
        });

        it('should verify no groups exist', () => {
            cy.request({
                method: 'GET', 
                url: '/api/groups/',
                timeout: 30000,
                failOnStatusCode: false
            }).then((response) => {
                if (response.status === 200) {
                    // Groups API returns array directly
                    const groups = response.body;
                    expect(groups.length).to.equal(0);
                    cy.log('No groups found after reset (as expected)');
                }
            });
        });
    });

    describe('Step 10e: Final Verification', () => {
        it('should have a clean system ready for use', () => {
            manualLogin();
            
            // Verify admin dashboard is accessible
            cy.visit('/admin/');
            cy.contains('Admin Dashboard', { timeout: 15000 }).should('be.visible');
            
            // The system is now blank and ready for a fresh start
            cy.log('System reset complete - clean installation verified');
        });
    });
});
