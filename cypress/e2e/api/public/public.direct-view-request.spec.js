/// <reference types="cypress" />

describe('Direct requests to MVC view files', () => {
  [
    '/admin/views/dashboard.php',
    '/finance/views/dashboard.php',
    '/v2/templates/root/dashboard.php',
  ].forEach((path) => {
    it(`returns 403 for ${path}`, () => {
      cy.request({ url: path, followRedirect: false, failOnStatusCode: false })
        .its('status')
        .should('eq', 403);
    });
  });
});
