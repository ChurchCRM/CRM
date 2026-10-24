/// <reference types="cypress" />

describe("Test Post Setup block", () => {
    it("Redirects a signed-out visitor through the web root to the member sign-in page", () => {
        cy.clearCookies();
        cy.clearLocalStorage();
        cy.visit("/setup");
        cy.location("pathname", { timeout: 10000 }).should("eq", "/portal/login");
    });
});
