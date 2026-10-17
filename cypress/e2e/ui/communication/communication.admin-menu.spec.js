/// <reference types="cypress" />

const adminEmailLinks = ["admin/email", "admin/email/log", "admin/email/debug"];

describe("Communication admin menu", () => {
    it("lists Email Settings, Email Log and Email Debug once each for an administrator", () => {
        cy.setupAdminSession();
        cy.visit("v2/dashboard");
        adminEmailLinks.forEach((href) => {
            cy.get(`#sidebar-menu a[href$='${href}']`).should("have.length", 1);
        });
    });

    it("hides the admin email pages from a standard user", () => {
        cy.setupStandardSession();
        cy.visit("v2/dashboard");
        adminEmailLinks.forEach((href) => {
            cy.get(`#sidebar-menu a[href$='${href}']`).should("not.exist");
        });
    });
});
