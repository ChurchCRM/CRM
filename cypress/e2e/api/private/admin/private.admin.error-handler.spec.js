/// <reference types="cypress" />

/**
 * Error Handler coverage — all Slim entry points
 *
 * Verifies that every app (api, admin, v2, people, groups, finance,
 * session, external, kiosk, plugins) returns appropriate error responses
 * for unknown routes — correct status codes, no raw stack traces.
 */

// ─── /api — private JSON API ────────────────────────────────────────────────

describe('Error Handler — /api (JSON API)', () => {
  it('should return JSON 404 for unknown route', () => {
    cy.makePrivateAdminAPICall('GET', '/api/this-route-does-not-exist', null, 404)
      .then((response) => {
        expect(response.headers['content-type']).to.match(/application\/json/i);
        expect(response.body).to.have.property('error');
      });
  });

  it('should NOT expose a stack trace', () => {
    cy.makePrivateAdminAPICall('GET', '/api/this-route-does-not-exist', null, 404)
      .then((response) => {
        const body = JSON.stringify(response.body);
        expect(body).to.not.match(/Stack trace/i);
        expect(body).to.not.match(/\.php:\d+/);
      });
  });
});

// ─── /api/public — unauthenticated JSON API ──────────────────────────────────

describe('Error Handler — /api/public (public JSON API)', () => {
  it('should return JSON 404 for unknown public route', () => {
    cy.request({
      url: '/api/public/this-route-does-not-exist',
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.status).to.equal(404);
      expect(response.headers['content-type']).to.match(/application\/json/i);
      expect(response.body).to.have.property('error');
    });
  });

  it('should NOT expose a stack trace', () => {
    cy.request({
      url: '/api/public/this-route-does-not-exist',
      failOnStatusCode: false,
    }).then((response) => {
      const body = JSON.stringify(response.body);
      expect(body).to.not.match(/Stack trace/i);
      expect(body).to.not.match(/\.php:\d+/);
    });
  });
});

// ─── /external — unauthenticated JSON API ────────────────────────────────────

describe('Error Handler — /external (public register API)', () => {
  it('should return 404 for unknown external route', () => {
    cy.request({
      url: '/external/this-route-does-not-exist',
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.status).to.equal(404);
    });
  });

  it('should NOT expose a stack trace', () => {
    cy.request({
      url: '/external/this-route-does-not-exist',
      failOnStatusCode: false,
    }).then((response) => {
      const body = JSON.stringify(response.body);
      expect(body).to.not.match(/Stack trace/i);
      expect(body).to.not.match(/\.php:\d+/);
    });
  });
});

// ─── MVC apps (require admin session) ────────────────────────────────────────

const mvcApps = [
  { prefix: '/admin',   label: '/admin' },
  { prefix: '/v2',      label: '/v2' },
  { prefix: '/people',  label: '/people' },
  { prefix: '/groups',  label: '/groups' },
  { prefix: '/finance', label: '/finance' },
];

mvcApps.forEach(({ prefix, label }) => {
  describe(`Error Handler — ${label} (MVC, admin session)`, () => {
    beforeEach(() => {
      cy.setupAdminSession();
    });

    it(`should return 404 for unknown ${label} route`, () => {
      cy.request({
        url: `${prefix}/this-route-does-not-exist`,
        failOnStatusCode: false,
      }).then((response) => {
        expect(response.status).to.equal(404);
      });
    });

    it(`should NOT expose a stack trace on ${label}`, () => {
      cy.request({
        url: `${prefix}/this-route-does-not-exist`,
        failOnStatusCode: false,
      }).then((response) => {
        const body = JSON.stringify(response.body);
        expect(body).to.not.match(/Stack trace/i);
        expect(body).to.not.match(/\.php:\d+/);
      });
    });
  });
});

// ─── /kiosk — redirects 404s to /kiosk/admin ─────────────────────────────────

describe('Error Handler — /kiosk (redirects unknown routes)', () => {
  it('should redirect unknown kiosk route (not a 500)', () => {
    cy.request({
      url: '/kiosk/this-route-does-not-exist',
      failOnStatusCode: false,
      followRedirect: false,
    }).then((response) => {
      // Kiosk redirects 404s to /kiosk/admin — should be 3xx, not 500
      expect(response.status).to.be.lessThan(500);
    });
  });
});

// ─── /plugins — redirects 404s to root ───────────────────────────────────────

describe('Error Handler — /plugins (redirects unknown routes)', () => {
  it('should redirect unknown plugins route (not a 500)', () => {
    // Plugins intentionally redirect 404s to / — should be 3xx, never a 500
    cy.request({
      url: '/plugins/this-route-does-not-exist',
      failOnStatusCode: false,
      followRedirect: false,
    }).then((response) => {
      expect(response.status).to.be.lessThan(500);
      expect(response.status).to.be.at.least(300);
    });
  });

  it('should NOT expose a stack trace on /plugins redirect', () => {
    cy.request({
      url: '/plugins/this-route-does-not-exist',
      failOnStatusCode: false,
      followRedirect: false,
    }).then((response) => {
      const body = JSON.stringify(response.body);
      expect(body).to.not.match(/Stack trace/i);
      expect(body).to.not.match(/\.php:\d+/);
    });
  });
});