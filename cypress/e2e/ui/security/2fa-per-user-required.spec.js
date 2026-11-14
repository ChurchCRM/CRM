/// <reference types="cypress" />

/**
 * Per-user "Require 2FA" (#9690): with bRequire2FA off, an administrator can
 * require 2FA for one login, which then gets the same grace banner and
 * enrollment redirect as the system-wide mandate.
 */
describe("2FA required for one user", () => {
    const USER_ID = 3; // tony.wade, the standard user

    const setRequired = (required, expectedStatus = 200) =>
        cy.makePrivateAdminAPICall("POST", `/admin/api/user/${USER_ID}/setTwoFactorRequired`, { required }, expectedStatus);
    const setGraceDays = (days) =>
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/i2FAGracePeriodDays", { value: String(days) }, 200);

    const signInAsStandardUser = () => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(Cypress.testEnv("standard.username"));
        cy.get("input[name=Password]").type(`${Cypress.testEnv("standard.password")}{enter}`);
    };

    before(() => {
        cy.rememberTestEnv(["standard.username", "standard.password"]);
    });

    beforeEach(() => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/bRequire2FA", { value: "0" }, 200);
        cy.makePrivateAdminAPICall("POST", `/admin/api/user/${USER_ID}/disableTwoFactor`, null, 200);
    });

    afterEach(() => {
        setRequired(false);
        setGraceDays(7);
    });

    it("refuses a request that doesn't say true or false", () => {
        cy.makePrivateAdminAPICall("POST", `/admin/api/user/${USER_ID}/setTwoFactorRequired`, { required: "yes" }, 400);
    });

    it("shows the grace banner to a required user who has not enrolled", () => {
        setGraceDays(7);
        setRequired(true).its("body.required").should("eq", true);

        signInAsStandardUser();
        cy.url({ timeout: 15000 }).should("not.include", "manage2fa");
        cy.get("#two-fa-grace-banner", { timeout: 10000 }).should("exist");
    });

    it("sends a required user straight to enrollment when there is no grace period", () => {
        setGraceDays(0);
        setRequired(true);

        signInAsStandardUser();
        cy.url({ timeout: 15000 }).should("include", "manage2fa");
    });

    it("stops asking once the requirement is removed", () => {
        setGraceDays(0);
        setRequired(true);
        setRequired(false).its("body.required").should("eq", false);

        signInAsStandardUser();
        cy.url({ timeout: 15000 }).should("not.include", "manage2fa");
        cy.get("#two-fa-grace-banner").should("not.exist");
    });

    it("marks the user Required on the Users page and offers to remove it", () => {
        setRequired(true);

        cy.setupAdminSession({ forceLogin: true });
        cy.visit("admin/system/users");
        cy.window().then((win) => win.$("#user-listing-table").DataTable().search(Cypress.testEnv("standard.username")).draw());
        cy.get(`.js-set-2fa-required[data-user_id="${USER_ID}"]`)
            .should("have.attr", "data-required", "false")
            .closest("tr")
            .find("[data-cy=two-factor-required-badge]")
            .should("exist");
    });
});
