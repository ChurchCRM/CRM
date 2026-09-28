/**
 * @file Field-Level ACL Tests for Person Custom Fields
 * @description Tests that restricted person custom fields are properly filtered based on user permissions
 * @issue GHSA-p6xx-xx98-f323
 */

describe('Field-Level ACL on Person Custom Fields', () => {
  const testPersonId = 1; // Assuming person 1 exists with custom fields
  const financeUser = { username: 'finance_user', password: 'password' }; // Has bFinance
  const notesUser = { username: 'notes_user', password: 'password' }; // Has bNotes
  const lowPrivilegeUser = { username: 'lowpriv_user', password: 'password' }; // No Finance/Notes
  const adminUser = { username: 'admin_user', password: 'password' }; // Has all permissions

  // Helper: Setup custom field with specific security level
  const setupCustomField = (fieldName, securityLevel) => {
    // This would be set up in fixtures or beforeAll
    // We assume test fixtures create these fields
    cy.log(`Custom field '${fieldName}' has security level: ${securityLevel}`);
  };

  describe('Person View Display (Web UI)', () => {
    it('should hide Finance-restricted custom fields from users without Finance permission', () => {
      cy.login(lowPrivilegeUser.username, lowPrivilegeUser.password);
      cy.visit(`/people/view/${testPersonId}`);

      // Verify restricted Finance field is NOT visible
      cy.get('[data-custom-field="Finance_Account"]').should('not.exist');
      cy.contains('Finance Account Number').should('not.exist');
    });

    it('should show Finance-restricted custom fields to users with Finance permission', () => {
      cy.login(financeUser.username, financeUser.password);
      cy.visit(`/people/view/${testPersonId}`);

      // Verify restricted Finance field IS visible
      cy.get('[data-custom-field="Finance_Account"]').should('be.visible');
      cy.contains('Finance Account Number').should('be.visible');
    });

    it('should hide Notes-restricted custom fields from users without Notes permission', () => {
      cy.login(lowPrivilegeUser.username, lowPrivilegeUser.password);
      cy.visit(`/people/view/${testPersonId}`);

      // Verify restricted Notes field is NOT visible
      cy.get('[data-custom-field="Pastoral_Notes"]').should('not.exist');
      cy.contains('Pastoral Notes').should('not.exist');
    });

    it('should show Notes-restricted custom fields to users with Notes permission', () => {
      cy.login(notesUser.username, notesUser.password);
      cy.visit(`/people/view/${testPersonId}`);

      // Verify restricted Notes field IS visible
      cy.get('[data-custom-field="Pastoral_Notes"]').should('be.visible');
      cy.contains('Pastoral Notes').should('be.visible');
    });

    it('should show all custom fields to admin users', () => {
      cy.login(adminUser.username, adminUser.password);
      cy.visit(`/people/view/${testPersonId}`);

      // Verify all fields are visible
      cy.get('[data-custom-field="Finance_Account"]').should('be.visible');
      cy.get('[data-custom-field="Pastoral_Notes"]').should('be.visible');
      cy.contains('Finance Account Number').should('be.visible');
      cy.contains('Pastoral Notes').should('be.visible');
    });

    it('should show bAll custom fields to all users regardless of permissions', () => {
      cy.login(lowPrivilegeUser.username, lowPrivilegeUser.password);
      cy.visit(`/people/view/${testPersonId}`);

      // Verify unrestricted field is visible
      cy.get('[data-custom-field="Public_Field"]').should('be.visible');
      cy.contains('Public Information').should('be.visible');
    });
  });

  describe('Person List Display (Web UI)', () => {
    it('should hide Finance-restricted custom fields from users without Finance permission in list', () => {
      cy.login(lowPrivilegeUser.username, lowPrivilegeUser.password);
      cy.visit('/people/list');

      // Find the row for test person
      cy.get(`tr[data-person-id="${testPersonId}"]`).within(() => {
        cy.get('[data-column="Finance_Account"]').should('not.exist');
      });
    });

    it('should show Finance-restricted custom fields to Finance users in list', () => {
      cy.login(financeUser.username, financeUser.password);
      cy.visit('/people/list');

      // Find the row for test person
      cy.get(`tr[data-person-id="${testPersonId}"]`).within(() => {
        cy.get('[data-column="Finance_Account"]').should('be.visible');
      });
    });

    it('should show bAll custom fields to all users in list', () => {
      cy.login(lowPrivilegeUser.username, lowPrivilegeUser.password);
      cy.visit('/people/list');

      // Find the row for test person
      cy.get(`tr[data-person-id="${testPersonId}"]`).within(() => {
        cy.get('[data-column="Public_Field"]').should('be.visible');
      });
    });
  });

  describe('Person API Endpoint', () => {
    it('should filter Finance-restricted custom fields from API response for unprivileged users', () => {
      cy.login(lowPrivilegeUser.username, lowPrivilegeUser.password);

      cy.request({
        method: 'GET',
        url: `/api/person/${testPersonId}`,
        headers: { 'Authorization': `Bearer ${lowPrivilegeUser.token}` }
      }).then((response) => {
        expect(response.status).to.equal(200);

        // Check singlePersonCustom array
        const customFields = response.body.singlePersonCustom || [];
        const financeFields = customFields.filter(f => f.id === 'Finance_Account');

        // Should NOT contain Finance field
        expect(financeFields).to.have.length(0);
      });
    });

    it('should include Finance-restricted custom fields in API response for Finance users', () => {
      cy.login(financeUser.username, financeUser.password);

      cy.request({
        method: 'GET',
        url: `/api/person/${testPersonId}`,
        headers: { 'Authorization': `Bearer ${financeUser.token}` }
      }).then((response) => {
        expect(response.status).to.equal(200);

        // Check singlePersonCustom array
        const customFields = response.body.singlePersonCustom || [];
        const financeFields = customFields.filter(f => f.id === 'Finance_Account');

        // SHOULD contain Finance field
        expect(financeFields.length).to.be.greaterThan(0);
      });
    });

    it('should include all custom fields in API response for admin users', () => {
      cy.login(adminUser.username, adminUser.password);

      cy.request({
        method: 'GET',
        url: `/api/person/${testPersonId}`,
        headers: { 'Authorization': `Bearer ${adminUser.token}` }
      }).then((response) => {
        expect(response.status).to.equal(200);

        // Check singlePersonCustom array
        const customFields = response.body.singlePersonCustom || [];

        // Should contain both restricted fields
        const financeFields = customFields.filter(f => f.id === 'Finance_Account');
        const notesFields = customFields.filter(f => f.id === 'Pastoral_Notes');

        expect(financeFields.length).to.be.greaterThan(0);
        expect(notesFields.length).to.be.greaterThan(0);
      });
    });

    it('should always include bAll custom fields in API response regardless of permissions', () => {
      cy.login(lowPrivilegeUser.username, lowPrivilegeUser.password);

      cy.request({
        method: 'GET',
        url: `/api/person/${testPersonId}`,
        headers: { 'Authorization': `Bearer ${lowPrivilegeUser.token}` }
      }).then((response) => {
        expect(response.status).to.equal(200);

        // Check singlePersonCustom array
        const customFields = response.body.singlePersonCustom || [];
        const publicFields = customFields.filter(f => f.id === 'Public_Field');

        // SHOULD contain public field
        expect(publicFields.length).to.be.greaterThan(0);
      });
    });
  });

  describe('CSV Export', () => {
    it('should exclude Finance-restricted columns from CSV download for unprivileged users', () => {
      cy.login(lowPrivilegeUser.username, lowPrivilegeUser.password);
      cy.visit('/people/list');

      // Trigger CSV export
      cy.get('[data-export-format="csv"]').click();

      // Verify download
      cy.readFile('cypress/downloads/churchcrm_export.csv').then((fileContent) => {
        // Should NOT contain Finance column header
        expect(fileContent).to.not.include('Finance Account Number');

        // Should contain public field column
        expect(fileContent).to.include('Public Information');
      });
    });

    it('should include Finance-restricted columns in CSV for Finance users', () => {
      cy.login(financeUser.username, financeUser.password);
      cy.visit('/people/list');

      // Trigger CSV export
      cy.get('[data-export-format="csv"]').click();

      // Verify download
      cy.readFile('cypress/downloads/churchcrm_export.csv').then((fileContent) => {
        // SHOULD contain Finance column header
        expect(fileContent).to.include('Finance Account Number');
      });
    });
  });

  describe('Cross-Cutting Consistency', () => {
    it('should consistently filter the same fields across web view, list, and API', () => {
      // Test that restricted fields are filtered consistently
      cy.login(lowPrivilegeUser.username, lowPrivilegeUser.password);

      // Check web view
      cy.visit(`/people/view/${testPersonId}`);
      cy.get('[data-custom-field="Finance_Account"]').should('not.exist');

      // Check list view
      cy.visit('/people/list');
      cy.get(`tr[data-person-id="${testPersonId}"]`).within(() => {
        cy.get('[data-column="Finance_Account"]').should('not.exist');
      });

      // Check API response
      cy.request({
        method: 'GET',
        url: `/api/person/${testPersonId}`,
        headers: { 'Authorization': `Bearer ${lowPrivilegeUser.token}` }
      }).then((response) => {
        const financeFields = (response.body.singlePersonCustom || []).filter(f => f.id === 'Finance_Account');
        expect(financeFields).to.have.length(0);
      });
    });
  });

  describe('Edge Cases', () => {
    it('should handle persons with no custom fields gracefully', () => {
      cy.login(lowPrivilegeUser.username, lowPrivilegeUser.password);

      // Test with a person that has no custom fields
      cy.visit('/people/view/9999');
      cy.get('[data-section="additional-info"]').should('be.empty');
    });

    it('should handle persons where user has no visible custom fields', () => {
      cy.login(lowPrivilegeUser.username, lowPrivilegeUser.password);

      // Create scenario where person has only Finance and Notes fields
      // but user has neither permission
      cy.visit(`/people/view/${testPersonId}`);

      // Additional info section should be empty or hidden
      cy.get('[data-section="additional-info"]').then(($section) => {
        if ($section.length > 0) {
          cy.wrap($section).children('[data-custom-field]').should('have.length', 0);
        }
      });
    });

    it('should handle custom fields with special characters safely', () => {
      cy.login(financeUser.username, financeUser.password);
      cy.visit(`/people/view/${testPersonId}`);

      // Verify fields with special characters are rendered safely
      cy.get('[data-custom-field]').each(($field) => {
        // Should have proper HTML escaping, no unescaped content
        expect($field.html()).to.not.include('<script');
        expect($field.html()).to.not.include('javascript:');
      });
    });
  });
});
