/// <reference types="cypress" />

// These are full browser/session checks: do not use setupAdminSession / setupStandardSession
// here, because the shared login helper intentionally dismisses notifications.
const login = (role) => {
    cy.readEnv(`${role}.username`).then((username) => {
        cy.readEnv(`${role}.password`).then((password) => {
            cy.visit("/session/begin");
            cy.get("input[name=User]").type(username);
            cy.get("input[name=Password]").type(password + "{enter}");
            cy.expectStaffSignedIn();
        });
    });
};

describe("System update notice for all staff", () => {
    it("shows admins the upgrade destination and latest-version support policy", () => {
        login("admin");
        cy.visit("/v2/dashboard");
        cy.get('[data-notification-id="system-update-available"]').within(() => {
            cy.contains("System Update Available");
            cy.contains("Security updates and support are provided for the latest version only.");
            cy.get('a[href$="/admin/system/upgrade"]').should("exist");
        });
    });

    it("lets staff read each newer release without upgrade controls", () => {
        login("standard");
        cy.visit("/v2/dashboard");
        cy.get('[data-notification-id="system-update-available"]').within(() => {
            cy.contains("ask your administrator");
            cy.contains("Security updates and support are provided for the latest version only.");
            cy.get('a[href$="/v2/releases"]').click();
        });
        cy.location("pathname").should("include", "/v2/releases");
        cy.get("#releases-page").should("be.visible");
        cy.get('a[href$="/admin/system/upgrade"]').should("not.exist");
        cy.get("#releases-list .version-notes-block").each(($release) => {
            cy.wrap($release).find(".release-notes").should("not.be.empty");
            cy.wrap($release).find("a").should("have.attr", "href").and("include", "changelog/");
        });
    });

    it("dismisses for only the current login session", () => {
        login("standard");
        cy.visit("/v2/dashboard");
        cy.get('[data-notification-id="system-update-available"] .js-dismiss-notification').click();
        cy.get('[data-notification-id="system-update-available"]').should("not.exist");
        cy.reload();
        cy.get('[data-notification-id="system-update-available"]').should("not.exist");
        cy.visit("/session/end");
        login("standard");
        cy.visit("/v2/dashboard");
        cy.get('[data-notification-id="system-update-available"]').should("be.visible");
    });
});
