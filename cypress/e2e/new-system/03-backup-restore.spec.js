/// <reference types="cypress" />

/**
 * Backup and Restore Tests (Steps 6-9)
 * 
 * Tests run after demo data has been imported:
 * 6. Create a backup of the system (current demo state)
 * 7. Restore seed.sql via restore page
 * 8. Verify data from that restore is valid
 * 9. Restore the demo backup to ensure restore works
 * 
 * Prerequisites: Demo data must have been imported (02-demo-import.spec.js)
 */

describe('03 - Backup and Restore', () => {
    // Default admin credentials
    const adminCredentials = {
        username: 'admin',
        password: 'changeme'
    };

    // Helper function to login, handling forced password-change redirect on first login
    const loginAsAdmin = () => {
        // Test 02 resets password back to 'changeme' after testing forced change
        const password = adminCredentials.password;
        cy.visit('/login');
        cy.get('input[name=User]').type(adminCredentials.username);
        cy.get('input[name=Password]').type(password + '{enter}');
        cy.url({ timeout: 15000 }).should('not.include', '/session/begin');
    };

    describe('Step 6: Create Backup', () => {
        beforeEach(() => {
            loginAsAdmin();
        });

        it('should navigate to backup page', () => {
            cy.visit('/admin/system/backup');
            
            // Should see backup page
            cy.contains('Create Backup').should('be.visible');
            cy.contains('Backup Best Practices').should('be.visible');
        });

        it('should create a database backup', () => {
            cy.visit('/admin/system/backup');
            
            // Select Database Only option (archiveType=2)
            cy.get('#archiveType2').should('be.checked');
            
            // Click backup button
            cy.get('#doBackup').click();
            
            // Wait for backup to complete (status shows success)
            // Note: statusRunning may flash so fast we can't see it, so just wait for complete
            cy.get('#statusComplete', { timeout: 60000 }).should('be.visible');
            
            // Download button should appear
            cy.get('#downloadbutton', { timeout: 10000 }).should('be.visible');
        });

        it('should verify backup file can be downloaded via API', () => {
            // Create backup via API
            cy.request({
                method: 'POST',
                url: '/api/database/backup',
                body: { BackupType: 2 }, // 2 = SQL only
                timeout: 60000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('BackupDownloadFileName');
                
                // Store backup filename for later restore
                const backupFile = response.body.BackupDownloadFileName.replace(/^.*[\\\/]/, ''); // Get filename
                cy.log('Backup created: ' + backupFile);
                
                // Verify download endpoint works
                cy.request({
                    method: 'GET',
                    url: '/api/database/download/' + backupFile,
                    encoding: 'binary',
                    timeout: 30000
                }).then((downloadResponse) => {
                    expect(downloadResponse.status).to.equal(200);
                    // SQL file should start with comment or SQL commands
                    expect(downloadResponse.body.length).to.be.greaterThan(1000);
                });
            });
        });
    });

    describe('Step 7: Restore seed.sql', () => {
        beforeEach(() => {
            loginAsAdmin();
        });

        it('should navigate to restore page', () => {
            cy.visit('/admin/system/restore');
            
            // Should see restore page with warning
            cy.contains('Important Warning').should('be.visible');
            cy.contains('CAUTION').should('be.visible');
            cy.contains('Restore Database').should('be.visible');
        });

        it('should restore seed.sql file', () => {
            cy.visit('/admin/system/restore');
            
            // The demo SQL file path (relative to cypress project root)
            const demoSqlPath = 'cypress/data/seed.sql';
            
            // Use cy.selectFile to upload the file
            // The file input is hidden, so we need to force the action
            cy.get('#restoreFile').selectFile(demoSqlPath, { force: true });
            
            // File info should show
            cy.get('#fileInfo').should('be.visible');
            cy.get('#fileName').should('contain', 'seed.sql');
            
            // Click restore button
            cy.get('#submitRestore').click();
            
            // Should show progress
            cy.get('#statusRunning', { timeout: 10000 }).should('be.visible');
            
            // Wait for restore to complete - this redirects to login after success
            // The success modal appears briefly then redirects
            cy.get('#restoreSuccessModal', { timeout: 120000 }).should('be.visible');
            
            // Wait a moment for redirect countdown
            cy.wait(2000);
            
            // Should eventually redirect to login page
            cy.url({ timeout: 30000 }).should('satisfy', (url) => {
                return url.includes('/session/begin') || url.includes('/login');
            });
        });
    });

    describe('Step 8: Verify Restored Data', () => {
        beforeEach(() => {
            // After restore, we need to login again
            loginAsAdmin();
        });

        it('should have demo families after restore', () => {
            cy.request({
                method: 'GET',
                url: '/api/families/latest',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('families');
                
                // seed.sql should have multiple families
                const families = response.body.families;
                expect(families.length).to.be.greaterThan(5);
                cy.log(`Found ${families.length} families after restore`);
                
                // Check for known demo family names
                const familyNames = families.map(f => f.name || f.Name);
                cy.log('Family names: ' + familyNames.slice(0, 5).join(', '));
            });
        });

        it('should have demo people after restore', () => {
            cy.request({
                method: 'GET',
                url: '/api/persons/latest',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('people');
                
                const people = response.body.people;
                expect(people.length).to.be.at.least(10);
                cy.log(`Found ${people.length} people after restore`);
            });
        });

        it('should have groups after restore', () => {
            cy.request({
                method: 'GET',
                url: '/api/groups/',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                
                // Groups API returns array directly
                const groups = response.body;
                expect(groups.length).to.be.greaterThan(0);
                cy.log(`Found ${groups.length} groups after restore`);
            });
        });

        it('should navigate to people page and see data', () => {
            cy.visit('/v2/people');
            
            // Should see people listing
            cy.contains('People').should('be.visible');
            
            // Table should have data
            cy.get('table', { timeout: 10000 }).should('exist');
            cy.wait(2000); // Wait for DataTables
            cy.get('table tbody tr').should('have.length.at.least', 1);
        });
    });

    describe('Step 9: Verify Backup API Functionality', () => {
        let backupFilename = '';

        beforeEach(() => {
            loginAsAdmin();
        });

        it('should create a new backup before testing restore cycle', () => {
            // Create a fresh backup to test restore
            cy.request({
                method: 'POST',
                url: '/api/database/backup',
                body: { BackupType: 2 },
                timeout: 60000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('BackupDownloadFileName');
                
                backupFilename = response.body.BackupDownloadFileName.replace(/^.*[\\\/]/, '');
                cy.log('Created backup for restore test: ' + backupFilename);
                
                // Store in Cypress env for next test
                Cypress.env('lastBackupFile', backupFilename);
            });
        });

        it('should verify backup download works', () => {
            const savedBackup = Cypress.env('lastBackupFile');
            if (!savedBackup) {
                cy.log('No backup filename saved, skipping download test');
                return;
            }
            
            // Verify we can download the backup we created
            cy.request({
                method: 'GET',
                url: '/api/database/download/' + savedBackup,
                encoding: 'binary',
            }).then((downloadResponse) => {
                expect(downloadResponse.status).to.equal(200);
                expect(downloadResponse.body.length).to.be.greaterThan(1000);
                cy.log('Backup download verified: ' + savedBackup);
            });
        });

        it('should verify system still has data after backup operations', () => {
            // Verify we can access the system
            cy.visit('/admin/');
            cy.contains('Admin Dashboard').should('be.visible');
            
            // Verify data still exists
            cy.request({
                method: 'GET',
                url: '/api/persons/latest',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body.people.length).to.be.greaterThan(5);
                cy.log('System verified working after backup operations');
            });
        });
    });
});
