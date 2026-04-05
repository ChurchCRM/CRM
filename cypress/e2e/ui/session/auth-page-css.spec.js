/// <reference types="cypress" />

/**
 * CSS Regression: Authentication Pages
 *
 * Verifies that auth-page-specific CSS (scoped under body.page-auth)
 * renders correctly on login, password reset, and error pages.
 * Guards against regressions from the global→scoped CSS migration.
 */
describe("Auth Page CSS Regression", () => {
    describe("Login Page Styling", () => {
        beforeEach(() => {
            cy.visit("/session/begin");
        });

        it("Should have page-auth body class", () => {
            cy.get("body").should("have.class", "page-auth");
        });

        it("Should render login container with proper layout", () => {
            cy.get(".login-container").should("be.visible");
            cy.get(".login-wrapper").should("be.visible");
        });

        it("Should render sign-in button with auth-specific gradient styling", () => {
            cy.get(".btn-sign-in").should("be.visible").then(($btn) => {
                cy.window().then((win) => {
                    const bg = win.getComputedStyle($btn[0]).backgroundImage;
                    expect(bg).to.include("gradient");
                });
            });
        });

        it("Should render form inputs inside login form", () => {
            cy.get("input[name='User']").should("be.visible");
            cy.get("input[name='Password']").should("be.visible");
        });

        it("Should display login header with church name or logo", () => {
            cy.get(".login-form-header").should("be.visible");
        });
    });

    describe("Password Reset Page Styling", () => {
        beforeEach(() => {
            cy.visit("/session/forgot-password/reset-request");
        });

        it("Should have page-auth body class", () => {
            cy.get("body").should("have.class", "page-auth");
        });

        it("Should render forgot-password card with visible form", () => {
            cy.get(".forgot-password-card").should("be.visible");
            cy.get("input[name='username']").should("be.visible");
        });

        it("Should render reset button with auth-specific gradient styling", () => {
            cy.get(".btn-reset").should("be.visible").then(($btn) => {
                cy.window().then((win) => {
                    const bg = win.getComputedStyle($btn[0]).backgroundImage;
                    expect(bg).to.include("gradient");
                });
            });
        });
    });

    describe("Password Reset Error Page Styling", () => {
        it("Should render scoped alert on auth error page", () => {
            cy.visit("/session/forgot-password/set/invalid-token-css-test");

            cy.get("body").should("have.class", "page-auth");
            cy.get(".alert.alert-danger").should("be.visible");
            cy.get(".alert-buttons").should("be.visible");
            // Buttons inside alert-buttons should be styled
            cy.get(".alert-buttons a, .alert-buttons button").should("have.length.at.least", 1);
        });
    });
});
