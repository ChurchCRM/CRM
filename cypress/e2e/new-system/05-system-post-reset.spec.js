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

describe('05 - System Post Reset', () => {
    describe('Step 10c: Verify System Reset and Login', () => {
        it('should redirect to login after reset', () => {
            // Clear any cached sessions since we just reset the database
            cy.clearCookies();
            cy.clearLocalStorage();

            // Visit homepage - should redirect to login after reset.
            // A DB reset recreates the schema at the current version, so no version
            // mismatch occurs and the db-upgrade page is never shown.
            cy.visit('/');

            cy.url({ timeout: 30000 }).should('satisfy', (url) => {
                return url.includes('/session/begin') || url.includes('/login');
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
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);

                // Groups API returns array directly
                const groups = response.body;
                expect(groups.length).to.equal(0);
                cy.log('No groups found after reset (as expected)');
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
