/// <reference types="cypress" />

/**
 * Member Portal — the Directory page.
 *
 * Seed facts: Mathew Campbell (2) is a Member; Lena Black (100) is a Regular Attender.
 * Nathan Lewis (14) is the only listed Member of the Lewis family, born 5 September 1947.
 * The Hart family has seven listed Members.
 *
 * NOTE: every x-api-key request replaces the browser session cookie with an API-token
 * session, so a step that follows one signs in again.
 */
const MEMBER = 2;
const REGULAR_ATTENDER = 100;
const NATHAN_LEWIS = 14;

const DEFAULTS = {
    listed: [1],
    viewers: [1],
    fields: {
        address: true,
        mailingAddress: false,
        homePhone: true,
        personalPhone: true,
        workPhone: true,
        cellPhone: true,
        email: true,
        workEmail: true,
        photo: true,
    },
    birthday: "withoutYear",
    anniversary: "withoutYear",
    browse: true,
};

const adminKey = () => Cypress.testEnv("admin.api.key");

const asAdmin = (method, url, body) =>
    cy.request({
        method,
        url,
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body,
        failOnStatusCode: false,
    });

const saveSettings = (settings) => asAdmin("POST", "/admin/api/member-portal/directory", settings);
const setSwitch = (on) => asAdmin("POST", "/admin/api/system/config/bPortalShowDirectory", { value: on ? "1" : "0" });

const visitAs = (who, path) => {
    cy.useMemberSession(who);
    cy.visit(path, { failOnStatusCode: false });
};

const setHideAge = (personId, hide) => {
    cy.setupAdminSession();
    cy.visit(`/PersonEditor.php?PersonID=${personId}`);
    cy.get("#HideAge")[hide ? "check" : "uncheck"]();
    cy.get("button[name='PersonSubmit']").click();
    cy.location("pathname").should("not.include", "PersonEditor.php");
};

before(() => {
    cy.rememberTestEnv(["admin.api.key"]);
});

describe("Member Portal directory", () => {
    beforeEach(() => {
        saveSettings(DEFAULTS);
        setSwitch(true);
    });

    after(() => {
        saveSettings(DEFAULTS);
        setSwitch(false);
    });

    it("is in the menu of a member allowed to see it, and lists the church", () => {
        visitAs(MEMBER, "/portal/");
        cy.get(".portal-nav a[href$='/portal/directory']").should("contain.text", "Directory").click();

        cy.get(".portal-page-title").should("contain.text", "Directory");
        cy.get("[data-directory-entry='family-1']").should("contain.text", "Campbell, Mathew");
        cy.get("[data-directory-letter='H']").should("exist");
    });

    it("narrows the list to the names typed", () => {
        visitAs(MEMBER, "/portal/directory");
        cy.get("#portal-directory-search").type("hart");
        cy.get("[data-directory-entry]:visible").should("have.length", 1).and("contain.text", "Hart");
        cy.get("#portal-directory-no-match").should("not.be.visible");

        cy.get("#portal-directory-search").clear().type("nobody by this name");
        cy.get("[data-directory-entry]:visible").should("have.length", 0);
        cy.get("#portal-directory-no-match").should("be.visible");
    });

    it("lists nobody until a member searches, when the church allows search only", () => {
        saveSettings({ browse: false });
        visitAs(MEMBER, "/portal/directory");
        cy.get("#portal-directory-prompt").should("be.visible");
        cy.get("[data-directory-entry]").should("not.exist");
        cy.get("[data-directory-letter]").should("not.exist");

        cy.get("#portal-directory-search").type("hart");
        cy.get("#portal-directory-search-button").click();
        cy.location("search").should("eq", "?q=hart");
        cy.get("[data-directory-entry]").should("have.length", 1).and("contain.text", "Hart");

        cy.get("#portal-directory-search").clear().type("nobody by this name{enter}");
        cy.get("[data-directory-entry]").should("not.exist");
        cy.get("#portal-directory-none").should("be.visible");
    });

    it("is not offered to a member whose classification may not see it", () => {
        visitAs(REGULAR_ATTENDER, "/portal/");
        cy.get(".portal-nav a[href$='/portal/directory']").should("not.exist");
        cy.request({ url: "/portal/directory", failOnStatusCode: false }).its("status").should("eq", 403);
    });

    it("disappears while it is switched off", () => {
        setSwitch(false);
        visitAs(MEMBER, "/portal/");
        cy.get(".portal-nav a[href$='/portal/directory']").should("not.exist");
        cy.request({ url: "/portal/directory", failOnStatusCode: false }).its("status").should("eq", 404);
    });

    it("never shows the birth year of someone whose record hides their age", () => {
        saveSettings({ birthday: "withYear" });
        setHideAge(NATHAN_LEWIS, true);

        visitAs(MEMBER, "/portal/directory");
        cy.get(`[data-directory-entry='person-${NATHAN_LEWIS}'] [data-field='birthday']`)
            .should("be.visible")
            .and("not.contain.text", "1947");
        cy.get("[data-directory-entry='family-1'] [data-directory-member='2'] [data-field='birthday']").should("contain.text", "1970");

        setHideAge(NATHAN_LEWIS, false);
    });
});
