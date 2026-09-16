/// <reference types="cypress" />

/**
 * Tests for limited-access users (EditSelf only, no admin permissions).
 *
 * Seed data: user "limited.user" (person ID 4, family 1 — Campbell) has
 * usr_EditSelf=1 and all other permissions=0.
 * Password: "changeme" (same as admin).
 *
 * The exclusive-permission invariant (EditSelf=1 → all module perms = 0) is
 * enforced at two levels:
 *   1. PHP model: User::isEditSelfExclusive() suppresses module perms at runtime
 *   2. DB migration: src/mysql/upgrade/7.4.2-editself-exclusive.sql clears any
 *      orphaned module permissions left from pre-PR-9016 installations.
 *
 * Since the Member Portal landed (#9863) the destination of that confinement is
 * `/portal`, not the interim `/external/limited-access` page: a self-service
 * login lands in the portal, is bounced back to it from every admin URL, and
 * `/external/limited-access` is a 302 to `/portal` for old links. What the
 * portal itself renders is covered by
 * cypress/e2e/ui/portal/member.portal-landing.spec.js.
 */
describe("Self-only access — EditSelf account user (limited.user)", () => {
    const limitedUser = "limited.user";
    const limitedPassword = "changeme";

    const login = () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/portal");
    };

    it("Login redirects to /portal", () => {
        login();
        cy.url().should("not.include", "/external/limited-access");
        cy.url().should("not.include", "/v2/dashboard");
    });

    it("Shows the portal home, not the admin shell", () => {
        login();
        cy.get(".portal-home").should("exist");
        cy.get("#sidebar").should("not.exist");
    });

    it("Sign out returns to the login page", () => {
        login();
        cy.contains("Sign out").click();
        cy.url({ timeout: 10000 }).should("include", "/session/begin");
    });

    it("The old /external/limited-access URL redirects to /portal", () => {
        login();
        cy.visit("external/limited-access", { failOnStatusCode: false });
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.url().should("not.include", "/external/limited-access");
    });

    it("Direct visit to /v2/dashboard redirects to /portal", () => {
        login();

        // Try to access an admin page directly
        cy.visit("v2/dashboard", { failOnStatusCode: false });
        cy.url().should("include", "/portal");
    });

    it("Direct visit to other internal MVC apps also redirects to /portal", () => {
        login();

        // The "portal only" guarantee must hold across every internal MVC app,
        // not just /v2 — each is gated by AuthMiddleware via MvcAppFactory.
        cy.visit("people/dashboard", { failOnStatusCode: false });
        cy.url().should("include", "/portal");
    });

    it("Session-based internal API call is blocked with 403", () => {
        // Complements the api-key 403 test: a logged-in browser SESSION for a
        // limited user must also be rejected from internal APIs (AuthMiddleware
        // User::isEditSelfExclusive() gate), so they can't pivot via the cookie.
        login();

        cy.request({
            method: "GET",
            url: "/api/person/2",
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(403);
        });
    });

    it("API call with limited user key returns 403", () => {
        cy.apiRequest({
            method: "GET",
            url: "/api/person/1",
            headers: {
                "x-api-key": "limitedUserApiKeyForTesting123456789012345678",
            },
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(403);
        });
    });
});
