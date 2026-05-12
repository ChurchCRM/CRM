/// <reference types="cypress" />

/**
 * CSS Regression: Authentication Pages
 *
 * Verifies that auth-page-specific CSS (scoped under body.page-auth)
 * renders correctly on login, password reset, and error pages.
 * Guards against regressions from the global→scoped CSS migration.
 */

/** Toggle bEnableSelfRegistration via the admin API. value: "1" = on, "0" = off. */
function setSelfReg(value) {
    cy.makePrivateAdminAPICall(
        "POST",
        "admin/api/system/config/bEnableSelfRegistration",
        { value },
    );
}

describe("Auth Page CSS Regression", () => {
    describe("Login Page Styling", () => {
        beforeEach(() => {
            cy.visit("/session/begin");
        });

        it("Should have page-auth body class", () => {
            cy.get("body").should("have.class", "page-auth");
        });

        it("Should render login container and card with proper layout", () => {
            cy.get(".login-container").should("be.visible");
            cy.get(".login-card").should("be.visible");
        });

        it("Should render sign-in button with auth-specific gradient styling", () => {
            cy.get(".btn-sign-in").should("be.visible")
                .invoke("css", "background-image")
                .should("include", "gradient");
        });

        it("Should render form inputs inside login form", () => {
            cy.get("input[name='User']").should("be.visible");
            cy.get("input[name='Password']").should("be.visible");
        });

        it("Should display login card header with church name or logo", () => {
            cy.get(".login-card-header").should("be.visible");
        });
    });

    describe("Login Page — Segmented Pill Control (self-registration)", () => {
        after(() => {
            // Restore self-reg to disabled after this suite so other tests
            // are not affected by the enabled state.
            setSelfReg("0");
        });

        it("Pill control is hidden when self-registration is disabled", () => {
            setSelfReg("0");
            cy.visit("/session/begin");
            cy.get(".login-tab-control").should("not.exist");
            // Plain form title should appear instead
            cy.get(".login-form-title").should("be.visible");
        });

        it("Pill control is visible when self-registration is enabled", () => {
            setSelfReg("1");
            cy.visit("/session/begin");
            cy.get(".login-tab-control").should("be.visible");
            cy.get(".login-tab-btn").should("have.length", 2);
        });

        it("Sign In pill is active by default", () => {
            setSelfReg("1");
            cy.visit("/session/begin");
            cy.get("#tab-signin").should("have.class", "active");
            cy.get("#tab-register").should("not.have.class", "active");
        });

        it("Register pill links to the registration page in a new tab", () => {
            setSelfReg("1");
            cy.visit("/session/begin");
            cy.get("#tab-register")
                .should("have.attr", "href")
                .and("include", "/external/register/");
            cy.get("#tab-register").should("have.attr", "target", "_blank");
        });

        it("Login form is always visible regardless of self-reg setting", () => {
            setSelfReg("1");
            cy.visit("/session/begin");
            cy.get("input[name='User']").should("be.visible");
            cy.get("input[name='Password']").should("be.visible");
            cy.get(".btn-sign-in").should("be.visible");
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
            cy.get(".btn-reset").should("be.visible")
                .invoke("css", "background-image")
                .should("include", "gradient");
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

    describe("Login Page Query Parameters", () => {
        it("Should prefill username from query parameter", () => {
            cy.visit("/session/begin?username=test@user.com");
            cy.get('input[id="UserBox"]').should("have.value", "test@user.com");
        });
    });
});
