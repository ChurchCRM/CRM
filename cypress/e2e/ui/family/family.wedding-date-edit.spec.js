/// <reference types="cypress" />

describe("Family Wedding Date Edit Workflow", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Create family without wedding date, then add wedding date via edit", () => {
        // Step 1: Create a new family without a wedding date
        cy.visit("FamilyEditor.php");

        cy.contains("Family Info");

        // Fill in Family Info section
        const familyName = "Smith" + Cypress._.random(0, 1e6);
        cy.get("#FamilyName").type(familyName);
        cy.get('input[name="Address1"]').type("123 Main Street");
        cy.get('input[name="City"]').clear().type("Springfield");
        cy.get('select[name="State"]').select("IL", { force: true });

        // Fill in Contact Information
        cy.get('input[name="Email"]').type("test@example.com");

        // Fill in Family Members - add at least one person
        cy.get('input[name="FirstName1"]').type("John");
        cy.get('select[name="Classification1"]').select("1", { force: true });

        // NOTE: Do NOT fill in the Wedding Date field

        // Save the family
        cy.get('button[name="FamilySubmit"]').click();

        // Should redirect to family view page
        cy.location("pathname").should("include", "/people/family/");
        cy.contains("Family Profile");

        // Verify wedding date is NOT displayed
        // Check that the ring icon element doesn't exist in the page
        cy.get("i.fa-ring").should("not.exist");

        // Step 2: View the family profile and verify wedding date section doesn't show
        // (Already on the family view page from creation)
        cy.contains(familyName).should("exist");

        // Step 3: Edit the family to add a wedding date
        cy.get('a.btn-ghost-primary').contains("Edit").click();

        // Verify we're on the Family Editor page
        cy.contains("Family Info");

        // Fill in the Wedding Date
        const weddingYear = "2020";
        const weddingMonth = "06";
        const weddingDay = "15";
        cy.get("#WeddingDate").type(
            `${weddingYear}-${weddingMonth}-${weddingDay}`,
        );

        // Save the changes
        cy.get('button[name="FamilySubmit"]').click();

        // Should be back on family view page
        cy.location("pathname").should("include", "/people/family/");
        cy.contains("Family Profile");

        // Step 4: Verify the wedding date is now displayed
        // Check that the ring icon is visible
        cy.get("i.fa-ring").should("be.visible");

        // Verify the formatted wedding date is displayed on the page
        cy.contains(`${weddingMonth}/${weddingDay}/${weddingYear}`).should("be.visible");

        // Verify the wedding date appears in a list item with the ring icon
        cy.get("li").contains(`${weddingMonth}/${weddingDay}/${weddingYear}`).should("be.visible");
        cy.get("li").contains(`${weddingMonth}/${weddingDay}/${weddingYear}`).find("i.fa-ring").should("be.visible");
    });
});
