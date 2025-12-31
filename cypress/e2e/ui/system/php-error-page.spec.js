describe('PHP Error Page - Unauthenticated Access', () => {
  /**
   * Test the PHP version error page
   * This tests direct access to php-error.php without authentication
   * to ensure the page displays correctly with proper messaging
   */

  it('should load the PHP error page with clear messaging', () => {
    // Direct file access using cy.request to bypass routing middleware
    // The error page should be directly accessible even with PHP version check
    cy.request({
      method: 'GET',
      url: '/php-error.php',
      failOnStatusCode: false
    }).then((response) => {
      // Page should load successfully
      expect(response.status).to.equal(200);
      expect(response.body).to.include('PHP Version Not Supported');
    });
  });

  it('should display current PHP version and requirements', () => {
    // Simulate triggering the error page by checking index.php redirect
    cy.request({
      method: 'GET',
      url: '/php-error.php',
      failOnStatusCode: false
    }).then((response) => {
      expect(response.body).to.include('ChurchCRM requires PHP 8.2 or later');
      expect(response.body).to.include('Contact your hosting provider');
      expect(response.body).to.include('Current Version');
      expect(response.body).to.include('Required Version');
    });
  });
});
