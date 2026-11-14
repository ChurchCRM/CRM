/// <reference types="cypress" />

import { ACCOUNT_VIEWPORTS, captureAccountLayout } from "../../support/account-layout";

const ADMIN = 1;
const STANDARD = 3;

describe("Admin view of another user's account (/admin/system/users/{personId})", () => {
    ACCOUNT_VIEWPORTS.forEach(({ width, height }) => {
        ["default", "dark"].forEach((mode) => {
            it(`opens another user's account from the list at ${width}px in ${mode} mode`, () => {
                cy.viewport(width, height);
                cy.setupAdminSession();
                cy.visit("/admin/system/users");
                captureAccountLayout("admin-user-list", mode);
                cy.readEnv("standard.username").then((username) => {
                    cy.get("#user-listing-table_wrapper .dt-search input").type(username);
                    cy.contains("#user-listing-table tbody code", username)
                        .closest("tr")
                        .find('td:first-child a[href*="/admin/system/users/"]')
                        .should("be.visible")
                        .click();
                    cy.get("#tab-account").should("contain", username);
                });
                cy.get('#tab-account a[href$="/account/password"]').should("not.exist");
                cy.get('[data-cy="permission-row"]').should("have.length.greaterThan", 10);
                captureAccountLayout("admin-account", mode);
                cy.get('#settingsNav a[href="#tab-permissions"]').click();
                cy.get('[data-cy="permissions-tab"]').should("be.visible");
                captureAccountLayout("admin-permissions", mode);
            });
        });
    });

    it("sends an administrator who opens their own id to /account", () => {
        cy.setupAdminSession();
        cy.visit(`/admin/system/users/${ADMIN}`);
        cy.location("pathname").should("match", /\/account\/?$/);
    });

    it("links each user in the user list to the admin view", () => {
        cy.setupAdminSession();
        cy.visit("/admin/system/users");
        cy.get(`a[href$="/admin/system/users/${STANDARD}"]`).should("exist");
    });

    it("refuses a non-administrator with 403", () => {
        cy.setupStandardSession();
        cy.request({
            url: `/admin/system/users/${ADMIN}`,
            headers: { Accept: "application/json" },
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 403);
        cy.visit(`/admin/system/users/${ADMIN}`, { failOnStatusCode: false });
        cy.location("pathname").should("include", "/v2/access-denied");
    });
});
