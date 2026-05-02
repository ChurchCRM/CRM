/// <reference types="cypress" />

describe('API Public PHP Error Page', () => {
  /**
   * Test the PHP version error page
   * This tests direct access to php-error.php without authentication
   * to ensure the page displays correctly with proper messaging
   */

  it('should load the PHP error page with clear messaging', () => {
    // Direct file access using cy.request to bypass routing middleware
    // The error page should be directly accessible and return 503 Service Unavailable (hard error)
    cy.request({
      method: 'GET',
      url: '/errors/php-error.php',
      failOnStatusCode: false
    }).then((response) => {
      // Page should return 503 Service Unavailable (hard blocking error)
      expect(response.status).to.equal(503);
      expect(response.body).to.include('PHP Version Not Supported');
      expect(response.body).to.include('APPLICATION BLOCKED');
    });
  });

  it('should display current PHP version and requirements', () => {
    // Simulate triggering the error page by checking index.php redirect
    cy.request({
      method: 'GET',
      url: '/errors/php-error.php',
      failOnStatusCode: false
    }).then((response) => {
      // Check for dynamic version requirement text (reads from composer.json)
      expect(response.body).to.match(/PHP \d+\.\d+ or later/);
      expect(response.body).to.include('Contact your hosting provider immediately');
      expect(response.body).to.include('Current PHP Version');
      expect(response.body).to.include('Minimum Required');
      expect(response.body).to.include('APPLICATION BLOCKED');
      expect(response.body).to.include('hard blocking error');
    });
  });
});
