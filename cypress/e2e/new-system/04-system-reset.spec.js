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

        // After a DB reset the admin has NeedPasswordChange=true; complete the forced form if needed.
        // The forced form uses button[type=submit] (login-box layout, not card layout).
        cy.url().then((url) => {
            if (url.includes('/changepassword')) {
                cy.get('#OldPassword').type(password);
                cy.get('#NewPassword1').type('Cypress@01!');
                cy.get('#NewPassword2').type('Cypress@01!');
                cy.get('button[type=submit]').click();
                // ChurchInfoRequiredMiddleware redirects to church-info when sChurchName is empty
                cy.url({ timeout: 15000 }).should('include', '/admin/system/church-info');
            }
        });

        // After a DB reset sChurchName is empty; fill in the minimum required fields so the
        // middleware stops redirecting and subsequent test navigation works normally.
        cy.url().then((url) => {
            if (url.includes('/admin/system/church-info')) {
                // Wait for page to fully load — country defaults to US and populates state dropdown
                cy.get('#sChurchCountry', { timeout: 10000 }).siblings('.ts-wrapper').should('exist');
                cy.get('#sChurchName').clear().type('Test Community Church');
                cy.get('#sChurchPhone').clear().type('(555) 123-4567');
                cy.get('#sChurchEmail').clear().type('info@testchurch.org');
                cy.get('#sChurchAddress').clear().type('123 Main Street');
                cy.get('#sChurchCity').clear().type('Springfield');
                // Country defaults to US — wait for state dropdown then verify value is set
                cy.get('#sChurchState', { timeout: 10000 }).siblings('.ts-wrapper').should('exist');
                cy.tomSelectByValue('#sChurchState', 'IL');
                cy.get('#sChurchState').should('have.value', 'IL');
                cy.get('#sChurchZip').clear().type('62701');
                cy.get('#church-info-form').submit();
                cy.url({ timeout: 10000 }).should('include', 'church-info');
            }
        });
    };

    describe('Step 10a: Navigate to Reset Page', () => {
        it('should display danger warning and reset card', () => {
            manualLogin();
            cy.visit('/admin/system/reset');

            // Danger banner at top — scope to the top warning banner so it
            // doesn't match hidden backup status alerts also in the DOM.
            cy.contains('.alert-danger', 'Destructive Operation', { timeout: 15000 })
                .should('be.visible');

            // Reset button should be disabled until user types RESET
            cy.get('#resetBtn').should('be.disabled');
        });

        it('should enable reset button after typing RESET', () => {
            manualLogin();
            cy.visit('/admin/system/reset');

            cy.get('#confirmInput', { timeout: 15000 }).type('RESET');
            cy.get('#resetBtn').should('not.be.disabled');
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

});
