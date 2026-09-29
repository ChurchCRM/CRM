describe('OpenLP Plugin - SSRF Prevention (GHSA-hrfr-xg9w-hjm4)', () => {
  const PLUGIN_SETTINGS_URL = '/admin/plugins/openlp/settings';

  beforeEach(() => {
    cy.login('admin@example.com', 'password');
  });

  describe('URL Validation on Settings Test', () => {
    it('should reject localhost URLs', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      cy.get('[name="serverUrl"]').clear().type('http://localhost:4316');

      cy.contains('button', 'Test Connection').click();

      cy.contains(
        'Cannot connect to private or reserved network addresses',
        { timeout: 3000 }
      ).should('be.visible');
    });

    it('should reject 127.0.0.1 URLs (loopback)', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      cy.get('[name="serverUrl"]').clear().type('http://127.0.0.1:4316');

      cy.contains('button', 'Test Connection').click();

      cy.contains(
        'Cannot connect to private or reserved network addresses',
        { timeout: 3000 }
      ).should('be.visible');
    });

    it('should reject RFC1918 private IP ranges (10.x.x.x)', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      cy.get('[name="serverUrl"]').clear().type('http://10.0.0.1:4316');

      cy.contains('button', 'Test Connection').click();

      cy.contains(
        'Cannot connect to private or reserved network addresses',
        { timeout: 3000 }
      ).should('be.visible');
    });

    it('should reject RFC1918 private IP ranges (192.168.x.x)', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      cy.get('[name="serverUrl"]').clear().type('http://192.168.1.100:4316');

      cy.contains('button', 'Test Connection').click();

      cy.contains(
        'Cannot connect to private or reserved network addresses',
        { timeout: 3000 }
      ).should('be.visible');
    });

    it('should reject RFC1918 private IP ranges (172.16.x.x)', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      cy.get('[name="serverUrl"]').clear().type('http://172.16.0.1:4316');

      cy.contains('button', 'Test Connection').click();

      cy.contains(
        'Cannot connect to private or reserved network addresses',
        { timeout: 3000 }
      ).should('be.visible');
    });

    it('should reject link-local addresses (169.254.x.x)', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      cy.get('[name="serverUrl"]').clear().type('http://169.254.169.254');

      cy.contains('button', 'Test Connection').click();

      cy.contains(
        'Cannot connect to private or reserved network addresses',
        { timeout: 3000 }
      ).should('be.visible');
    });

    it('should reject invalid URL schemes', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      cy.get('[name="serverUrl"]').clear().type('file:///etc/passwd');

      cy.contains('button', 'Test Connection').click();

      cy.contains(
        'Invalid server URL format',
        { timeout: 3000 }
      ).should('be.visible');
    });

    it('should require a valid URL format', () => {
      cy.visit(PLUGIN_SETTINGS_URL);

      cy.get('[name="serverUrl"]').clear().type('not a valid url');

      cy.contains('button', 'Test Connection').click();

      cy.contains(
        'Invalid server URL format',
        { timeout: 3000 }
      ).should('be.visible');
    });
  });
});
