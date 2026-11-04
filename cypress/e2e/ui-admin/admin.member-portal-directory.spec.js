/// <reference types="cypress" />

/**
 * Admin → Member Portal → Directory: who is listed, who can see it, and what it shows.
 * Every change saves as it is made.
 */
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

const asAdmin = (method, body) =>
    cy.request({
        method,
        url: "/admin/api/member-portal/directory",
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body,
        failOnStatusCode: false,
    });

const openDirectoryTab = () => {
    cy.setupAdminSession();
    cy.intercept("POST", "**/admin/api/member-portal/directory").as("saveDirectory");
    cy.visit("/admin/member-portal");
    cy.get("#portal-directory-tab").should("contain.text", "Directory").click();
    cy.get("#portal-directory").should("be.visible");
};

before(() => {
    cy.rememberTestEnv(["admin.api.key"]);
});

describe("Admin → Member Portal → Directory", () => {
    beforeEach(() => {
        asAdmin("POST", DEFAULTS);
    });

    after(() => {
        asAdmin("POST", DEFAULTS);
    });

    it("starts with Members listed and seeing it, dates without the year", () => {
        openDirectoryTab();
        cy.get("#portalDirectory-listed-1").should("be.checked");
        cy.get("#portalDirectory-viewers-1").should("be.checked");
        cy.get("#portalDirectory-viewers-2").should("not.be.checked");
        cy.get("#portalDirectory-field-mailingAddress").should("not.be.checked");
        cy.get("#portalDirectory-field-email").should("be.checked");
        cy.get("#portalDirectory-birthday").should("have.value", "withoutYear");
        cy.get("#portalDirectory-anniversary").should("have.value", "withoutYear");
        cy.get("#portalDirectory-browse-1").should("be.checked");
        cy.get("#portalDirectorySectionPanel").should("contain.text", "Show the member directory");
    });

    it("saves each change as it is made", () => {
        openDirectoryTab();

        cy.get("#portalDirectory-viewers-2").check();
        cy.wait("@saveDirectory").its("response.statusCode").should("eq", 200);
        cy.get("#portalDirectory-field-email").uncheck();
        cy.wait("@saveDirectory").its("response.statusCode").should("eq", 200);
        cy.get("#portalDirectory-birthday").select("hidden");
        cy.wait("@saveDirectory").its("response.statusCode").should("eq", 200);
        cy.get("#portalDirectory-browse-0").check();
        cy.wait("@saveDirectory").its("response.statusCode").should("eq", 200);
        cy.contains("Settings saved successfully").should("be.visible");

        asAdmin("GET").its("body.settings").should("deep.include", {
            viewers: [1, 2],
            birthday: "hidden",
            browse: false,
        });
        asAdmin("GET").its("body.settings.fields.email").should("eq", false);

        openDirectoryTab();
        cy.get("#portalDirectory-viewers-2").should("be.checked");
        cy.get("#portalDirectory-field-email").should("not.be.checked");
        cy.get("#portalDirectory-birthday").should("have.value", "hidden");
        cy.get("#portalDirectory-browse-0").should("be.checked");
    });
});
