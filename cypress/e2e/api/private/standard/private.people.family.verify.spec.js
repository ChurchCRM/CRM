/// <reference types="cypress" />

describe("API Private Family Verify", () => {
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
