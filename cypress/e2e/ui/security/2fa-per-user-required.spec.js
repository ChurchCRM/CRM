/// <reference types="cypress" />

import { absoluteUrl, basePath } from "../../../support/urls";

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
        cy.url({ timeout: 15000 }).should("not.include", "/account/2fa");
        cy.get("#two-fa-grace-banner", { timeout: 10000 }).should("exist");
    });

    it("sends a required user straight to enrollment when there is no grace period", () => {
        setGraceDays(0);
        setRequired(true);

        signInAsStandardUser();
        cy.url({ timeout: 15000 }).should("include", "/account/2fa");

        cy.visit(`/v2/dashboard?next=${encodeURIComponent("/account/2fa")}`);
        cy.location("pathname").should("eq", `${basePath()}/account/2fa`);
    });

    it("refuses other API calls until an overdue user enrolls, but keeps enrollment working", () => {
        setGraceDays(0);
        setRequired(true);

        signInAsStandardUser();
        cy.url({ timeout: 15000 }).should("include", "/account/2fa");

        cy.request({ url: absoluteUrl("/api/cart/"), failOnStatusCode: false }).its("status").should("eq", 403);
        cy.request(absoluteUrl("/api/user/current/2fa-status")).its("status").should("eq", 200);
    });

    it("lets an in-grace user call the API", () => {
        setGraceDays(7);
        setRequired(true);

        signInAsStandardUser();
        cy.get("#two-fa-grace-banner", { timeout: 10000 }).should("exist");
        cy.request(absoluteUrl("/api/cart/")).its("status").should("eq", 200);
    });

    it("stops asking once the requirement is removed", () => {
        setGraceDays(0);
        setRequired(true);
        setRequired(false).its("body.required").should("eq", false);

        signInAsStandardUser();
        cy.url({ timeout: 15000 }).should("not.include", "/account/2fa");
        cy.get("#two-fa-grace-banner").should("not.exist");
    });

    it("lets an administrator require 2FA from the Users page and then offers to remove it", () => {
        cy.setupAdminSession({ forceLogin: true });
        cy.visit("/admin/system/users");
        cy.window().then((win) => win.$("#user-listing-table").DataTable().search(Cypress.testEnv("standard.username")).draw());
        cy.intercept("POST", "**/setTwoFactorRequired").as("setRequired");
        cy.get(`.js-set-2fa-required[data-user_id="${USER_ID}"]`).should("have.attr", "data-required", "true").click({ force: true });
        cy.wait("@setRequired").its("response.statusCode").should("eq", 200);

        cy.visit("/admin/system/users");
        cy.window().then((win) => win.$("#user-listing-table").DataTable().search(Cypress.testEnv("standard.username")).draw());
        cy.get(`.js-set-2fa-required[data-user_id="${USER_ID}"]`)
            .should("have.attr", "data-required", "false")
            .closest("tr")
            .find("[data-cy=two-factor-required-badge]")
            .should("exist");
    });

    it("refuses a session request without the CSRF token", () => {
        cy.setupAdminSession({ forceLogin: true });
        cy.request({
            method: "POST",
            url: absoluteUrl(`/admin/api/user/${USER_ID}/setTwoFactorRequired`),
            body: { required: true },
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 403);
    });
});
