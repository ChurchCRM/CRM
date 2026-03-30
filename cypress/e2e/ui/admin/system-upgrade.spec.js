describe('System Upgrade Page', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should load and display version information', () => {
        cy.visit('/admin/system/upgrade');

        // Version information card
        cy.contains('Version Information').should('be.visible');
        cy.contains('Current Version').should('be.visible');
        cy.get('.badge.bg-primary-lt').should('be.visible').and('not.be.empty');
        cy.contains('Latest GitHub Version').should('be.visible');

        // Refresh button
        cy.get('#refreshFromGitHub').should('be.visible').and('contain', 'Refresh from GitHub');
    });

    it('should display the file integrity check card', () => {
        cy.visit('/admin/system/upgrade');

        cy.contains('File Integrity Check').should('be.visible');
        cy.get('.card').contains('File Integrity Check')
            .closest('.card')
            .find('.card-body')
            .should('be.visible');
    });

    it('should display the upgrade wizard with all steps', () => {
        cy.visit('/admin/system/upgrade');

        cy.get('#upgrade-wizard-card').should('be.visible');

        cy.get('.bs-stepper-header').within(() => {
            cy.contains('Warnings').should('exist');
            cy.contains('Database Backup').should('exist');
            cy.contains('Download & Apply').should('exist');
            cy.contains('Complete').should('exist');
        });

        // Step 1 (Warnings) should be active initially
        cy.get('#step-warnings').should('be.visible');
    });

    describe('Upgrade Wizard Workflow', () => {
        it('should navigate from warnings to backup step', () => {
            cy.visit('/admin/system/upgrade');

            cy.get('#step-warnings').should('be.visible');
            cy.get('#acceptWarnings').should('be.visible').click();
            cy.get('#step-backup').should('be.visible');
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
            cy.get('#step-backup').should('be.visible');

            cy.get('#skipBackup').should('be.visible').click();

            // Warning alert should appear
            cy.get('#backupStatus').within(() => {
                cy.get('.alert-warning').should('be.visible');
                cy.contains('Backup Skipped').should('be.visible');
            });

            // Skip button hidden, continue button visible
            cy.get('#skipBackup').should('have.class', 'd-none');
            cy.get('#backupNavButtons').should('not.have.class', 'd-none');
            cy.get('#backup-next').should('be.visible');
        });

        it('should navigate full workflow: warnings → skip backup → download step (intercepted)', () => {
            cy.intercept('GET', '**/admin/api/upgrade/download-latest-release', {
                statusCode: 200,
                body: {
                    fileName: 'ChurchCRM-test-5.0.0.zip',
                    fullPath: '/tmp/ChurchCRM-test-5.0.0.zip',
                    releaseNotes: '## What\'s New\n\n- **Feature 1**: Added new dashboard\n- **Feature 2**: Improved performance\n\n### Bug Fixes\n\n- Fixed login issue\n- Fixed backup restore\n\n> Note: Please backup before upgrading',
                    sha1: 'abc123def456'
                }
            }).as('downloadRelease');

            cy.visit('/admin/system/upgrade');

            // Step 1: Accept warnings
            cy.get('#acceptWarnings').click();

            // Step 2: Skip backup
            cy.get('#skipBackup').click();
            cy.get('#backup-next').click();

            // Step 3: Download step
            cy.get('#step-apply').should('be.visible');
            cy.wait('@downloadRelease');

            // Download success
            cy.get('#downloadStatus .alert-success').should('be.visible');

            // Update details visible with correct info
            cy.get('#updateDetails').should('not.have.class', 'd-none');
            cy.get('#updateFileName').should('contain', 'ChurchCRM-test-5.0.0.zip');
            cy.get('#updateSHA1').should('contain', 'abc123def456');

            // Release notes rendered as markdown
            cy.get('#releaseNotes').within(() => {
                cy.get('h2').should('contain', "What's New");
                cy.get('li').should('have.length.at.least', 2);
                cy.get('strong').should('exist');
                cy.get('blockquote').should('exist');
            });

            // Apply button visible but NOT clicked
            cy.get('#applyButtonContainer').should('not.have.class', 'd-none');
            cy.get('#applyUpdate').should('be.visible').and('contain', 'Apply Update Now');
        });

        it('should handle download failure with retry button', () => {
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
            cy.get('#retryDownload').should('be.visible').and('contain', 'Retry');
        });

        it('should create backup and show download button', () => {
            cy.intercept('POST', '**/api/database/backup', {
                statusCode: 200,
                body: { BackupDownloadFileName: 'ChurchCRM-Backup-2026-03-30.sql.gz' }
            }).as('createBackup');

            cy.visit('/admin/system/upgrade');

            cy.get('#acceptWarnings').click();
            cy.get('#doBackup').click();
            cy.wait('@createBackup');

            cy.get('#backupStatus .alert-success').should('be.visible');
            cy.contains('Backup Complete').should('be.visible');

            cy.get('#downloadbutton').should('be.visible')
                .and('contain', 'ChurchCRM-Backup-2026-03-30.sql.gz');

            cy.get('#backup-next').should('be.visible');
        });

        it('should handle backup failure gracefully', () => {
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
        it('should call refresh API when button clicked', () => {
            cy.intercept('POST', '**/admin/api/upgrade/refresh-upgrade-info', {
                statusCode: 200,
                body: { data: {}, message: 'Refreshed' }
            }).as('refreshInfo');

            cy.visit('/admin/system/upgrade');

            cy.get('#refreshFromGitHub').click();
            cy.wait('@refreshInfo');
        });

        it('should show error on refresh failure', () => {
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
