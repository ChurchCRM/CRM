describe('Admin System Logs', () => {
  it('Should have System Logs menu item in Admin menu', () => {
    cy.loginAdmin('v2/dashboard');
    
    // Open Admin menu in sidebar
    cy.get('.nav-sidebar').contains('Admin').click();
    
    // Verify System Logs menu item exists and is after Debug
    cy.get('.nav-sidebar')
      .contains('Debug')
      .parent()
      .parent()
      .next()
      .should('contain', 'System Logs');
  });

  it('Should display log level selector and system logs page', () => {
    cy.loginAdmin('v2/admin/logs');
    
    // Verify log level selector exists
    cy.get('#logLevel').should('exist');
    cy.get('button[type="submit"]').should('contain', 'Update Log Level');
    
    // Verify page title
    cy.get('.card-header h4').should('contain', 'Log Level Configuration');
    cy.get('.card-header h4').should('contain', 'System Logs');
  });

  it('Should display log files table with correct column order', () => {
    cy.loginAdmin('v2/admin/logs');
    
    cy.get('.card-body').then(($body) => {
      if ($body.find('#logFilesTable').length > 0) {
        // If logs exist, table should be visible with Actions column first
        cy.get('#logFilesTable').should('be.visible');
        cy.get('#logFilesTable thead th').should('have.length', 4);
        cy.get('#logFilesTable thead th').eq(0).should('contain', 'Actions');
        cy.get('#logFilesTable thead th').eq(1).should('contain', 'Log File');
        cy.get('#logFilesTable thead th').eq(2).should('contain', 'Size');
        cy.get('#logFilesTable thead th').eq(3).should('contain', 'Last Modified');
        
        // Verify action buttons exist in first column
        cy.get('.view-log').should('exist');
        cy.get('.delete-log').should('exist');
      } else {
        // If no logs, info message should be shown
        cy.get('.alert-info').should('contain', 'No log files found');
      }
    });
  });

  it('Should open log viewer modal when clicking view button', () => {
    cy.loginAdmin('v2/admin/logs');
    
    // Check if any log files exist
    cy.get('.card-body').then(($body) => {
      if ($body.find('.view-log').length > 0) {
        // Click first view button
        cy.get('.view-log').first().click();
        
        // Verify modal opens
        cy.get('#logViewerModal').should('be.visible');
        cy.get('#logViewerModalLabel').should('contain', 'Log File Viewer');
        
        // Verify filter controls exist
        cy.get('.log-filter[data-level="all"]').should('exist');
        cy.get('.log-filter[data-level="ERROR"]').should('exist');
        cy.get('.log-filter[data-level="WARNING"]').should('exist');
        cy.get('.log-filter[data-level="INFO"]').should('exist');
        cy.get('.log-filter[data-level="DEBUG"]').should('exist');
        
        // Verify line limit dropdown exists
        cy.get('#logLinesLimit').should('exist');
        
        // Wait for content to load
        cy.get('#logLoading', { timeout: 5000 }).should('not.be.visible');
        cy.get('#logContent').should('be.visible');
        
        // Close modal
        cy.get('#logViewerModal .close').click();
        cy.get('#logViewerModal').should('not.be.visible');
      }
    });
  });

  it('Should filter log content by level', () => {
    cy.loginAdmin('v2/admin/logs');
    
    cy.get('.card-body').then(($body) => {
      if ($body.find('.view-log').length > 0) {
        cy.get('.view-log').first().click();
        
        // Wait for content to load
        cy.get('#logLoading', { timeout: 5000 }).should('not.be.visible');
        
        // Click ERROR filter
        cy.get('.log-filter[data-level="ERROR"]').click();
        cy.get('.log-filter[data-level="ERROR"]').should('have.class', 'active');
        
        // Click back to All
        cy.get('.log-filter[data-level="all"]').click();
        cy.get('.log-filter[data-level="all"]').should('have.class', 'active');
        
        // Close modal
        cy.get('#logViewerModal .close').click();
      }
    });
  });

  it('Should change number of lines displayed', () => {
    cy.loginAdmin('v2/admin/logs');
    
    cy.get('.card-body').then(($body) => {
      if ($body.find('.view-log').length > 0) {
        cy.get('.view-log').first().click();
        
        // Wait for content to load
        cy.get('#logLoading', { timeout: 5000 }).should('not.be.visible');
        
        // Change line limit
        cy.get('#logLinesLimit').select('50');
        cy.get('#logLinesLimit').should('have.value', '50');
        
        cy.get('#logLinesLimit').select('500');
        cy.get('#logLinesLimit').should('have.value', '500');
        
        // Close modal
        cy.get('#logViewerModal .close').click();
      }
    });
  });

  it('Should update log level configuration', () => {
    cy.loginAdmin('v2/admin/logs');
    
    // Change log level to ERROR
    cy.get('#logLevel').select('400'); // ERROR level
    cy.get('button[type="submit"]').click();
    
    // Verify success message or page reload
    cy.get('#logLevelStatus').should('contain', 'Log level updated successfully');
    
    // Verify the dropdown shows the updated value
    cy.get('#logLevel').should('have.value', '400');
  });

  it('Should reject invalid log file names', () => {
    // Try to access a file with invalid characters
    cy.request({
      url: '/v2/admin/logs/../../../etc/passwd',
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.status).to.eq(400);
      expect(response.body).to.contain('Invalid filename');
    });

    // Try to access a file without .log extension
    cy.request({
      url: '/v2/admin/logs/config.php',
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.status).to.eq(400);
      expect(response.body).to.contain('Invalid filename');
    });
  });

  it('Should return 404 for non-existent log files', () => {
    cy.request({
      url: '/v2/admin/logs/nonexistent-file.log',
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.status).to.eq(404);
      expect(response.body).to.contain('Log file not found');
    });
  });

  it('Should have delete all button when logs exist', () => {
    cy.loginAdmin('v2/admin/logs');
    
    cy.get('.card-body').then(($body) => {
      if ($body.find('#logFilesTable').length > 0) {
        cy.get('#deleteAllLogs').should('exist');
        cy.get('#deleteAllLogs').should('contain', 'Delete All Logs');
        cy.get('#deleteAllLogs').should('have.class', 'btn-danger');
      }
    });
  });

  it('Should delete a single log file', () => {
    cy.loginAdmin('v2/admin/logs');
    
    cy.get('.card-body').then(($body) => {
      if ($body.find('.delete-log').length > 0) {
        // Stub the confirm dialog
        cy.window().then((win) => {
          cy.stub(win, 'confirm').returns(true);
        });
        
        // Click delete on first log
        cy.get('.delete-log').first().click();
        
        // Verify the page reloads (or check for success)
        cy.url().should('include', '/v2/admin/logs');
      }
    });
  });

  it('Should delete all logs when confirmed', () => {
    cy.loginAdmin('v2/admin/logs');
    
    cy.get('.card-body').then(($body) => {
      if ($body.find('#deleteAllLogs').length > 0) {
        // Stub the confirm dialog
        cy.window().then((win) => {
          cy.stub(win, 'confirm').returns(true);
        });
        
        // Click delete all
        cy.get('#deleteAllLogs').click();
        
        // Verify the page reloads
        cy.url().should('include', '/v2/admin/logs');
      }
    });
  });
});
