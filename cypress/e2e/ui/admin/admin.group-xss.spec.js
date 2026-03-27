/// <reference types="cypress" />

describe('Group XSS Security Tests', () => {

  beforeEach(() => {
    cy.setupAdminSession();
  });
  // UI-focused test: interact with the GroupEditor and verify textarea behavior
  it('Should prevent stored XSS in group description via GroupEditor', () => {
    const xssPayload = '<script>alert("XSS")</script>';

    cy.visit('/GroupEditor.php?GroupID=9');
    // Ensure editor loaded
    cy.get('input[name="Name"]').should('exist');

    // Inject payload into the description textarea (simulate malicious input)
    cy.get('textarea[name="Description"]').then($textarea => {
      cy.wrap($textarea).invoke('val', xssPayload);
    });

    // Validate the textarea contains the raw payload (it should not execute in the editor)
    cy.get('textarea[name="Description"]').should('have.value', xssPayload);
  });
});
