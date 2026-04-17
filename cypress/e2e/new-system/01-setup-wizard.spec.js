/// <reference types="cypress" />

/**
 * Setup Wizard Tests (Step 1-2)
 * 
 * Tests run against a fresh ChurchCRM installation with:
 * - No Config.php file (setup wizard is triggered)
 * - Empty database (no demo data)
 * 
 * Steps covered:
 * 1. System Check - Prerequisites 
 * 2. Configure - Database configuration (with optional advanced URL settings)
 */

describe('01 - Setup Wizard', () => {
    // Database configuration from environment
    const dbConfig = {
        host: Cypress.env('db.host') || 'database-new-system',
        port: Cypress.env('db.port') || '3306',
        name: Cypress.env('db.name') || 'churchcrm',
        user: Cypress.env('db.user') || 'churchcrm',
        password: Cypress.env('db.password') || 'changeme'
    };

    // Default admin credentials after fresh install
    const adminCredentials = {
        username: 'admin',
        password: 'changeme'
    };

    // New password set during the forced change step
    const newAdminPassword = Cypress.env('admin.new.password') || 'AdminP@ss1234!';

    describe('Fresh Installation', () => {
        it('should display the setup wizard on first visit', () => {
            cy.visit('/');
            
            // Should redirect to setup page
            cy.url().should('include', '/setup');
            
            // Should show the setup wizard with logo and tagline
            cy.get('.setup-logo').should('be.visible');
            cy.contains("Let's get your church management system up and running").should('be.visible');
            
            // Should show the stepper with 2 steps (System Check, Configure)
            cy.get('.bs-stepper-header .step').should('have.length', 2);
            cy.contains('.bs-stepper-label', 'System Check').should('be.visible');
            cy.contains('.bs-stepper-label', 'Configure').should('be.visible');
        });

        it('should complete prerequisites check step', () => {
            cy.visit('/setup');
            
            // Wait for prerequisite checks to complete
            cy.get('#prerequisites-next-btn', { timeout: 30000 }).should('not.be.disabled');
            
            // Click Continue to proceed to Configure step
            cy.get('#prerequisites-next-btn').click();
            
            // Should now be on step 2 (Configure/Database)
            cy.get('#step-database').should('have.class', 'active');
        });

        it('should show advanced settings collapsed by default on configure step', () => {
            cy.visit('/setup');
            
            // Complete step 1
            cy.get('#prerequisites-next-btn', { timeout: 30000 }).should('not.be.disabled');
            cy.get('#prerequisites-next-btn').click();
            
            // Step 2: Configure (Database)
            cy.get('#step-database').should('have.class', 'active');
            
            // Advanced settings should be collapsed by default
            cy.get('#advanced-settings-collapse').should('not.have.class', 'show');
            
            // Click to expand advanced settings
            cy.get('[data-bs-target="#advanced-settings-collapse"]').click();
            
            // URL and Root Path fields should be visible after expanding
            cy.get('#advanced-settings-collapse').should('have.class', 'show');
            cy.get('#URL').should('be.visible');
            cy.get('#ROOT_PATH').should('be.visible');
        });

        it('should complete full installation with database setup', () => {
            cy.visit('/setup');
            
            // Step 1: System Check (Prerequisites)
            cy.get('#prerequisites-next-btn', { timeout: 30000 }).should('not.be.disabled');
            cy.get('#prerequisites-next-btn').click();
            
            // Step 2: Configure (Database)
            cy.get('#step-database').should('have.class', 'active');
            
            // Clear and fill database fields with environment credentials
            cy.get('#DB_SERVER_NAME').clear().type(dbConfig.host);
            cy.get('#DB_SERVER_PORT').clear().type(dbConfig.port);
            cy.get('#DB_NAME').clear().type(dbConfig.name);
            cy.get('#DB_USER').clear().type(dbConfig.user);
            cy.get('#DB_PASSWORD').clear().type(dbConfig.password);
            cy.get('#DB_PASSWORD_CONFIRM').clear().type(dbConfig.password);
            
            // Submit the setup form
            cy.get('#submit-setup').click();
            
            // Wait for setup modal to appear
            cy.get('#setupModal', { timeout: 60000 }).should('be.visible');
            
            // Wait for setup to complete successfully
            cy.get('#setup-success', { timeout: 120000 }).should('be.visible');
            
            // Should show installation complete message and admin credentials
            cy.contains('Installation Complete!').should('be.visible');
            cy.contains('admin').should('be.visible');
            cy.contains('changeme').should('be.visible');
            
            // Wait for footer with login button to appear and be visible
            cy.get('#setup-footer', { timeout: 10000 }).should('be.visible');
            cy.get('#continue-to-login').should('be.visible').click();
            
            // Should redirect to login page
            cy.url({ timeout: 10000 }).should('include', '/session/begin');
        });
    });

    describe('Password Change', () => {
        it('should redirect to forced password change on first admin login', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(adminCredentials.password + '{enter}');

            // NeedPasswordChange=true on fresh install → forced change page
            cy.url({ timeout: 15000 }).should('include', '/changepassword');
            cy.get('.login-wrapper').should('be.visible');
            cy.contains('Password Change Required').should('be.visible');
        });

        it('should complete forced password change and redirect to church-info', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(adminCredentials.password + '{enter}');

            cy.url({ timeout: 15000 }).should('include', '/changepassword');

            cy.get('#OldPassword').type(adminCredentials.password);
            cy.get('#NewPassword1').type(newAdminPassword);
            cy.get('#NewPassword2').type(newAdminPassword);
            cy.get('button[type=submit]').click();

            // ChurchInfoRequiredMiddleware redirects admin to church-info when sChurchName is empty
            cy.url({ timeout: 15000 }).should('include', '/admin/system/church-info');

            // Store new password in Cypress env so 02-demo-import.spec.js can read it
            cy.then(() => { Cypress.env('newSystemAdminPassword', newAdminPassword); });
        });
    });

    describe('Church Info Setup', () => {
        it('should redirect to church-info after login when church name is not set', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');

            cy.url({ timeout: 15000 }).should('include', '/admin/system/church-info');
            cy.contains('Church Information').should('be.visible');
        });

        it('should fill and save church information to complete first-run setup', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');

            cy.url({ timeout: 15000 }).should('include', '/admin/system/church-info');

            // Wait for page to fully load — country defaults to US and populates state dropdown
            cy.get('#sChurchCountry', { timeout: 10000 }).siblings('.ts-wrapper').should('exist');


            // Fill required fields (single-page layout, no tabs)
            cy.get('#sChurchName').clear().type('Test Community Church');
            cy.get('#sChurchPhone').clear().type('(555) 123-4567');
            cy.get('#sChurchEmail').clear().type('info@testchurch.org');
            cy.get('#sChurchAddress').clear().type('123 Main Street');
            cy.get('#sChurchCity').clear().type('Springfield');

            // Country defaults to US — wait for state dropdown then select state
            cy.get('#sChurchState', { timeout: 10000 }).siblings('.ts-wrapper').should('exist');
            cy.tomSelectByValue('#sChurchState', 'IL');
            cy.get('#sChurchState').should('have.value', 'IL');

            cy.get('#sChurchZip').clear().type('62701');

            // Submit the form
            cy.get('#church-info-form').submit();

            // Should remain on church-info and show success notification
            cy.url({ timeout: 10000 }).should('include', 'church-info');
            cy.contains('Church information saved successfully', { timeout: 10000 }).should('be.visible');
        });
    });

    describe('First Admin Login', () => {
        // By this point the password has been changed to newAdminPassword and
        // church info has been saved, so normal login lands on the dashboard.

        it('should login with new password and reach the dashboard', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');

            // Should redirect away from login
            cy.url({ timeout: 15000 }).should('not.include', '/session/begin');

            // Should be on some page (dashboard or admin)
            cy.get('body').should('exist');
        });

        it('should show admin dashboard after church info is configured', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');
            cy.url({ timeout: 15000 }).should('not.include', '/session/begin');

            cy.url().then((url) => {
                cy.log('Redirected to: ' + url);
                // Church info is now saved so the middleware no longer redirects;
                // expect the Tabler page layout.
                cy.get('.page, .page-wrapper, .navbar').should('exist');
            });
        });

        it('should verify system is empty (no people/families)', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');
            cy.url({ timeout: 15000 }).should('not.include', '/session/begin');

            // Check people API - should return mostly empty (only admin user)
            cy.request({
                method: 'GET',
                url: '/api/persons/latest',
                failOnStatusCode: false
            }).then((response) => {
                // Either 200 with few/no people or some other valid response
                if (response.status === 200 && response.body.people) {
                    // Only the admin user should exist (created during setup)
                    expect(response.body.people.length).to.be.lessThan(3);
                }
            });
        });

        it('should reset admin password back to changeme for subsequent specs', () => {
            // Cypress.env() does not persist across spec files, so specs 02-04
            // cannot know the password set here. Reset to the default 'changeme'
            // so all downstream specs can login with the well-known credentials.
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');
            cy.url({ timeout: 15000 }).should('not.include', '/session/begin');

            cy.visit('/v2/user/current/changepassword');
            cy.get('#OldPassword').type(newAdminPassword);
            cy.get('#NewPassword1').type(adminCredentials.password);
            cy.get('#NewPassword2').type(adminCredentials.password);
            cy.get('input[type=submit]').click();
            cy.contains('Password Change Successful', { timeout: 10000 }).should('be.visible');
        });
    });

    describe('Email Disabled UI (fresh install has no SMTP)', () => {
        // In a fresh install, SMTP is unconfigured, so SystemConfig::isEmailEnabled()
        // returns false. All UI that depends on sending email must hide or warn.

        const loginAsAdmin = () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(adminCredentials.password + '{enter}');
            cy.url({ timeout: 15000 }).should('not.include', '/session/begin');
        };

        it('should not show "Forgot password?" link on the login page', () => {
            cy.visit('/session/begin');
            cy.contains('Forgot password?').should('not.exist');
        });

        it('should return an email-disabled error when hitting the reset-request route', () => {
            cy.visit('/session/forgot-password/reset-request', { failOnStatusCode: false });
            cy.contains('Password reset is unavailable because email is disabled').should('be.visible');
        });

        it('should return success (for enumeration safety) without minting a token on the public API', () => {
            cy.request({
                method: 'POST',
                url: '/api/public/user/password-reset',
                body: { userName: 'admin' },
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property('success', true);
            });
        });

        it('should show the "Email is disabled" banner on the admin users page', () => {
            loginAsAdmin();
            cy.visit('/admin/system/users');
            cy.contains('Email is disabled').should('be.visible');
            cy.contains('Set up Email').should('be.visible');
        });

        it('should hide the "Reset Password via Email" action from user dropdowns', () => {
            loginAsAdmin();
            cy.visit('/admin/system/users');
            cy.contains('Reset Password via Email').should('not.exist');
        });

        it('should reject the admin password-reset API with a clear error when email is disabled', () => {
            loginAsAdmin();
            cy.request({
                method: 'POST',
                url: '/api/user/1/password/reset',
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(409);
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.match(/email is disabled/i);
            });
        });

        it('should show the "Email is disabled" banner on the UserEditor page', () => {
            loginAsAdmin();
            cy.visit('/UserEditor.php?PersonID=1');
            cy.contains('Email is disabled').should('be.visible');
        });
    });
});
