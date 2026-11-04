/// <reference types="cypress" />

describe("Test Post Setup block", () => {
    const saved = {};

    before(() => {
        ["bMemberSignIn", "bEnableSelfRegistration"].forEach((name) => {
            cy.getSystemConfig(name).then((value) => {
                saved[name] = value;
            });
            cy.setSystemConfig(name, "0");
        });
    });

    after(() => {
        Object.entries(saved).forEach(([name, value]) => cy.restoreSystemConfig(name, value));
    });

    it("Redirects a signed-out visitor through the web root to the staff sign-in while member sign-in is off", () => {
        cy.clearCookies();
        cy.clearLocalStorage();
        cy.visit("/setup");
        cy.location("pathname", { timeout: 10000 }).should("match", /\/admin\/$/);
    });
});
