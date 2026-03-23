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
    
    // Verify settings button is visible
    cy.get('button').contains('Quick Settings').should('be.visible');
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
        
        // Verify action buttons exist in table rows
        cy.get('.view-log').should('exist');
        cy.get('.delete-log').should('exist');
        
        // Verify delete all button exists when logs are present
        cy.get('#deleteAllLogs').should('exist');
      }
    });
  });

  it('Should open log viewer modal when viewing a log file', () => {
    cy.visit('admin/system/logs');
    
    // Click the dropdown toggle button first to open the menu
    cy.get('.btn-ghost-secondary').first().click();
    
    // Then click the view button for the first log file
    cy.get('.view-log').first().click();
    
    // Verify modal is displayed
    cy.get('.modal').should('be.visible');
    cy.get('.modal-title').should('contain', 'Log File Viewer');
  });

});
