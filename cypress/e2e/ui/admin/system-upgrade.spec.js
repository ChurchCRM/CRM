describe('System Upgrade Page', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should load and display compact version info', () => {
        cy.visit('/admin/system/upgrade');

        cy.contains('Installed').should('be.visible');
        cy.get('.badge.bg-primary-lt').should('be.visible').and('not.be.empty');
        cy.get('#refreshFromGitHub').should('be.visible');
    });

    it('should display the upgrade wizard with all steps', () => {
        cy.visit('/admin/system/upgrade');

        cy.get('#upgrade-wizard-card').should('be.visible');

        cy.get('.bs-stepper-header').within(() => {
            cy.contains('Pre-flight').should('exist');
            cy.contains('Backup').should('exist');
            cy.contains('Download & Apply').should('exist');
            cy.contains('Complete').should('exist');
        });

        cy.get('#step-warnings').should('be.visible');
    });

    it('should show pre-flight step with Continue button', () => {
        cy.visit('/admin/system/upgrade');
        cy.get('#acceptWarnings').should('be.visible').and('contain', 'Continue');
    });

    describe('Upgrade Wizard Workflow', () => {
        it('should navigate from pre-flight to backup step', () => {
            cy.visit('/admin/system/upgrade');

            cy.get('#step-warnings').should('be.visible');
            cy.get('#acceptWarnings').click();
            cy.get('#step-backup').should('be.visible');
        });

        it('should mark completed steps with green checkmark', () => {
            cy.visit('/admin/system/upgrade');

            cy.get('#acceptWarnings').click();
            cy.get('#step-backup').should('be.visible');

            cy.get('.bs-stepper-header .step').first()
                .should('have.class', 'completed');
        });

        it('should show Create Backup and Skip Backup buttons', () => {
            cy.visit('/admin/system/upgrade');

            cy.get('#acceptWarnings').click();
            cy.get('#step-backup').should('be.visible');

            cy.get('#doBackup').should('be.visible').and('contain', 'Create Backup');
            cy.get('#skipBackup').should('be.visible');
        });

        it('should skip backup and auto-advance to download step', () => {
            cy.intercept('GET', '**/admin/api/upgrade/download-latest-release', {
                statusCode: 200,
                body: {
                    fileName: 'ChurchCRM-test-5.0.0.zip',
                    fullPath: '/tmp/ChurchCRM-test-5.0.0.zip',
                    releaseNotes: '## What\'s New\n\n- **Feature 1**: Dashboard\n',
                    sha1: 'abc123def456'
                }
            }).as('downloadRelease');

            cy.visit('/admin/system/upgrade');

            cy.get('#acceptWarnings').click();
            cy.get('#step-backup').should('be.visible');

            // Skip auto-advances to download step
            cy.get('#skipBackup').click();

            // Should reach download step and trigger the API call
            cy.wait('@downloadRelease', { timeout: 15000 });
            cy.get('#downloadStatus .alert-success').should('be.visible');
        });

        it('should navigate full workflow with intercepted download', () => {
            cy.intercept('GET', '**/admin/api/upgrade/download-latest-release', {
                statusCode: 200,
                body: {
                    fileName: 'ChurchCRM-test-5.0.0.zip',
                    fullPath: '/tmp/ChurchCRM-test-5.0.0.zip',
                    releaseNotes: '## What\'s New\n\n- **Feature 1**: Dashboard\n- **Feature 2**: Performance\n\n> Note: Backup first',
                    sha1: 'abc123def456'
                }
            }).as('downloadRelease');

            cy.visit('/admin/system/upgrade');

            // Step 1: Continue past pre-flight
            cy.get('#acceptWarnings').click();
            cy.get('#step-backup').should('be.visible');

            // Step 2: Skip backup (auto-advances)
            cy.get('#skipBackup').click();

            // Step 3: Wait for download
            cy.wait('@downloadRelease', { timeout: 15000 });

            cy.get('#downloadStatus .alert-success').should('be.visible');
            cy.get('#updateDetails').should('not.have.class', 'd-none');
            cy.get('#updateFileName').should('contain', 'ChurchCRM-test-5.0.0.zip');
            cy.get('#updateSHA1').should('contain', 'abc123def456');

            // Release notes rendered as markdown
            cy.get('#releaseNotes').within(() => {
                cy.get('h2').should('exist');
                cy.get('li').should('have.length.at.least', 2);
            });

            // Apply button visible but NOT clicked
            cy.get('#applyButtonContainer').should('not.have.class', 'd-none');
            cy.get('#applyUpdate').should('be.visible');
        });

        it('should handle download failure with retry', () => {
            cy.intercept('GET', '**/admin/api/upgrade/download-latest-release', {
                statusCode: 400,
                body: { message: 'Rate limit exceeded' }
            }).as('downloadFail');

            cy.visit('/admin/system/upgrade');

            cy.get('#acceptWarnings').click();
            cy.get('#step-backup').should('be.visible');
            cy.get('#skipBackup').click();

            cy.wait('@downloadFail', { timeout: 15000 });
            cy.get('#downloadStatus .alert-danger').should('be.visible');
            cy.get('#retryDownload').should('be.visible');
        });

        it('should create backup and show download button', () => {
            cy.intercept('POST', '**/admin/api/database/backup', {
                statusCode: 200,
                body: { BackupDownloadFileName: 'ChurchCRM-Backup.sql.gz' }
            }).as('createBackup');

            cy.visit('/admin/system/upgrade');

            cy.get('#acceptWarnings').click();
            cy.get('#doBackup').click();
            cy.wait('@createBackup');

            cy.get('#backupStatus .alert-success').should('be.visible');
            cy.get('#downloadbutton').should('be.visible')
                .and('contain', 'Download Backup');
        });

        it('should handle backup failure', () => {
            cy.intercept('POST', '**/admin/api/database/backup', {
                statusCode: 500,
                body: { message: 'Insufficient disk space' }
            }).as('backupFail');

            cy.visit('/admin/system/upgrade');

            cy.get('#acceptWarnings').click();
            cy.get('#doBackup').click();
            cy.wait('@backupFail');

            cy.get('#backupStatus .alert-danger').should('be.visible');
            cy.get('#doBackup').should('not.be.disabled');
        });
    });

    describe('Refresh from GitHub', () => {
        it('should call refresh API', () => {
            cy.intercept('POST', '**/admin/api/upgrade/refresh-upgrade-info', {
                statusCode: 200,
                body: { data: {}, message: 'Refreshed' }
            }).as('refreshInfo');

            cy.visit('/admin/system/upgrade');
            cy.get('#refreshFromGitHub').click();
            cy.wait('@refreshInfo');
        });

        it('should handle refresh failure', () => {
            cy.intercept('POST', '**/admin/api/upgrade/refresh-upgrade-info', {
                statusCode: 500,
                body: { message: 'GitHub API unavailable' }
            }).as('refreshFail');

            cy.visit('/admin/system/upgrade');
            cy.get('#refreshFromGitHub').click();
            cy.wait('@refreshFail');
            cy.get('#refreshFromGitHub').should('not.be.disabled');
        });
    });
});
