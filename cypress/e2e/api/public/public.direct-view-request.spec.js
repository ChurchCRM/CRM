/// <reference types="cypress" />

describe('Direct requests to MVC view files', () => {
  [
    '/admin/views/dashboard.php',
    '/finance/views/dashboard.php',
    '/v2/templates/root/dashboard.php',
    '/admin/views/dashboard.php/extra',
    '/finance/views/dashboard.php/extra',
    '/v2/templates/root/dashboard.php/extra',
    '/admin/views/partials/member-portal-members.php',
    '/people/views/partials/portal-signin-card.php',
    '/people/views/sign-in-email.php',
    '/people/views/partials/email-attention-toolbar.php',
    '/portal/routes/login.php',
  ].forEach((path) => {
    it(`returns 403 for ${path}`, () => {
      cy.request({ url: path, followRedirect: false, failOnStatusCode: false })
        .its('status')
        .should('eq', 403);
    });
  });
});

describe('Direct requests to core plugin PHP files', () => {
  [
    '/plugins/core/mailchimp/src/MailChimpPlugin.php',
    '/plugins/core/gravatar/src/GravatarPlugin.php',
    '/plugins/core/mailchimp/src/MailChimpPlugin.php/extra',
  ].forEach((path) => {
    it(`returns 403 for ${path}`, () => {
      cy.request({ url: path, followRedirect: false, failOnStatusCode: false })
        .its('status')
        .should('eq', 403);
    });
  });
});
