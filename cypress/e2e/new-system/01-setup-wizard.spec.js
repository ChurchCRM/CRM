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
    let dbConfig = {
        host: 'database-new-system',
        port: '3306',
        name: 'churchcrm',
        user: 'churchcrm',
        password: 'changeme'
    };

    // Default admin credentials after fresh install
    const adminCredentials = {
        username: 'admin',
        password: 'changeme'
    };

    // New password set during the forced change step
    let newAdminPassword = 'AdminP@ss1234!';

    before(() => {
        cy.rememberTestEnv(['db.host', 'db.port', 'db.name', 'db.user', 'db.password', 'admin.new.password']).then(() => {
            dbConfig = {
                host: Cypress.testEnv('db.host') || 'database-new-system',
                port: Cypress.testEnv('db.port') || '3306',
                name: Cypress.testEnv('db.name') || 'churchcrm',
                user: Cypress.testEnv('db.user') || 'churchcrm',
                password: Cypress.testEnv('db.password') || 'changeme'
            };
            newAdminPassword = Cypress.testEnv('admin.new.password') || 'AdminP@ss1234!';
        });
    });

    describe('Fresh Installation', () => {
        it('should display the setup wizard on first visit', () => {
            cy.visit('/');
            cy.url().should('include', '/setup');

            cy.get('.setup-logo').should('be.visible');
            cy.contains('h1', 'Install ChurchCRM').should('be.visible');
            cy.contains('#step-prerequisites', 'Server').should('be.visible');
            cy.contains('#step-website', 'Web address').should('be.visible');
            cy.contains('#step-database', 'Connect your database').should('be.visible');
        });

        it('shows the server check result on the same page as the database form', () => {
            cy.visit('/setup');
            cy.get('#status-banner', { timeout: 30000 }).should('not.have.class', 'status-checking');
            cy.get('#DB_NAME').should('be.visible');
        });

        it('should show advanced settings collapsed by default on configure step', () => {
            cy.visit('/setup');
            cy.get('#advanced-settings-collapse').should('not.have.class', 'show');
            cy.get('[data-bs-target="#advanced-settings-collapse"]').click();
            cy.get('#advanced-settings-collapse').should('have.class', 'show');
            cy.get('#URL').should('be.visible');
            cy.get('#ROOT_PATH').should('be.visible');
        });

        const fillDatabase = (overrides = {}) => {
            const values = { ...dbConfig, ...overrides };
            cy.get('#DB_SERVER_NAME').clear().type(values.host);
            cy.get('#DB_SERVER_PORT').clear().type(String(values.port));
            cy.get('#DB_NAME').clear().type(values.name);
            cy.get('#DB_USER').clear().type(values.user);
            cy.get('#DB_PASSWORD').clear().type(values.password);
        };

        const openConfigureStep = () => {
            cy.visit('/setup');
            cy.get('#DB_NAME').should('be.visible');
        };

        const clickInstallExpecting = (reason, text) => {
            cy.get('#submit-setup').click();
            cy.wait('@setupPost').its('response.body').should((body) => {
                expect(body.correct).to.eq(false);
                expect(body.reason).to.eq(reason);
            });
            cy.get('#db-connection-result').should('be.visible').and('contain.text', text);
            cy.get('#setupModal').should('not.be.visible');
        };

        it('explains each database failure on Install without saving anything', () => {
            cy.intercept('POST', /\/setup\/?$/).as('setupPost');
            openConfigureStep();
            cy.get('#db-connection-result').should('not.be.visible');

            fillDatabase({ host: '127.0.0.1', port: '1' });
            clickInstallExpecting('database_host_unreachable', 'often not localhost');

            fillDatabase({ password: 'not-the-password' });
            cy.get('#db-connection-result').should('not.be.visible');
            clickInstallExpecting('database_login_rejected', 'rejected that username or password');

            fillDatabase({ name: 'churchcrm_missing_db' });
            clickInstallExpecting('database_not_found', 'no database with that name');

            cy.visit('/setup');
            cy.get('.setup-logo').should('be.visible');
        });

        it('names the field to fix when a value is missing', () => {
            openConfigureStep();
            fillDatabase();
            cy.get('#DB_NAME').clear();
            cy.get('#submit-setup').click();
            cy.get('#DB_NAME').should('have.class', 'is-invalid');
            cy.contains('.invalid-feedback', 'Enter the database name').should('be.visible');
            cy.get('#DB_NAME').type('x');
            cy.get('#DB_NAME').should('not.have.class', 'is-invalid');
        });

        it('holds an unsupported database server for an acknowledgment', () => {
            const posts = [];
            cy.intercept('POST', /\/setup\/?$/, (req) => {
                const body = typeof req.body === 'string' ? JSON.parse(req.body) : req.body;
                posts.push(body);
                req.reply(
                    body.ACKNOWLEDGE_UNSUPPORTED_DB
                        ? { valid: true, correct: false, reason: 'database_unreachable', message: 'stubbed stop' }
                        : {
                              valid: true,
                              correct: true,
                              supported: false,
                              message: 'Your database server (MySQL 8.0.36) is older than ChurchCRM supports.',
                          },
                );
            }).as('setupPost');
            openConfigureStep();
            fillDatabase();

            cy.get('#submit-setup').click();
            cy.wait('@setupPost');
            cy.get('#db-version-warning').should('be.visible');
            cy.get('#db-version-message').should('contain.text', 'MySQL 8.0.36');
            cy.get('#submit-setup').should('be.disabled');

            cy.get('#db-version-ack').check();
            cy.get('#submit-setup').should('not.be.disabled').click();
            cy.wait('@setupPost').then(() => {
                expect(posts[posts.length - 1].ACKNOWLEDGE_UNSUPPORTED_DB).to.eq('1');
            });

            cy.get('#DB_NAME').type('x');
            cy.get('#db-version-warning').should('not.be.visible');
            cy.get('#db-version-ack').should('not.be.checked');
            cy.get('#submit-setup').should('not.be.disabled');
        });

        it('should complete full installation with database setup', () => {
            cy.visit('/setup');
            cy.get('#status-banner', { timeout: 30000 }).should('not.have.class', 'status-checking');

            fillDatabase();
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
            cy.location('pathname', { timeout: 10000 }).should('match', /\/session\/begin$/);
            cy.get('input[name=User]', { timeout: 10000 }).should('be.visible');
        });
    });

    describe('Password Change', () => {
        it('should redirect to forced password change on first admin login', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(adminCredentials.password + '{enter}');

            // NeedPasswordChange=true on fresh install → forced change page
            cy.url({ timeout: 15000 }).should('include', '/account/password');
            cy.get('.login-card').should('be.visible');
            cy.contains('Password Change Required').should('be.visible');
        });

        it('should complete forced password change and redirect to church-info', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(adminCredentials.password + '{enter}');

            cy.url({ timeout: 15000 }).should('include', '/account/password');

            cy.get('#OldPassword').type(adminCredentials.password);
            cy.get('#NewPassword1').type(newAdminPassword);
            cy.get('#NewPassword2').type(newAdminPassword);
            cy.get('button[type=submit]').click();

            // ChurchInfoRequiredMiddleware redirects admin to church-info when sChurchName is empty
            cy.url({ timeout: 15000 }).should('include', '/admin/system/church-info');

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
            cy.get('#sChurchZip').should('have.value', '62701');

            // Submit via the real button, settled first - a programmatic
            // .submit() raced the page on master run 45204267654 and the
            // success toast never landed inside the 10s window. The save
            // posts and reloads the page, so give the toast the same 30s
            // the other heavy setup steps in this spec use.
            cy.get('#church-info-form button[type=submit]').should('be.visible').click();

            // Should remain on church-info and show success notification
            cy.url({ timeout: 10000 }).should('include', 'church-info');
            cy.contains('Church information saved successfully', { timeout: 30000 }).should('be.visible');
        });
    });

    describe('First Admin Login', () => {
        // By this point the password has been changed to newAdminPassword and
        // church info has been saved, so normal login lands on the dashboard.

        it('should login with new password and reach the dashboard', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');
            cy.expectStaffSignedIn({ timeout: 15000 });
        });

        it('should show admin dashboard after church info is configured', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');
            cy.expectStaffSignedIn({ timeout: 15000 });

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
            cy.expectStaffSignedIn({ timeout: 15000 });

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

        it('should start with the "Other" event type (#10561)', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');
            cy.expectStaffSignedIn({ timeout: 15000 });

            cy.request('/api/events/types').then((response) => {
                expect(JSON.stringify(response.body)).to.contain('"Name":"Other"');
            });
        });

        it('should reset admin password back to changeme for subsequent specs', () => {
            // A password set in this spec does not carry into specs 02-04, so specs 02-04
            // cannot know the password set here. Reset to the default 'changeme'
            // so all downstream specs can login with the well-known credentials.
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');
            cy.expectStaffSignedIn({ timeout: 15000 });

            cy.visit('/account/password');
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
            cy.expectStaffSignedIn({ timeout: 15000 });
        };

        it('should not show "Forgot password?" link on the login page', () => {
            cy.visit('/login');
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
    });
});
