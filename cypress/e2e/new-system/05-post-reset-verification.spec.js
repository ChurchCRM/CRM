/// <reference types="cypress" />

/**
 * Post-Reset Verification Tests (Steps 10c / 10d / 10e)
 *
 * Runs AFTER 04-system-reset.spec.js has wiped the database.
 *
 * Why this lives in its own spec file: Cypress tears down the browser context
 * between spec files. Splitting here guarantees no in-flight XHRs from the
 * pre-reset session (dashboard widgets, cart-count refresh, etc.) can race
 * with the verification requests on the freshly-wiped database — a race that
 * previously surfaced as "An unknown error has occurred: [object Object]"
 * unhandled rejections.
 *
 * Preconditions (left by 04-system-reset.spec.js):
 * - DB has just been reset
 * - admin / changeme credentials are valid again
 * - NeedPasswordChange=true on admin (forced flow)
 * - sChurchName is empty (ChurchInfoRequiredMiddleware will redirect)
 */

describe('05 - Post-Reset Verification', () => {
    const adminCredentials = {
        username: 'admin',
        password: 'changeme'
    };

    // Helper to manually login, handling forced password-change redirect after a DB reset.
    // Kept local to this spec because it is tightly coupled to the new-system reset flow.
    const manualLogin = () => {
        cy.clearCookies();
        cy.clearLocalStorage();
        const password = adminCredentials.password;
        cy.visit('/login');
        cy.get('input[name=User]', { timeout: 15000 }).type(adminCredentials.username);
        cy.get('input[name=Password]').type(password);
        cy.get('input[name=Password]').type('{enter}');
        cy.url({ timeout: 30000 }).should('not.include', '/session/begin');

        cy.url().then((url) => {
            if (url.includes('/changepassword')) {
                cy.get('#OldPassword').type(password);
                cy.get('#NewPassword1').type('Cypress@01!');
                cy.get('#NewPassword2').type('Cypress@01!');
                cy.get('button[type=submit]').click();
                cy.url({ timeout: 15000 }).should('include', '/admin/system/church-info');
            }
        });

        cy.url().then((url) => {
            if (url.includes('/admin/system/church-info')) {
                cy.get('#sChurchCountry', { timeout: 10000 }).siblings('.ts-wrapper').should('exist');
                cy.get('#sChurchName').clear().type('Test Community Church');
                cy.get('#sChurchPhone').clear().type('(555) 123-4567');
                cy.get('#sChurchEmail').clear().type('info@testchurch.org');
                cy.get('#sChurchAddress').clear().type('123 Main Street');
                cy.get('#sChurchCity').clear().type('Springfield');
                cy.get('#sChurchState', { timeout: 10000 }).siblings('.ts-wrapper').should('exist');
                cy.tomSelectByValue('#sChurchState', 'IL');
                cy.get('#sChurchState').should('have.value', 'IL');
                cy.get('#sChurchZip').clear().type('62701');
                cy.get('#church-info-form').submit();
                cy.url({ timeout: 10000 }).should('include', 'church-info');
            }
        });
    };

    describe('Step 10c: Verify System Reset and Login', () => {
        it('should redirect to login after reset', () => {
            // Clear any cached sessions since the database was just reset.
            cy.clearCookies();
            cy.clearLocalStorage();

            // A DB reset recreates the schema at the current version, so no version
            // mismatch occurs and the db-upgrade page is never shown.
            cy.visit('/');

            cy.url({ timeout: 30000 }).should('include', '/session/begin');
        });

        it('should login with default credentials after reset', () => {
            // manualLogin() uses 'changeme', detects forced /changepassword redirect,
            // and completes it — leaving password as 'Cypress@01!' with NeedPasswordChange=false.
            manualLogin();

            // Change it back to 'changeme' so Steps 10d/10e can login cleanly.
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
                const groups = response.body;
                expect(groups.length).to.equal(0);
                cy.log('No groups found after reset (as expected)');
            });
        });
    });

    describe('Step 10e: Final Verification', () => {
        it('should have a clean system ready for use', () => {
            manualLogin();

            cy.visit('/admin/');
            cy.contains('Admin Dashboard', { timeout: 15000 }).should('be.visible');

            cy.log('System reset complete - clean installation verified');
        });
    });
});
