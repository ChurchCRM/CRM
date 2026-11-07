/// <reference types="cypress" />

/**
 * CSS Regression: Authentication Pages
 *
 * Verifies that auth-page-specific CSS (scoped under body.page-auth)
 * renders correctly on login, password reset, and error pages.
 * Guards against regressions from the global→scoped CSS migration.
 */

/** Toggle bEnableSelfRegistration via the admin API. value: "1" = on, "0" = off. */
function setConfigValue(name, value) {
    cy.makePrivateAdminAPICall("POST", `admin/api/system/config/${name}`, { value });
}

function setSelfReg(value) {
    setConfigValue("bEnableSelfRegistration", value);
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

    describe("Portal sign-in — optional family registration", () => {
        let savedSelfReg;
        let savedMemberSignIn;

        before(() => {
            cy.getSystemConfig("bEnableSelfRegistration").then((value) => {
                savedSelfReg = value;
            });
            cy.getSystemConfig("bMemberSignIn").then((value) => {
                savedMemberSignIn = value;
            });
        });

        after(() => {
            cy.restoreSystemConfig("bEnableSelfRegistration", savedSelfReg);
            cy.restoreSystemConfig("bMemberSignIn", savedMemberSignIn);
        });

        it("hides registration when self-registration is disabled", () => {
            setConfigValue("bMemberSignIn", "1");
            setSelfReg("0");
            cy.visit("/portal/login");
            cy.contains("Register your family").should("not.exist");
            cy.request({ url: "/portal/register", followRedirect: false, failOnStatusCode: false }).its("status").should("eq", 404);
            cy.request({ url: "/external/register/", failOnStatusCode: false }).its("status").should("eq", 404);
        });

        it("offers family registration from the portal sign-in when enabled", () => {
            setConfigValue("bMemberSignIn", "1");
            setSelfReg("1");
            cy.visit("/portal/login");
            cy.contains("a", "Register your family").should("have.attr", "href").and("include", "/portal/register");
            cy.visit("/portal/register");
            cy.get("#registration-stepper").should("be.visible");
            cy.request({ url: "/external/register/", followRedirect: false }).then((response) => {
                expect(response.status).to.eq(302);
                expect(response.headers.location).to.include("/portal/register");
            });
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
