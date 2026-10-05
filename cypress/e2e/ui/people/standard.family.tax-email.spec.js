/// <reference types="cypress" />

/**
 * Finance menu → Email {year} Tax Doc on a family (#8336, #10335).
 *
 * Seed family 1 (Campbell) has 2018 payments and members with email addresses.
 * Whether the message goes out depends on the stack's mail setup, so either notice
 * counts; what the action must never do is stop on the report page with a 500.
 */
describe("Family Finance menu: Email Tax Doc", () => {
    beforeEach(() => cy.setupAdminSession());

    it("emails the year's giving statement and returns to the family", () => {
        cy.visit("/people/family/1");
        cy.contains("button", "Finance").click();
        cy.get("form[action$='/Reports/FamilyTaxReportEmail.php'] button").first().click();

        cy.location("pathname").should("match", /\/people\/family\/1$/);
        cy.location("search").should("match", /TaxEmailSent=\d{4}|TaxEmailError=SendFailed/);
    });
});
