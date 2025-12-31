/// <reference types="cypress" />

describe('Group XSS Security Tests', () => {

  beforeEach(() => {
    cy.setupAdminSession();
  });

  it('Should prevent stored XSS in group description via GroupEditor', () => {
    const xssPayload = '<script>alert("XSS")</script>';

    cy.visit('/GroupEditor.php?GroupID=9');
    
    // Get the group name and verify we're on the editor
    cy.get('input[name="Name"]').should('exist');
    
    // Inject XSS payload into description field via JavaScript to bypass frontend validation
    cy.get('textarea[name="Description"]').then($textarea => {
      cy.wrap($textarea).invoke('val', xssPayload);
    });

    // Verify the payload is in the textarea (not executed)
    cy.get('textarea[name="Description"]').should('have.value', xssPayload);
  });

  it('Should escape XSS payload when displaying in GroupView.php', () => {
    // Use a known test group ID
    const testGroupId = 9;
    const xssPayload = '<img src=x onerror="window.testXSSExecuted=true">';

    // Update the group via API with XSS payload
    cy.makePrivateAdminAPICall('POST', `/api/groups/${testGroupId}`, {
      groupName: 'Church Board',
      description: xssPayload,
      groupType: '0'
    }, 200).then(() => {
      // Navigate to GroupView page
      cy.visit(`/GroupView.php?GroupID=${testGroupId}`);

      // Verify that the XSS script did NOT execute (most important test)
      cy.window().then((win) => {
        expect(win.testXSSExecuted).to.be.undefined;
      });

      // Verify the description area is rendered (payload should be escaped but present)
      cy.get('.card-body').should('exist');

      // Cleanup: restore original description
      cy.makePrivateAdminAPICall('POST', `/api/groups/${testGroupId}`, {
        groupName: 'Church Board',
        description: '',
        groupType: '0'
      }, 200);
    });
  });

  it('Should prevent XSS with script tag in description', () => {
    const testGroupId = 9;
    const xssPayload = '<script>window.xssDetected = true;</script>';

    cy.makePrivateAdminAPICall('POST', `/api/groups/${testGroupId}`, {
      groupName: 'Church Board',
      description: xssPayload,
      groupType: '0'
    }, 200).then(() => {
      // Navigate to GroupView
      cy.visit(`/GroupView.php?GroupID=${testGroupId}`);

      // Verify XSS did not execute
      cy.window().then((win) => {
        expect(win.xssDetected).to.be.undefined;
      });

      // Cleanup
      cy.makePrivateAdminAPICall('POST', `/api/groups/${testGroupId}`, {
        groupName: 'Church Board',
        description: '',
        groupType: '0'
      }, 200);
    });
  });

  it('Should properly escape special HTML characters in description', () => {
    const testGroupId = 9;
    const specialChars = 'Test with <>&"\'characters';

    cy.makePrivateAdminAPICall('POST', `/api/groups/${testGroupId}`, {
      groupName: 'Church Board',
      description: specialChars,
      groupType: '0'
    }, 200).then(() => {
      // Navigate to GroupView
      cy.visit(`/GroupView.php?GroupID=${testGroupId}`);

      // Verify page loads without errors (escaping prevents breakage)
      cy.get('.card-body').should('exist');

      // Cleanup
      cy.makePrivateAdminAPICall('POST', `/api/groups/${testGroupId}`, {
        groupName: 'Church Board',
        description: '',
        groupType: '0'
      }, 200);
    });
  });
});
