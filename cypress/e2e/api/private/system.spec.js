/// <reference types="cypress" />

describe("Check Notification API", () => {
    it("Notification API", () => {
        cy.makePrivateUserAPICall(
            "GET",
            "/api/system/notification",
            "",
            200,
        );
    });
});
