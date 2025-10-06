/// <reference types="cypress" />

describe("API Private Family Verify", () => {
    it("Verify API", () => {
        const result = cy.makePrivateUserAPICall(
            "POST",
            "/api/family/2/verify",
            "",
            200,
        );
    });
});
