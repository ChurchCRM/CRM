/// <reference types="cypress" />

describe("Test Post Setup block", () => {
    it("Redirects to session/begin", () => {
        // cy.request() follows redirects but skips JS/CSS/image loading — much faster than cy.visit().
        // redirectedToUrl is the final URL after all redirect hops.
        cy.request("/setup").its("redirectedToUrl").should("include", "session/begin");
    });
});
