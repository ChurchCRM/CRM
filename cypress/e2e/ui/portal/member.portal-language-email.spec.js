/// <reference types="cypress" />

import {
    askForEmail,
    postCode,
    resetEmailSignIn,
    restoreSettings,
    signInEmail,
    useMemberSignIn,
    waitForMail,
} from "../../../support/member-sign-in";

/**
 * Member sign-in (#10463): mail to a member is written in the language they
 * chose for the portal; the office notice is written in the church's.
 * Seed people: Constance Hart (6), head of household, signs in; Darren Campbell
 * (4), an "Other Relative" the member rule refuses; Amanda Black (99) is the
 * office list.
 */

const member = { personId: 6, email: "constance.hart@example.com" };
const refused = { personId: 4, email: "darren.freeman@example.com" };
const office = { personId: 99, email: "amanda.black@example.com" };

const adminKey = () => Cypress.testEnv("admin.api.key");

// An x-api-key request replaces the browser's session cookie, so it runs before a sign-in.
const setAdminLanguage = (value) =>
    cy.request({
        method: "POST",
        url: "/api/user/1/setting/ui.locale",
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { value },
    });

const signInAsAdmin = () =>
    cy.env(["admin.username", "admin.password"]).then((env) => {
        cy.clearCookies();
        cy.request({ method: "POST", url: "/admin/", form: true, body: { User: env["admin.username"], Password: env["admin.password"] } });
    });

const signInWithCode = () => {
    cy.clearCookies();
    resetEmailSignIn([member.personId]);
    askForEmail(member.email);
    signInEmail(member.email).then(({ code }) => postCode(code));
};

const saveProfile = (body) =>
    cy.request("/portal/profile/edit").then(({ body: page }) =>
        cy.request({
            method: "POST",
            url: "/api/portal/me",
            headers: { "X-CSRF-Token": page.match(/name="csrf_token" value="([^"]+)"/)[1] },
            body,
        }),
    );

const newestMailTo = (address, subject = "") =>
    waitForMail(address).then((messages) => {
        const message = messages.find((candidate) => String(candidate.Subject).includes(subject));
        expect(message, `email to ${address}`).to.exist;
        return cy.task("mail:get", { id: message.ID }).then(({ body }) => `${body.Text || ""}`);
    });

before(() => {
    cy.rememberTestEnv(["admin.api.key"]);
});

describe("Member Portal mail in the member's language", () => {
    const saved = {};

    before(() => {
        cy.task("mail:available").its("available").should("eq", true);
        useMemberSignIn(saved, { sMemberSignInNotificationRecipientIDs: String(office.personId) });
        signInWithCode();
        saveProfile({ language: "fr_FR" }).its("body.profile.language").should("eq", "fr_FR");
    });

    beforeEach(() => {
        cy.expireSignInThrottle("email-change.person");
    });

    after(() => {
        signInWithCode();
        saveProfile({ language: "" });
        setAdminLanguage("en_US");
        restoreSettings(saved);
    });

    it("sends the sign-in code in the member's language to a signed-out browser", () => {
        cy.clearCookies();
        resetEmailSignIn([member.personId]);
        askForEmail(member.email).its("body").should("include", 'lang="en-US"');

        newestMailTo(member.email).then((text) => {
            expect(text).to.include("Se connecter");
            expect(text).to.include("Si cela ne fonctionne pas");
            expect(text).to.not.include("If that doesn't work");
        });
    });

    it("sends the email change confirmation in the member's language", () => {
        signInWithCode();
        const address = `constance.${Date.now()}@example.com`;
        saveProfile({ email: address }).its("body.pendingEmail").should("eq", address);

        newestMailTo(address).then((text) => {
            expect(text).to.include("Si cela ne fonctionne pas");
        });
    });

    it("writes the office notice in the church's language, whoever asks", () => {
        setAdminLanguage("fr_FR");
        signInAsAdmin();
        resetEmailSignIn([refused.personId]);
        askForEmail(refused.email).its("body").should("include", 'lang="fr-FR"');

        newestMailTo(office.email).then((text) => {
            expect(text).to.include("Someone asked for a Member Portal sign-in email");
            expect(text).to.include("View Person Page");
            expect(text).to.include("If that doesn't work");
            expect(text).to.not.include("Voir Page de la personne");
        });
    });
});
