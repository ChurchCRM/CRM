/// <reference types="cypress" />

describe("Admin Settings page", () => {
    beforeEach(() => cy.setupAdminSession());

    it("links to every settings page and is reachable from the Admin menu and dashboard", () => {
        cy.visit("/admin/");
        cy.get(".page-header .btn-list a[href$='/admin/settings']").should("contain", "Settings");
        cy.get("#sidebar-menu a[href$='/admin/settings']").should("exist");
        cy.get("#scheduledTasksSettings, #quickSearchSettings, .dashboard-feature-toggle").should("not.exist");

        cy.visit("/admin/settings");
        [
            "/admin/system/church-info",
            "/admin/system/localization",
            "/admin/system/feature-toggles",
            "/admin/system/users",
            "/admin/email",
            "/admin/people",
            "/admin/member-portal",
            "/admin/ministry-settings",
            "/admin/system/report-settings",
        ].forEach((path) => cy.get(`#settingsIndex a.card[href$='${path}']`).should("be.visible"));
        cy.get("#quickSearchSettings .settings-panel-fields").should("not.be.disabled");
        cy.get("#scheduledTasksSettings .settings-panel-fields").should("not.be.disabled");
    });
});
