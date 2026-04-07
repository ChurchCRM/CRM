/// <reference types="cypress" />

/**
 * MVC Error Pages (404, 403, 500) — Tabler-styled layout validation
 *
 * Verifies that visiting unknown or unauthorized routes in the admin and v2
 * route groups renders proper Tabler-styled error pages with nav shell and
 * no raw stack traces exposed to the browser.
 */

describe('MVC Error Pages — HTML (admin)', () => {
  beforeEach(() => {
    cy.setupAdminSession();
  });

  describe('Admin 404 — unknown route', () => {
    it('should render a Tabler-styled 404 page with status 404', () => {
      cy.visit('/admin/this-route-does-not-exist', { failOnStatusCode: false });
      cy.request({ url: '/admin/this-route-does-not-exist', failOnStatusCode: false })
        .its('status')
        .should('equal', 404);
    });

    it('should display the 404 code and Page Not Found title', () => {
      cy.visit('/admin/this-route-does-not-exist', { failOnStatusCode: false });
      cy.contains('404').should('be.visible');
      cy.contains('Page Not Found').should('be.visible');
    });

    it('should display a Back to Admin Dashboard button', () => {
      cy.visit('/admin/this-route-does-not-exist', { failOnStatusCode: false });
      cy.get('a.btn').contains('Back to Admin Dashboard').should('be.visible');
    });

    it('should NOT expose stack trace in the page body', () => {
      cy.visit('/admin/this-route-does-not-exist', { failOnStatusCode: false });
      cy.get('body').invoke('text').then((text) => {
        expect(text).to.not.match(/Slim\\Exception/i);
        expect(text).to.not.match(/Stack trace/i);
        expect(text).to.not.match(/\.php:\d+/);
      });
    });

    it('should include the standard nav shell (header)', () => {
      cy.visit('/admin/this-route-does-not-exist', { failOnStatusCode: false });
      cy.get('.navbar, .navbar-vertical, .page').should('exist');
    });
  });
});

describe('MVC Error Pages — HTML (v2)', () => {
  beforeEach(() => {
    cy.setupAdminSession();
  });

  describe('v2 404 — unknown route', () => {
    it('should render a Tabler-styled 404 page with status 404', () => {
      cy.request({ url: '/v2/this-route-does-not-exist', failOnStatusCode: false })
        .its('status')
        .should('equal', 404);
    });

    it('should display the 404 code and Page Not Found title', () => {
      cy.visit('/v2/this-route-does-not-exist', { failOnStatusCode: false });
      cy.contains('404').should('be.visible');
      cy.contains('Page Not Found').should('be.visible');
    });

    it('should display a Return to Dashboard button', () => {
      cy.visit('/v2/this-route-does-not-exist', { failOnStatusCode: false });
      cy.get('a.btn').contains('Return to Dashboard').should('be.visible');
    });

    it('should NOT expose stack trace in the page body', () => {
      cy.visit('/v2/this-route-does-not-exist', { failOnStatusCode: false });
      cy.get('body').invoke('text').then((text) => {
        expect(text).to.not.match(/Slim\\Exception/i);
        expect(text).to.not.match(/Stack trace/i);
        expect(text).to.not.match(/\.php:\d+/);
      });
    });

    it('should include the standard nav shell (header)', () => {
      cy.visit('/v2/this-route-does-not-exist', { failOnStatusCode: false });
      cy.get('.navbar, .navbar-vertical, .page').should('exist');
    });
  });

  describe('v2 403 — access denied page', () => {
    it('should display the Permission Required page', () => {
      cy.visit('/v2/access-denied', { failOnStatusCode: false });
      cy.contains('Permission Required').should('be.visible');
    });

    it('should display a Go to Dashboard button', () => {
      cy.visit('/v2/access-denied', { failOnStatusCode: false });
      cy.get('a.btn').contains('Go to Dashboard').should('be.visible');
    });

    it('should include the nav shell', () => {
      cy.visit('/v2/access-denied', { failOnStatusCode: false });
      cy.get('.navbar, .navbar-vertical, .page').should('exist');
    });
  });
});



describe('MVC Error Pages — Standard User Access', () => {
  beforeEach(() => {
    cy.setupStandardSession();
  });

  it('Standard user visiting admin 404 route should see error or redirect — not a raw stack trace', () => {
    cy.visit('/admin/this-route-does-not-exist', { failOnStatusCode: false });
    cy.get('body').invoke('text').then((text) => {
      expect(text).to.not.match(/Slim\\Exception/i);
      expect(text).to.not.match(/Stack trace/i);
      expect(text).to.not.match(/\.php:\d+/);
    });
  });
});


