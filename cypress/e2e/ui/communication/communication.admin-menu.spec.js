/// <reference types="cypress" />

const adminEmailLinks = ["admin/email", "admin/email/log", "admin/email/debug"];

describe("Communication admin menu", () => {
    describe("administrator", () => {
        beforeEach(() => cy.setupAdminSession());

        it("lists Email Settings, Email Log and Email Debug once each, under Communication > Admin", () => {
            cy.visit("v2/dashboard");
            adminEmailLinks.forEach((href) => {
                cy.get(`#sidebar-menu a[href$='${href}']`).should("have.length", 1);
            });
        });

        it("shows the email settings inline, without the log", () => {
            cy.visit("admin/email");
            cy.contains("Email Settings").should("be.visible");
            cy.get("#email-settings-form").should("be.visible").within(() => {
                cy.contains("Enable Email");
                cy.contains("SMTP Host");
            });
            cy.get("#email-recent-sends").should("not.exist");
        });

        it("shows the email log on its own page", () => {
            cy.visit("admin/email/log");
            cy.contains("Email Log").should("be.visible");
            cy.get("#email-recent-sends").should("exist");
            cy.get("#email-settings-form").should("not.exist");
        });

        it("opens Email Debug from the Email Settings page", () => {
            cy.visit("admin/email");
            cy.contains("a", "Email Debug").click();
            cy.location("pathname").should("match", /\/admin\/email\/debug\/?$/);
            cy.contains("SMTP Configuration");
        });

        it("links Mailchimp settings to the plugin management page", () => {
            cy.visit("admin/email");
            cy.contains("a", "Plugin Settings").click();
            cy.location("pathname").should("match", /\/plugins\/management\/?$/);
            cy.location("hash").should("eq", "#plugin-mailchimp");
        });
    });

    describe("standard user", () => {
        beforeEach(() => cy.setupStandardSession());

        it("does not list the admin email pages", () => {
            cy.visit("v2/dashboard");
            adminEmailLinks.forEach((href) => {
                cy.get(`#sidebar-menu a[href$='${href}']`).should("not.exist");
            });
        });
    });
});
