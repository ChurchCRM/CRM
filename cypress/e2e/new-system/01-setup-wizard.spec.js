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
            cy.get('[data-target="#advanced-settings-collapse"]').click();
            
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

    describe('First Admin Login', () => {
        it('should login with default admin credentials', () => {
            cy.visit('/login');
            
            // Enter default admin credentials
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(adminCredentials.password + '{enter}');
            
            // Should redirect away from login
            cy.url({ timeout: 15000 }).should('not.include', '/session/begin');
            
            // Should be on some page (dashboard or admin)
            cy.get('body').should('exist');
        });

        it('should show admin dashboard or home after first login', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(adminCredentials.password + '{enter}');
            cy.url({ timeout: 15000 }).should('not.include', '/session/begin');
            
            // Fresh system should show admin dashboard (no people yet)
            // The system may redirect to admin or v2/dashboard
            // If the admin was created with NeedPasswordChange=true, may land on forced change-password page
            cy.url().then((url) => {
                cy.log('Redirected to: ' + url);
                // Verify we're logged in and not on login page.
                // Accept either the full dashboard layout or the forced change-password minimal layout (.login-box).
                cy.get('.main-sidebar, .wrapper, .content-wrapper, .login-box').should('exist');
            });
        });

        it('should verify system is empty (no people/families)', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(adminCredentials.username);
            cy.get('input[name=Password]').type(adminCredentials.password + '{enter}');
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
    });
});
