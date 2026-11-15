/// <reference types="cypress" />

describe("API Private Family Verify", () => {
    it("Verify family immediately without email", () => {
        // Test the /verify/now endpoint which doesn't send emails
        cy.makePrivateAdminAPICall("POST", "/api/family/2/verify/now", null, 200);
    });


    it("Emails an approved family the portal link", () => {
        cy.makePrivateAdminAPICall("POST", "/api/family/2/verify", null, 200);
    });

    it("Rejects a user without EditRecords", () => {
        cy.makePrivateNoPermAPICall("POST", "/api/family/2/verify", null, 403);
        cy.makePrivateNoPermAPICall("POST", "/api/family/2/verify/now", null, 403);
    });
});
