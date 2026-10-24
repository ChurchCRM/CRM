/// <reference types="cypress" />

/**
 * Login throttling and uniform answers on the sign-in pages (#10197).
 * Seed users, password "changeme": login_lockout_user (907) and
 * twofa_lockout_user (903), unlocked before and after each test because
 * public.user.spec.js locks them too; locked.user (7), locked until 2100.
 */
const PASSWORD = "changeme";
const GENERIC_MESSAGE = "Invalid login or password";
const LOCKOUT_USER = "login_lockout_user";
const LOCKOUT_USER_ID = 907;
const TWOFA_USER = "twofa_lockout_user";
const TWOFA_USER_ID = 903;

function login(userName, password) {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(userName);
    cy.get("input[name=Password]").type(`${password}{enter}`, { log: false });
}

function postLogin(userName, password) {
    cy.clearCookies();
    return cy.request({ method: "POST", url: "/session/begin", form: true, body: { User: userName, Password: password } });
}

const unlock = (userId) => cy.makePrivateAdminAPICall("POST", `/admin/api/user/${userId}/login/reset`, null, 200);

describe("Sign-in page throttling (#10197)", () => {
    let maxFailedLogins;

    before(() => {
        cy.getSystemConfig("iMaxFailedLogins").then((value) => {
            maxFailedLogins = Number(value);
        });
    });

    beforeEach(() => {
        unlock(LOCKOUT_USER_ID);
        unlock(TWOFA_USER_ID);
    });

    after(() => {
        unlock(LOCKOUT_USER_ID);
        unlock(TWOFA_USER_ID);
    });

    it("shows the same message for an unknown user, a wrong password and a locked account", () => {
        ["no_such_user_10197", LOCKOUT_USER, "locked.user"].forEach((userName) => {
            login(userName, userName === "locked.user" ? PASSWORD : "wrong_password");
            cy.url().should("include", "/session/begin");
            cy.get(".alert-danger").invoke("text").invoke("trim").should("eq", GENERIC_MESSAGE);
        });
    });

    it("locks after iMaxFailedLogins wrong passwords and unlocks when the period ends", () => {
        Cypress._.times(maxFailedLogins, () => postLogin(LOCKOUT_USER, "wrong_password"));

        login(LOCKOUT_USER, PASSWORD);
        cy.url().should("include", "/session/begin");
        cy.contains(".alert-danger", GENERIC_MESSAGE);

        cy.expireSignInThrottle("signin.lockout", LOCKOUT_USER_ID);
        login(LOCKOUT_USER, PASSWORD);
        cy.url({ timeout: 10000 }).should("not.include", "/session/begin");
    });

    it("wrong 2FA codes lock the account", () => {
        login(TWOFA_USER, PASSWORD);
        cy.url({ timeout: 10000 }).should("include", "/session/two-factor");

        Cypress._.times(maxFailedLogins, (n) => {
            cy.get("#TwoFACode").type(`${String(n).padStart(6, "0")}{enter}`);
        });
        cy.url().should("include", "/session/begin");

        login(TWOFA_USER, PASSWORD);
        cy.url().should("include", "/session/begin");
        cy.contains(".alert-danger", GENERIC_MESSAGE);

        cy.expireSignInThrottle("signin.lockout", TWOFA_USER_ID);
        login(TWOFA_USER, PASSWORD);
        cy.url({ timeout: 10000 }).should("include", "/session/two-factor");
    });

    it("the reset request route answers an unknown user like a real one", () => {
        const resetRequest = (userName) =>
            cy.request({
                method: "POST",
                url: "/session/forgot-password/reset-request",
                body: { userName },
                failOnStatusCode: false,
            });

        resetRequest("no_such_user_10197").then((unknown) => {
            resetRequest("admin").then((noEmail) => {
                resetRequest(LOCKOUT_USER).then((known) => {
                    expect(unknown.status).to.eq(200);
                    [noEmail, known].forEach((resp) => {
                        expect(resp.status).to.eq(unknown.status);
                        expect(resp.body).to.eq(unknown.body);
                    });
                });
            });
        });
        cy.expireSignInThrottle("password-reset.account");
    });
});
