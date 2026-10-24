/// <reference types="cypress" />

describe("Test Post Setup block", () => {
    it("Redirects a signed-out visitor through the web root to the staff sign-in while member sign-in is off", () => {
        cy.clearCookies();
        cy.clearLocalStorage();
        cy.visit("/setup");
        cy.location("pathname", { timeout: 10000 }).should("eq", "/admin/");
    });
});
