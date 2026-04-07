/// <reference types="cypress" />

/**
 * MVC Error Pages (404, 403, 500) — Tabler-styled layout validation
 *
 * Verifies that visiting unknown or unauthorized routes in the admin and v2
 * route groups renders proper Tabler-styled error pages with nav shell and
 * no raw stack traces exposed to the browser.
 */

describe('MVC Error Pages', () => {
  beforeEach(() => {
    cy.setupAdminSession();
  });

  // ─── Admin route group (/admin/) ─────────────────────────────────────────

  describe('Admin 404 — unknown route', () => {
    it('should render a Tabler-styled 404 page with status 404', () => {
      cy.visit('/admin/this-route-does-not-exist', { failOnStatusCode: false });
      // HTTP status should be 404
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
      // The Tabler nav/header should exist
      cy.get('.navbar, .navbar-vertical, .page').should('exist');
    });
  });

  // ─── v2 route group (/v2/) ───────────────────────────────────────────────

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

  // ─── API route group (/api/) ─────────────────────────────────────────────

  describe('API 404 — unknown route', () => {
    it('should return JSON with status 404', () => {
      // Use API key auth (same as all other API tests) to bypass session issues.
      cy.request({
        url: '/api/this-route-does-not-exist',
        failOnStatusCode: false,
        headers: {
          'Accept': 'application/json',
          'x-api-key': Cypress.env('admin.api.key'),
        },
      }).then((response) => {
          expect(response.status).to.equal(404);
          expect(response.headers['content-type']).to.match(/application\/json/i);
          expect(response.body).to.have.property('error');
        });
    });

    it('should NOT include a stack trace in the JSON response', () => {
      cy.request({
        url: '/api/this-route-does-not-exist',
        failOnStatusCode: false,
        headers: {
          'Accept': 'application/json',
          'x-api-key': Cypress.env('admin.api.key'),
        },
      }).then((response) => {
          const body = JSON.stringify(response.body);
          expect(body).to.not.match(/Stack trace/i);
          expect(body).to.not.match(/\.php:\d+/);
        });
    });
  });

  // ─── 403 Access Denied ───────────────────────────────────────────────────

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
    // Standard user has no admin role, so AdminRoleAuthMiddleware redirects to /v2/access-denied
    // before the route is matched. Either way, no raw trace should be exposed.
    cy.visit('/admin/this-route-does-not-exist', { failOnStatusCode: false });
    cy.get('body').invoke('text').then((text) => {
      expect(text).to.not.match(/Slim\\Exception/i);
      expect(text).to.not.match(/Stack trace/i);
      expect(text).to.not.match(/\.php:\d+/);
    });
  });
});
