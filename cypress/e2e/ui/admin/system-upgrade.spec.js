describe('System Upgrade Page', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should load and display compact version info', () => {
        cy.visit('/admin/system/upgrade');

        // Version badges visible on one line
        cy.contains('Installed').should('be.visible');
        cy.get('.badge.bg-primary-lt').should('be.visible').and('not.be.empty');

        // Refresh button
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

        // Step 1 should be active initially
        cy.get('#step-warnings').should('be.visible');
    });

    describe('Upgrade Wizard Workflow', () => {
        it('should navigate from pre-flight to backup step', () => {
            cy.visit('/admin/system/upgrade');

            cy.get('#step-warnings').should('be.visible');
            cy.get('#acceptWarnings').should('be.visible').click();
            cy.get('#step-backup').should('be.visible');
        });

        it('should mark completed steps with green checkmark', () => {
            cy.visit('/admin/system/upgrade');

            cy.get('#acceptWarnings').click();
            cy.get('#step-backup').should('be.visible');

            // First step should be marked completed
            cy.get('.bs-stepper-header .step').first()
                .should('have.class', 'completed');
        });

        it('should show Create Backup and Skip Backup buttons', () => {
            cy.visit('/admin/system/upgrade');

            cy.get('#acceptWarnings').click();
            cy.get('#step-backup').should('be.visible');

            cy.get('#doBackup').should('be.visible').and('contain', 'Create Backup');
            cy.get('#skipBackup').should('be.visible').and('contain', 'Skip Backup');
            cy.get('#backupNavButtons').should('have.class', 'd-none');
        });

        it('should allow skipping backup and show warning', () => {
            cy.visit('/admin/system/upgrade');

            cy.get('#acceptWarnings').click();
            cy.get('#skipBackup').should('be.visible').click();

            cy.get('#backupStatus .alert-warning').should('be.visible');
            cy.contains('Backup Skipped').should('be.visible');

            cy.get('#skipBackup').should('have.class', 'd-none');
            cy.get('#backupNavButtons').should('not.have.class', 'd-none');
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

            // Step 1: Continue
            cy.get('#acceptWarnings').click();

            // Step 2: Skip backup
            cy.get('#skipBackup').click();
            cy.get('#backup-next').click();

            // Step 3: Download step
            cy.get('#step-apply').should('be.visible');
            cy.wait('@downloadRelease');

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

            // Both previous steps should be completed
            cy.get('.bs-stepper-header .step.completed').should('have.length', 2);
        });

        it('should handle download failure with retry', () => {
            cy.intercept('GET', '**/admin/api/upgrade/download-latest-release', {
                statusCode: 400,
                body: { message: 'Rate limit exceeded' }
            }).as('downloadFail');

            cy.visit('/admin/system/upgrade');

            cy.get('#acceptWarnings').click();
            cy.get('#skipBackup').click();
            cy.get('#backup-next').click();

            cy.wait('@downloadFail');
            cy.get('#downloadStatus .alert-danger').should('be.visible');
            cy.get('#retryDownload').should('be.visible');
        });

        it('should create backup successfully', () => {
            cy.intercept('POST', '**/api/database/backup', {
                statusCode: 200,
                body: { BackupDownloadFileName: 'ChurchCRM-Backup.sql.gz' }
            }).as('createBackup');

            cy.visit('/admin/system/upgrade');

            cy.get('#acceptWarnings').click();
            cy.get('#doBackup').click();
            cy.wait('@createBackup');

            cy.get('#backupStatus .alert-success').should('be.visible');
            cy.get('#downloadbutton').should('be.visible')
                .and('contain', 'ChurchCRM-Backup.sql.gz');
            cy.get('#backup-next').should('be.visible');
        });

        it('should handle backup failure', () => {
            cy.intercept('POST', '**/api/database/backup', {
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
