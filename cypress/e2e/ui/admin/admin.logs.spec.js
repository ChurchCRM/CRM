describe('Admin System Logs - UI Tests', () => {
  beforeEach(() => {
    cy.setupAdminSession();
  });

  it('Should display log level configuration section', () => {
    cy.visit('admin/system/logs');
    
    // Verify log level configuration card exists
    cy.get('.card-header h4').should('contain', 'Log Settings');
    
    // Verify log level selector exists
    cy.get('#logLevel').should('exist');
    cy.get('#saveLogLevel').should('contain', 'Save Log Level');
    
    // Verify helper text exists
    cy.get('.text-muted').should('contain', 'Lower numbers log more details');
  });

  it('Should display system logs section', () => {
    cy.visit('admin/system/logs');
    
    // Verify system logs card exists
    cy.get('.card-header h4').should('contain', 'System Logs');
    cy.get('.text-muted').should('contain', 'View application logs');
  });

  it('Should update log level when changed', () => {
    cy.visit('admin/system/logs');
    
    // Change log level to ERROR (400)
    cy.get('#logLevel').select('400');
    cy.get('#saveLogLevel').click();
    
    // Wait for success message
    cy.get('#logLevelStatus', { timeout: 5000 }).should('be.visible');
    cy.get('#logLevelStatus').should('contain', 'Log level updated');
    
    // Verify the dropdown still shows the selected value
    cy.get('#logLevel').should('have.value', '400');
    
    // Change back to INFO (200) for cleanup
    cy.get('#logLevel').select('200');
    cy.get('#saveLogLevel').click();
    cy.get('#logLevelStatus', { timeout: 5000 }).should('contain', 'Log level updated');
  });

  it('Should show appropriate content when no logs exist', () => {
    cy.visit('admin/system/logs');
    
    cy.get('.card-body').then(($body) => {
      if ($body.find('.alert-info').length > 0) {
        // If no logs, info message should be shown
        cy.get('.alert-info').should('contain', 'No log files found');
        
        // Delete all button should not exist when no logs
        cy.get('#deleteAllLogs').should('not.exist');
      } else if ($body.find('#logFilesTable').length > 0) {
        // If logs exist, table should be visible with correct structure
        cy.get('#logFilesTable').should('be.visible');
        cy.get('#logFilesTable thead th').should('have.length', 4);
        cy.get('#logFilesTable thead th').eq(0).should('contain', 'Actions');
        cy.get('#logFilesTable thead th').eq(1).should('contain', 'Log File');
        cy.get('#logFilesTable thead th').eq(2).should('contain', 'Size');
        cy.get('#logFilesTable thead th').eq(3).should('contain', 'Last Modified');
        
        // Verify action buttons exist
        cy.get('.view-log').should('exist');
        cy.get('.delete-log').should('exist');
        
        // Verify delete all button exists when logs are present
        cy.get('#deleteAllLogs').should('exist');
        cy.get('#deleteAllLogs').should('contain', 'Delete All Logs');
      }
    });
  });
});
