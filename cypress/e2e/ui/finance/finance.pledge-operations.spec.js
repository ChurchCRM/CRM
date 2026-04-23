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

    // GHSA-3xq9-c86x-cwpp — CSRF protection on delete
    it("PledgeDelete renders a CSRF token input", () => {
        cy.visit("PledgeDelete.php?GroupKey=test&linkBack=v2/dashboard");
        cy.get('input[name="csrf_token"]').should("have.attr", "value").and("match", /^[a-f0-9]{64}$/);
    });

    it("PledgeDelete rejects POST without a valid CSRF token", () => {
        cy.request({
            method: "POST",
            url: "PledgeDelete.php?GroupKey=test&linkBack=v2/dashboard",
            form: true,
            body: { Delete: "Delete", csrf_token: "bogus" },
            failOnStatusCode: false,
        }).its("status").should("eq", 403);
    });
});
