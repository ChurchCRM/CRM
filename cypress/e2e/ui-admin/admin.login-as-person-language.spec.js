/// <reference types="cypress" />

// Login as Person (#10463, docs/member-sign-in-design.md §6.5) shows the portal in
// the member's own language, so the administrator sees what the member sees, and
// read-only Login as Person cannot change that language.
const MEMBER = 2; // Mathew Campbell, head of the Campbell family
const SETTINGS = ["bAllowLoginAsPerson", "bLoginAsPersonCanWrite", "bMemberSignIn"];

const admin = (method, url, body = null, status = 200) => cy.makePrivateAdminAPICall(method, url, body, status);

const signInAsAdmin = () =>
    cy.env(["admin.username", "admin.password"]).then((env) => {
        cy.clearCookies();
        cy.request({ method: "POST", url: "/admin/", form: true, body: { User: env["admin.username"], Password: env["admin.password"] } });
    });

const startLoginAsPerson = () =>
    cy.request(`/people/view/${MEMBER}`).then(({ body }) =>
        cy.request({
            method: "POST",
            url: `/admin/api/person/${MEMBER}/login-as-person`,
            headers: { "X-CSRF-Token": body.match(/data-csrf-token="([^"]+)"/)[1] },
            body: {},
        }),
    );

const saveProfile = (body) =>
    cy.request("/portal/profile/edit").then(({ body: page }) =>
        cy.request({
            method: "POST",
            url: "/api/portal/me",
            headers: { "X-CSRF-Token": page.match(/name="csrf_token" value="([^"]+)"/)[1] },
            body,
            failOnStatusCode: false,
        }),
    );

describe("Login as Person: the member's language", () => {
    const saved = {};

    before(() => {
        SETTINGS.forEach((name) => {
            cy.getSystemConfig(name).then((value) => {
                saved[name] = value;
            });
        });
        admin("POST", "admin/api/system/config/bAllowLoginAsPerson", { value: "1" });
        admin("POST", "admin/api/system/config/bLoginAsPersonCanWrite", { value: "0" });
        cy.signInAsMember(MEMBER);
        saveProfile({ language: "fr_FR" }).its("body.profile.language").should("eq", "fr_FR");
    });

    after(() => {
        cy.signInAsMember(MEMBER);
        saveProfile({ language: "" });
        Object.entries(saved).forEach(([name, value]) => cy.restoreSystemConfig(name, value));
    });

    it("opens the portal in the member's language, not the administrator's", () => {
        signInAsAdmin();
        cy.visit("/v2/dashboard");
        cy.window().its("CRM.locale").should("eq", "en_US");

        startLoginAsPerson();
        cy.visit("/portal/profile/edit");
        cy.get("#login-as-person-banner").should("exist");
        cy.get("html").should("have.attr", "lang", "fr-FR");
        cy.get(".portal-nav-list").should("contain", "Accueil");
        cy.get("#portal-language").should("have.value", "fr_FR");
    });

    it("refuses to change the member's language while changes are off", () => {
        signInAsAdmin();
        startLoginAsPerson();
        saveProfile({ language: "de_DE" }).its("status").should("eq", 403);
        cy.request("/api/portal/me").its("body.profile.language").should("eq", "fr_FR");
    });
});
