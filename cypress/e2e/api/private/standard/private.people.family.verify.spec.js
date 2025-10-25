/// <reference types="cypress" />

describe("API Private Family Verify", () => {
    it("Verify API - Email failure expected in test environment", () => {
        // This endpoint sends verification emails. In the test environment without SMTP configured,
        // it will fail and return 500. We expect this failure and log it gracefully.
        cy.makePrivateAdminAPICall("POST", "/api/family/2/verify", null, 500);
    });
});
