/// <reference types="cypress" />

/**
 * Member sign-in row 11 (design §10.2): families have no email of their own.
 * Seed family 2 (Hart): head Constance and spouse Marion each have an email.
 */
describe("Family email belongs to the adults", () => {
    const hartFamilyId = 2;

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("shows each adult's own email on the family page", () => {
        cy.visit(`/people/family/${hartFamilyId}`);
        cy.get("#family-email-card [data-family-adult-email]").should("have.length", 2);
        cy.get("#family-email-card")
            .should("contain.text", "constance.hart@example.com")
            .and("contain.text", "Constance Hart")
            .and("contain.text", "marion.sutton@example.com")
            .and("contain.text", "Marion Hart");
        cy.get("#family-email-composer")
            .should("have.attr", "data-email-family-id", String(hartFamilyId))
            .and("have.attr", "data-email-address", "constance.hart@example.com");
    });

    it("has no Email field in the family editor", () => {
        cy.visit(`/FamilyEditor.php?FamilyID=${hartFamilyId}`);
        cy.contains("Family Info");
        cy.get('input[name="HomePhone"]').should("exist");
        cy.get('input[name="Email"]').should("not.exist");
    });

    it("shows the head of household's email in the family list", () => {
        cy.visit("/people/family/");
        cy.get("#families_wrapper input[type='search']").type("Hart");
        cy.contains("#families tbody tr", "Hart").should("contain.text", "constance.hart@example.com");
    });

    it("no longer marks the person editor's Email label for a family email", () => {
        cy.visit("/PersonEditor.php?PersonID=6");
        cy.get('label[for="Email"]').should("be.visible").find(".text-danger").should("not.exist");
    });
});
