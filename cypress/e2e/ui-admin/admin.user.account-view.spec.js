/// <reference types="cypress" />

const ADMIN = 1;
const STANDARD = 3;

describe("Admin view of another user's account (/admin/system/users/{personId})", () => {
    it("shows that user's account to an administrator", () => {
        cy.setupAdminSession();
        cy.visit(`/admin/system/users/${STANDARD}`);
        cy.readEnv("standard.username").then((username) => {
            cy.get("#tab-account").should("contain", username);
        });
        cy.get('#tab-account a[href$="/account/password"]').should("not.exist");
        cy.get('[data-cy="permission-row"]').should("have.length.greaterThan", 10);
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
