/// <reference types="cypress" />

/**
 * Upgrade Restore Test
 *
 * Installs a fresh ChurchCRM via the setup wizard, then restores an older
 * database backup (ChurchInfo 1.3.1 or ChurchCRM 6.0.0) and verifies
 * the auto-upgrade completes successfully.
 *
 * Environment variables:
 *   CYPRESS_UPGRADE_SQL_FILE   — path to the SQL file to restore (relative to project root)
 *   CYPRESS_UPGRADE_ADMIN_USER — admin username in the restored database (default: Admin)
 *   CYPRESS_UPGRADE_ADMIN_PASS — admin password in the restored database (default: changeme)
 *
 * Password hash formats supported after migration:
 *   - bcrypt ($2y$)         — current format
 *   - SHA-256 salted        — ChurchCRM 6.x legacy (migrated on login)
 *   - MD5 unsalted          — ChurchInfo 1.x legacy (migrated on login)
 */

describe('Upgrade via Restore', () => {
    const dbConfig = {
        host: Cypress.env('db.host') || 'database-new-system',
        port: Cypress.env('db.port') || '3306',
        name: Cypress.env('db.name') || 'churchcrm',
        user: Cypress.env('db.user') || 'churchcrm',
        password: Cypress.env('db.password') || 'changeme'
    };

    const setupAdmin = {
        username: Cypress.env('admin.username') || 'admin',
        password: Cypress.env('admin.password') || 'changeme'
    };

    const upgradeSqlFile = Cypress.env('UPGRADE_SQL_FILE');
    const upgradeAdminUser = Cypress.env('UPGRADE_ADMIN_USER') || 'Admin';
    const upgradeAdminPass = Cypress.env('UPGRADE_ADMIN_PASS') || 'changeme';

    // New password set during forced password change on the fresh install.
    // Configured in upgrade.config.ts so it can be overridden without editing this file.
    const newAdminPassword = Cypress.env('admin.new.password') || 'AdminP@ss1234!';

    // Password set during the forced-change that follows MD5->bcrypt migration of the
    // restored legacy account (see User::isPasswordValid()). Restored ChurchInfo/legacy
    // credentials are only valid for a single login before this change is required.
    const postUpgradePassword = Cypress.env('admin.post.upgrade.password') || 'PostMigrateP@ss9012!';

    describe('Step 1: Fresh Install via Setup Wizard', () => {
        it('should complete setup wizard', () => {
            cy.visit('/');
            cy.url().should('include', '/setup');

            // Step 1: Prerequisites
            cy.get('#prerequisites-next-btn', { timeout: 30000 }).should('not.be.disabled');
            cy.get('#prerequisites-next-btn').click();

            // Step 2: Database configuration
            cy.get('#step-database').should('have.class', 'active');
            cy.get('#DB_SERVER_NAME').clear().type(dbConfig.host);
            cy.get('#DB_SERVER_PORT').clear().type(dbConfig.port);
            cy.get('#DB_NAME').clear().type(dbConfig.name);
            cy.get('#DB_USER').clear().type(dbConfig.user);
            cy.get('#DB_PASSWORD').clear().type(dbConfig.password);
            cy.get('#DB_PASSWORD_CONFIRM').clear().type(dbConfig.password);

            cy.get('#submit-setup').click();

            // Wait for setup to complete
            cy.get('#setupModal', { timeout: 60000 }).should('be.visible');
            cy.get('#setup-success', { timeout: 120000 }).should('be.visible');
            cy.contains('Installation Complete!').should('be.visible');

            // Continue to login
            cy.get('#setup-footer', { timeout: 10000 }).should('be.visible');
            cy.get('#continue-to-login').should('be.visible').click();
            cy.url({ timeout: 10000 }).should('include', '/session/begin');
        });

        it('should complete forced password change', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(setupAdmin.username);
            cy.get('input[name=Password]').type(setupAdmin.password + '{enter}');
            cy.url({ timeout: 15000 }).should('include', '/changepassword');

            cy.get('#OldPassword').type(setupAdmin.password);
            cy.get('#NewPassword1').type(newAdminPassword);
            cy.get('#NewPassword2').type(newAdminPassword);
            cy.get('button[type=submit]').click();

            cy.url({ timeout: 15000 }).should('include', '/admin/system/church-info');
        });

        it('should fill church info to complete first-run setup', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(setupAdmin.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');
            cy.url({ timeout: 15000 }).should('include', '/admin/system/church-info');

            cy.get('#sChurchCountry', { timeout: 10000 }).siblings('.ts-wrapper').should('exist');
            cy.get('#sChurchName').clear().type('Upgrade Test Church');
            cy.get('#sChurchPhone').clear().type('(555) 000-0000');
            cy.get('#sChurchEmail').clear().type('test@upgrade.org');
            cy.get('#sChurchAddress').clear().type('1 Test Street');
            cy.get('#sChurchCity').clear().type('Springfield');
            cy.get('#sChurchState', { timeout: 10000 }).siblings('.ts-wrapper').should('exist');
            cy.tomSelectByValue('#sChurchState', 'IL');
            cy.get('#sChurchZip').clear().type('62701');

            // Wait for the form submit button to be interactive before submitting
            cy.get('#church-info-form button[type=submit], #church-info-form input[type=submit]').should('not.be.disabled');
            cy.get('#church-info-form').submit();
            // Assert on the visible success message, not the URL (which already contains 'church-info')
            cy.contains('Church information saved successfully', { timeout: 10000 }).should('be.visible');
        });

        it('should verify fresh install is working', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(setupAdmin.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');
            cy.url({ timeout: 15000 }).should('not.include', '/session/begin');
            cy.get('.page, .page-wrapper, .navbar').should('exist');
        });
    });

    describe('Step 2: Restore Old Database', () => {
        it('should have a SQL file configured for restore', () => {
            expect(upgradeSqlFile, 'CYPRESS_UPGRADE_SQL_FILE must be set').to.be.a('string').and.not.be.empty;
            cy.log('Will restore: ' + upgradeSqlFile);
        });

        it('should navigate to restore page', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(setupAdmin.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');
            cy.url({ timeout: 15000 }).should('not.include', '/session/begin');

            cy.visit('/admin/system/restore');
            cy.contains('Restore Database').should('be.visible');
        });

        it('should restore the old SQL file and auto-upgrade', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(setupAdmin.username);
            cy.get('input[name=Password]').type(newAdminPassword + '{enter}');
            cy.url({ timeout: 15000 }).should('not.include', '/session/begin');

            cy.visit('/admin/system/restore');

            // Upload the old SQL file
            cy.get('#restoreFile').selectFile(upgradeSqlFile, { force: true });
            cy.get('#fileInfo').should('be.visible');

            // Click restore
            cy.get('#submitRestore').click();

            // Wait for restore to process (includes auto-upgrade of all migrations)
            cy.get('#statusRunning', { timeout: 10000 }).should('be.visible');

            // Success modal — may take a while for ChurchInfo (30+ migrations)
            cy.get('#restoreSuccessModal', { timeout: 180000 }).should('be.visible');

            // Wait for page to redirect to login after restore completes
            cy.url({ timeout: 30000 }).should('satisfy', (url) => {
                return url.includes('/session/begin') || url.includes('/login');
            });
        });
    });

    describe('Step 2.5: Complete Forced Password Change (MD5 Migration)', () => {
        // The restored legacy account authenticates via the MD5 fallback in
        // User::isPasswordValid(), which forces a password change on that first
        // login (the weak, potentially-compromised MD5 plaintext must not remain
        // in use just because the stored hash got upgraded to bcrypt). This is the
        // one and only login that uses the original restored credentials — every
        // later login in this spec uses postUpgradePassword.
        it('should force a password change after the MD5-migrated login', () => {
            cy.visit('/login');
            cy.get('input[name=User]').type(upgradeAdminUser);
            cy.get('input[name=Password]').type(upgradeAdminPass + '{enter}');
            cy.url({ timeout: 30000 }).should('include', '/changepassword');

            cy.get('#OldPassword').type(upgradeAdminPass);
            cy.get('#NewPassword1').type(postUpgradePassword);
            cy.get('#NewPassword2').type(postUpgradePassword);
            cy.get('button[type=submit]').click();

            cy.url({ timeout: 15000 }).should('not.include', '/changepassword');
        });
    });

    describe('Step 3: Verify Upgraded System', () => {
        // Establish (and cache) a login session with the post-migration admin
        // credentials set in Step 2.5. cy.session() in beforeEach restores from
        // cache on subsequent tests, so the full login round-trip only happens once.
        beforeEach(() => {
            cy.session('upgraded-admin', () => {
                cy.visit('/login');
                cy.get('input[name=User]').type(upgradeAdminUser);
                cy.get('input[name=Password]').type(postUpgradePassword + '{enter}');
                cy.url({ timeout: 30000 }).should('not.include', '/session/begin');
            });
        });

        it('should show the login page after upgrade', () => {
            cy.clearCookies();
            cy.clearLocalStorage();
            cy.visit('/login');
            cy.get('input[name=User]', { timeout: 15000 }).should('be.visible');
            cy.get('input[name=Password]').should('be.visible');
        });

        it('should login with the post-migration admin credentials', () => {
            cy.clearCookies();
            cy.clearLocalStorage();
            cy.visit('/login');
            cy.get('input[name=User]').type(upgradeAdminUser);
            cy.get('input[name=Password]').type(postUpgradePassword + '{enter}');
            // After a successful upgrade + forced password change, the new credentials must work.
            cy.url({ timeout: 30000 }).should('not.include', '/session/begin');
        });

        it('should report current software version and healthy API after upgrade', () => {
            // VersionMiddleware stamps every /api response with the installed version.
            // A non-empty CRM_VERSION header confirms the app is upgraded and serving.
            cy.request({
                method: 'GET',
                url: '/api/persons/latest',
                timeout: 30000,
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.be.an('object');
                const version = response.headers['crm_version'];
                expect(version, 'CRM_VERSION header must be present').to.exist;
                expect(String(version), 'CRM_VERSION must be non-empty').to.not.equal('');
                cy.log(`Upgraded system reporting version: ${version}`);
            });
        });

        it('should access the admin dashboard', () => {
            cy.visit('/admin/');
            cy.contains('Admin Dashboard', { timeout: 15000 }).should('be.visible');
        });

        it('should have a working people API', () => {
            cy.request({
                method: 'GET',
                url: '/api/persons/latest',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('people');
                cy.log(`Found ${response.body.people.length} people after upgrade`);
            });
        });

        it('should have a working families API', () => {
            cy.request({
                method: 'GET',
                url: '/api/families/latest',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('families');
                cy.log(`Found ${response.body.families.length} families after upgrade`);
            });
        });
    });
});
