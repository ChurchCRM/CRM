describe('OpenLP Plugin - URL Validation (GHSA-hrfr-xg9w-hjm4)', () => {
  const PLUGIN_SETTINGS_URL = '/admin/plugins/openlp/settings';

  beforeEach(() => {
    cy.login('admin@example.com', 'password');
  });

  describe('URL Format & Hostname Resolution Validation', () => {
    it('should accept local network IP addresses', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      cy.get('[name="serverUrl"]').clear().type('http://192.168.1.100:4316');

      // Should not reject — connection failure (no server) is OK, validation passes
      cy.contains('button', 'Test Connection').click();

      // Either "Cannot connect" (no server) or "Connected" — both mean validation passed
      cy.contains(
        /Cannot connect to OpenLP|Connected to OpenLP/,
        { timeout: 3000 }
      ).should('be.visible');
    });

    it('should reject invalid URL schemes (file://)', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      cy.get('[name="serverUrl"]').clear().type('file:///etc/passwd');

      cy.contains('button', 'Test Connection').click();

      cy.contains(
        'Only http and https schemes are allowed',
        { timeout: 3000 }
      ).should('be.visible');
    });

    it('should reject unresolvable hostnames', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      cy.get('[name="serverUrl"]').clear().type('http://this-domain-definitely-does-not-exist-12345.invalid:4316');

      cy.contains('button', 'Test Connection').click();

      cy.contains(
        'Unable to resolve OpenLP server hostname',
        { timeout: 3000 }
      ).should('be.visible');
    });

    it('should accept resolvable hostnames', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      // localhost resolves to 127.0.0.1, should pass validation
      cy.get('[name="serverUrl"]').clear().type('http://localhost:4316');

      cy.contains('button', 'Test Connection').click();

      // Either "Cannot connect" (no server listening) or "Connected" — both mean validation passed
      cy.contains(
        /Cannot connect to OpenLP|Connected to OpenLP/,
        { timeout: 3000 }
      ).should('be.visible');
    });

    it('should require http or https scheme', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      cy.get('[name="serverUrl"]').clear().type('gopher://example.com');

      cy.contains('button', 'Test Connection').click();

      cy.contains(
        'Only http and https schemes are allowed',
        { timeout: 3000 }
      ).should('be.visible');
    });
  });
});
