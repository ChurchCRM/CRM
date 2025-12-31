describe('Family phone No Format behavior', () => {
  beforeEach(() => cy.setupAdminSession());

  it('creates a family with a raw/international phone and preserves value when editing', () => {
    const familyName = 'Cypress NoFormat ' + Date.now();
    const rawPhone = '+44 7700 900123';
    // Create a family
    cy.visit('/FamilyEditor.php');
    cy.get('#FamilyName').clear().type(familyName);
    cy.get('#HomePhone').clear().type(rawPhone);
    // Check the No format box before saving
    cy.get('input[name="NoFormat_HomePhone"]').check();
    // Wait for the form to be ready (Envelope field is conditional), use FamilyName as reliable anchor
    cy.get('#FamilyName').then(() => {
      // Save
      cy.get('input[name="FamilySubmit"]').click();
    });

    // After save, we should be redirected to PersonView or family list. Find the family and open editor.
    // Use the People/Family search to find the created family
    cy.visit('/v2/family');
    cy.get('input[placeholder="Search"]').type(familyName + '{enter}');
    cy.contains(familyName).click();

    // Click Edit (Family Editor)
    cy.contains('Edit').click();

    // On edit, the No format checkbox should be checked and the HomePhone input should contain the raw value
    cy.get('input[name="NoFormat_HomePhone"]').should('be.checked');
    cy.get('#HomePhone').should('have.value', rawPhone);

    // Now change the value to a different raw value and save
    const newRaw = '+44 7700 900999';
    cy.get('#HomePhone').clear().type(newRaw);
    cy.get('input[name="NoFormat_HomePhone"]').should('be.checked');
    cy.get('input[name="FamilySubmit"]').click();

    // Re-open editor to verify saved value
    cy.visit('/v2/family');
    cy.get('input[placeholder="Search"]').clear().type(familyName + '{enter}');
    cy.contains(familyName).click();
    cy.contains('Edit').click();

    cy.get('#HomePhone').should('have.value', newRaw);
    cy.get('input[name="NoFormat_HomePhone"]').should('be.checked');
  });
});
