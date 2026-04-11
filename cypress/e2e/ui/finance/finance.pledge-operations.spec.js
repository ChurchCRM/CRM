/// <reference types="cypress" />

describe("Pledge Operations", () => {
    beforeEach(() => cy.setupStandardSession());

    it("PledgeDelete page loads confirmation form without errors", () => {
        cy.visit("PledgeDelete.php?GroupKey=test&linkBack=v2/dashboard");
        cy.contains("Confirm Delete");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Warning:");
        cy.get('input[name="Delete"]').should("exist");
        cy.get('input[name="Cancel"]').should("exist");
    });

    it("PledgeDelete cancel redirects back", () => {
        cy.visit("PledgeDelete.php?GroupKey=test&linkBack=v2/dashboard");
        cy.get('input[name="Cancel"]').click();
        cy.url().should("contain", "v2/dashboard");
    });
});
