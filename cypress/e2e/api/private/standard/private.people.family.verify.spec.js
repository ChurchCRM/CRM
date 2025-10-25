/// <reference types="cypress" />

describe("API Private Family Verify", () => {
    it.skip("Verify API with email - Requires SMTP configuration", () => {
        // This endpoint sends verification emails and requires SMTP to be configured.
        // Skipped in test environment. Run manually with SMTP configured to test email flow.
        cy.makePrivateAdminAPICall("POST", "/api/family/2/verify", null, 200);
    });

    it("Verify family immediately without email", () => {
        // Test the /verify/now endpoint which doesn't send emails
        cy.makePrivateAdminAPICall("POST", "/api/family/2/verify/now", null, 200);
    });

    it("Get family verification URL", () => {
        // Test getting verification URL without sending email
        cy.makePrivateAdminAPICall("GET", "/api/family/2/verify/url", null, 200).then(
            (response) => {
                expect(response.body).to.have.property("url");
                expect(response.body.url).to.include("/external/verify/");
            },
        );
    });
});
