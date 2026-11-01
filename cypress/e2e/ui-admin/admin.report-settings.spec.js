/// <reference types="cypress" />

describe("Admin - Report Settings page", () => {
    it("shows the letterhead card and opens the shared uploader", () => {
        cy.setupAdminSession();
        cy.visit("/admin/system/report-settings");
        cy.contains("Report Settings").should("be.visible");
        cy.get("#church-letterhead-card").should("be.visible");
        cy.window().its("CRM.photoUploader", { timeout: 10000 }).should("exist");

        cy.get("#church-letterhead-upload-btn").click();
        cy.get(".uppy-Dashboard--modal", { timeout: 10000 }).should("be.visible");
    });

    it("sends a user without the admin role to access denied", () => {
        cy.setupPersonaSession("finance-only");
        cy.visit("/admin/system/report-settings", { failOnStatusCode: false });
        cy.url().should("include", "/v2/access-denied");
    });
});
