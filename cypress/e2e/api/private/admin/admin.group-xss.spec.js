/// <reference types="cypress" />

describe('Admin API: Group XSS-related behavior', () => {
  const testGroupId = 9;

  it('API sanitizes script tags from Description (strip-tags)', () => {
    const payload = '<script>window.apiXss=true;</script>';
    const expectedSanitized = 'window.apiXss=true;';

    // Update group description via admin API key
    cy.makePrivateAdminAPICall('POST', `/api/groups/${testGroupId}`, {
      groupName: 'Church Board',
      description: payload,
      groupType: '0'
    }, 200).then(() => {
      // Verify GET returns the stored description (API is transport/storage layer)
      cy.makePrivateAdminAPICall('GET', `/api/groups/${testGroupId}`, null, 200).then((resp) => {
        // API uses capitalized property names from the ORM -> toArray()
        expect(resp.body).to.have.property('Description');
        // InputSanitizationMiddleware uses sanitizeText -> strip_tags
        expect(resp.body.Description).to.equal(expectedSanitized);
      });

      // Cleanup
      cy.makePrivateAdminAPICall('POST', `/api/groups/${testGroupId}`, {
        groupName: 'Church Board',
        description: '',
        groupType: '0'
      }, 200);
    });
  });

  it('API sanitizes special HTML characters in Description (strip-tags)', () => {
    const special = `Test with <>&"' characters`;
    const expectedSanitized = `Test with &"' characters`;

    cy.makePrivateAdminAPICall('POST', `/api/groups/${testGroupId}`, {
      groupName: 'Church Board',
      description: special,
      groupType: '0'
    }, 200).then(() => {
      cy.makePrivateAdminAPICall('GET', `/api/groups/${testGroupId}`, null, 200).then((resp) => {
        // strip_tags removes HTML-like tags; expect inner text with '<>' removed
        expect(resp.body.Description).to.equal(expectedSanitized);
      });

      // Cleanup
      cy.makePrivateAdminAPICall('POST', `/api/groups/${testGroupId}`, {
        groupName: 'Church Board',
        description: '',
        groupType: '0'
      }, 200);
    });
  });
});
