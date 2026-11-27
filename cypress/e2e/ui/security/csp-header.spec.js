/// <reference types="cypress" />

describe("Content-Security-Policy header", () => {
    it("is enforced on the login page", () => {
        cy.request("/session/begin").then((response) => {
            const csp = response.headers["content-security-policy"];
            expect(csp, "enforcing CSP header").to.be.a("string");
            expect(response.headers).not.to.have.property("content-security-policy-report-only");
            expect(csp).to.match(/script-src [^;]*'nonce-/);
        });
    });
});
