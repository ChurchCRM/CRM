/// <reference types="cypress" />

describe("Check Notification API", () => {
    it("Notification API", () => {
        const result = cy.makePrivateUserAPICall(
            "GET",
            "/api/system/notification",
            "",
            200,
        );
    });
});
