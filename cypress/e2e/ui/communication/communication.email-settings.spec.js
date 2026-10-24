/// <reference types="cypress" />

describe("Email Settings form", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("admin/email");
    });

    const save = () => cy.get("#email-settings-form").submit();

    it("disables and clears the username and password when SMTP Authentication is off", () => {
        cy.get("#bSMTPAuth").check();
        cy.get("#sSMTPUser").should("not.be.disabled").clear().type("mailer@example.com");
        cy.get("#sSMTPPass").should("not.be.disabled").type("secret-pass");

        cy.get("#bSMTPAuth").uncheck();
        cy.get("#sSMTPUser").should("be.disabled").and("have.value", "");
        cy.get("#sSMTPPass").should("be.disabled").and("have.value", "");

        cy.get("#bSMTPAuth").check();
        cy.get("#sSMTPUser").should("not.be.disabled").and("have.value", "");
    });

    it("saves the credentials with authentication on and wipes them once it is off", () => {
        cy.get("#bSMTPAuth").check();
        cy.get("#sSMTPUser").clear().type("mailer@example.com");
        cy.get("#sSMTPPass").type("secret-pass");
        save();
        cy.contains("Email settings saved successfully", { timeout: 10000 }).should("be.visible");
        cy.get("#sSMTPUser").should("have.value", "mailer@example.com");
        cy.get("#sSMTPPass").should("have.attr", "placeholder").and("include", "Leave blank");

        cy.get("#bSMTPAuth").uncheck();
        save();
        cy.contains("Email settings saved successfully", { timeout: 10000 }).should("be.visible");
        cy.get("#bSMTPAuth").should("not.be.checked");
        cy.get("#sSMTPUser").should("be.disabled").and("have.value", "");
        cy.get("#sSMTPPass").should("be.disabled").and("not.have.attr", "placeholder", "Leave blank to keep existing password");
    });

    it("keeps the saved password when the field is left blank with authentication on", () => {
        cy.get("#bSMTPAuth").check();
        cy.get("#sSMTPUser").clear().type("mailer@example.com");
        cy.get("#sSMTPPass").type("secret-pass");
        save();
        cy.contains("Email settings saved successfully", { timeout: 10000 }).should("be.visible");

        save();
        cy.contains("Email settings saved successfully", { timeout: 10000 }).should("be.visible");
        cy.get("#sSMTPPass").should("have.attr", "placeholder").and("include", "Leave blank");

        cy.get("#bSMTPAuth").uncheck();
        save();
        cy.contains("Email settings saved successfully", { timeout: 10000 }).should("be.visible");
    });

    it("rejects a timeout outside 1 to 600 seconds", () => {
        cy.get("#iSMTPTimeout").clear().type("0");
        save();
        cy.contains("SMTP timeout must be a whole number of seconds between 1 and 600.", { timeout: 10000 }).should("be.visible");
        cy.get("#email-settings-form").should("exist");
    });

    it("requires a username when authentication is on", () => {
        cy.get("#bSMTPAuth").check();
        cy.get("#sSMTPUser").clear();
        save();
        cy.contains("SMTP username is required when authentication is on.", { timeout: 10000 }).should("be.visible");
        cy.get("#bSMTPAuth").uncheck();
    });
});
