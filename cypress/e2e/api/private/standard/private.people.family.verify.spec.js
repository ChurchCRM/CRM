/// <reference types="cypress" />

context("API Private Family Verify", () => {
    it("Verify API", () => {
        let result = cy.makePrivateUserAPICall(
            "POST",
            "/api/family/2/verify",
            "",
            200,
        );
    });
});
