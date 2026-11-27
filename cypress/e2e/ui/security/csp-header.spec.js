/// <reference types="cypress" />

describe("Content-Security-Policy header", () => {
    afterEach(() => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/bEnforceCSP", { value: "1" }, 200);
    });

    it("is enforced on the login page", () => {
        cy.request("/session/begin").then((response) => {
            const csp = response.headers["content-security-policy"];
            expect(csp, "enforcing CSP header").to.be.a("string");
            expect(response.headers).not.to.have.property("content-security-policy-report-only");
            expect(csp).to.match(/script-src [^;]*'nonce-/);
        });
    });

    it("drops to report-only when bEnforceCSP is off", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/bEnforceCSP", { value: "0" }, 200);
        cy.request("/session/begin").then((response) => {
            expect(response.headers).not.to.have.property("content-security-policy");
            expect(response.headers["content-security-policy-report-only"]).to.match(/script-src [^;]*'nonce-/);
        });
    });
});
