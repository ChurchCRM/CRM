/// <reference types="cypress" />

/**
 * Login redirect flows for the dedicated seed users (see cypress/data/seed.sql):
 *   - twofa_user      — usr_TwoFactorAuthSecret set  → 2FA challenge
 *   - mustchange.user — usr_NeedPasswordChange = 1    → forced password change
 *   - locked.user     — usr_FailedLogins = 99         → rejected, stays on login
 *
 * All three use password "changeme".
 */
function login(userName, password) {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(userName);
    cy.get("input[name=Password]").type(`${password}{enter}`);
}

describe("Session Login Flows", () => {
    describe("2FA-enabled user (twofa_user)", () => {
        it("Valid password redirects to the 2FA challenge", () => {
            login("twofa_user", "changeme");
            cy.url({ timeout: 10000 }).should("include", "/session/two-factor");
            cy.get("#TwoFACode").should("exist");
        });
    });

    describe("Password-change-required user (mustchange.user)", () => {
        it("Login redirects to the change-password page", () => {
            login("mustchange.user", "changeme");
            cy.url({ timeout: 10000 }).should("include", "/v2/user/current/changepassword");
            cy.get("#OldPassword").should("exist");
        });
    });

    describe("Locked account (locked.user)", () => {
        it("Correct password is rejected and stays on the login page", () => {
            // Locked from the first attempt (seeded over iMaxFailedLogins): no session
            // is granted, so the user never reaches the dashboard or any next step.
            login("locked.user", "changeme");
            cy.url({ timeout: 10000 }).should("include", "/session/begin");
            cy.get("input[name=User]").should("exist");
        });
    });
});
