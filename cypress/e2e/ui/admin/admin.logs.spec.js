describe('Admin System Logs - UI Tests', () => {
  beforeEach(() => {
    cy.setupAdminSession();
  });

  it('Should display page header', () => {
    cy.visit('admin/system/logs');
    
    // Verify page header exists (from layout framework)
    cy.contains('System Logs').should('be.visible');
  });

  it('Should display quick settings button', () => {
    cy.visit('admin/system/logs');

    // Quick Settings were moved to the header settings panel — verify settings assets load
    cy.get('link[href*="system-settings-panel.min.css"]').should('exist');
    cy.get('script[src*="system-settings-panel.min.js"]').should('exist');
  });

  it('Should display stat cards with Log Level always present', () => {
    cy.visit('admin/system/logs');
    
    // Log Level card should always be shown
    cy.get('.card-sm').should('exist');
    cy.contains('Log Level').should('be.visible');
    
    // Check for file/size/delete cards when logs exist
    cy.get('.card-body').then(($body) => {
      if ($body.find('#logFilesTable').length > 0) {
        cy.contains('Log Files').should('be.visible');
        cy.contains('Total Size').should('be.visible');
        cy.get('#deleteAllLogs').should('exist');
      }
    });
  });

  it('Should display system logs section with table or no-logs message', () => {
    cy.visit('admin/system/logs');
    
    cy.get('.card-body, .alert').then(($body) => {
      if ($body.find('.alert-info').length > 0) {
        // No logs case - alert shown directly without card header
        cy.get('.alert-info').should('contain', 'No log files found');
      } else if ($body.find('#logFilesTable').length > 0) {
        // Logs exist case - card with header and table
        cy.get('.card').contains('Log Files').should('exist');
        cy.get('#logFilesTable').should('be.visible');
        cy.get('#logFilesTable thead th').should('have.length', 4);
        cy.get('#logFilesTable thead th').eq(0).should('contain', 'Log File');
        cy.get('#logFilesTable thead th').eq(1).should('contain', 'Size');
        cy.get('#logFilesTable thead th').eq(2).should('contain', 'Last Modified');
        cy.get('#logFilesTable thead th').eq(3).should('contain', 'Actions');

        // Verify action buttons exist in the first table row
        cy.get('#logFilesTable tbody tr').first().within(() => {
          cy.get('.view-log').should('exist');
          cy.get('.download-log').should('exist');
          cy.get('.delete-log').should('exist');
        });

        // Verify delete all button exists when logs are present
        cy.get('#deleteAllLogs').should('exist');
      }
    });
  });

  it('Should open log viewer modal when viewing a log file', () => {
    cy.visit('admin/system/logs');

    // Skip when no log files exist (table is conditionally rendered in PHP)
    cy.get('body').then(($body) => {
      if ($body.find('#logFilesTable tbody tr').length === 0) {
        cy.log('No log files found — skipping log viewer modal test');
        return;
      }

      // Click the dropdown toggle in the first row to open the menu
      cy.get('#logFilesTable tbody tr').first().find('button[data-bs-toggle="dropdown"], .dropdown-toggle').first().click();

      // Click the View action from the now-visible dropdown
      cy.get('#logFilesTable tbody tr').first().find('.view-log').first().should('be.visible').click();

      // Verify modal is displayed and has the proper title
      cy.get('#logViewerModal').should('be.visible');
      cy.get('#logViewerModalLabel').should('contain', 'Log File Viewer');
    });
  });

  it('Should verify download endpoint for the first log file', () => {
    cy.visit('admin/system/logs');

    // Skip download test when no log files exist
    cy.get('body').then(($body) => {
      if ($body.find('#logFilesTable tbody tr').length === 0) {
        cy.log('No log files found — skipping download endpoint check');
        return;
      }

      // Ensure download action exists and extract the log file name
      cy.get('#logFilesTable tbody tr').first().within(() => {
        cy.get('.download-log').should('exist').invoke('attr', 'data-log-name').as('logName');
      });

      // Build the expected download URL and request it directly to verify the endpoint
      cy.get('@logName').then((logName) => {
        cy.window().then((win) => {
          const origin = win.location && win.location.origin ? win.location.origin : Cypress.config('baseUrl');
          const rootPath = (win.CRM && win.CRM.root) ? win.CRM.root : '/';
          const relative = rootPath + (rootPath.endsWith('/') ? '' : '/') + 'admin/api/system/logs/' + encodeURIComponent(logName) + '/download';
          const url = new URL(relative, origin).toString();
          cy.log('download url: ' + url);
          cy.request({ url, encoding: 'utf8', failOnStatusCode: false }).then((resp) => {
            cy.log('download status: ' + resp.status);
            cy.log('download headers: ' + JSON.stringify(resp.headers));

            expect(resp.status, `download status for ${logName} (${url})`).to.equal(200);

            const cd = resp.headers && (resp.headers['content-disposition'] || resp.headers['Content-Disposition']);
            cy.log('cd=' + cd);
            expect(cd, 'Content-Disposition header present').to.be.a('string');
            expect(cd.toLowerCase(), 'Content-Disposition mentions attachment').to.include('attachment');
            expect(cd, 'Content-Disposition mentions filename').to.match(/filename\s*=\s*"?.+"?/);

            expect(resp.body && resp.body.length, 'download body non-empty').to.be.greaterThan(10);
          });
        });
      });
    });
  });

});
