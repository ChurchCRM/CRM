/// <reference types="cypress" />

/**
 * Tests for limited-access users (EditSelf only, no admin permissions).
 *
 * Seed data: user "limited.user" (person ID 4, family 2) has
 * usr_EditSelf=1 and all other permissions=0.
 * Password: "changeme" (same as admin).
 */
describe("Limited Access User", () => {
    const limitedUser = "limited.user";
    const limitedPassword = "changeme";

    it("Login redirects to /external/limited-access", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");

        // Should end up on the limited access page, not the dashboard
        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");
        cy.contains("Welcome");
    });

    it("Shows Verify Family Info button and Log Out button", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");

        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");
        cy.contains("Verify Family Info").should("exist");
        cy.contains("Log Out").should("exist");
    });

    it("Verify Family Info link goes to /external/verify/{token}", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");

        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");
        cy.contains("Verify Family Info").click();
        cy.url({ timeout: 10000 }).should("include", "/external/verify/");
        // Verify page should show the family name (Campbell — person 4, family 1)
        cy.get("body", { timeout: 10000 }).should("contain.text", "Campbell");
    });

    it("Log Out returns to login page", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");

        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");
        cy.contains("Log Out").click();
        cy.url().should("include", "/session/begin");
    });

    it("Direct visit to /v2/dashboard redirects to limited-access", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");

        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");

        // Try to access an admin page directly
        cy.visit("v2/dashboard", { failOnStatusCode: false });
        cy.url().should("include", "/external/limited-access");
    });

    it("Direct visit to other internal MVC apps also redirects to limited-access", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");

        // The "external pages only" guarantee must hold across every internal
        // MVC app, not just /v2 — each is gated by AuthMiddleware via MvcAppFactory.
        cy.visit("people/dashboard", { failOnStatusCode: false });
        cy.url().should("include", "/external/limited-access");
    });

    it("Session-based internal API call is blocked with 403", () => {
        // Complements the api-key 403 test: a logged-in browser SESSION for a
        // limited user must also be rejected from internal APIs (AuthMiddleware
        // hasNoAdminPermissions gate), so they can't pivot via the cookie.
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");

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

/**
 * EditSelf-only user via the seeded `amanda.black` account (user 99, family 20,
 * EditSelf=1 only, password "onlyMyFamily"). Mirrors the limited.user lockout —
 * an EditSelf-only user must be confined to external pages and blocked from
 * internal pages AND internal APIs, including her OWN family.
 */
describe("EditSelf-only user (amanda.black)", () => {
    const editSelfUser = "amanda.black@example.com";
    const editSelfPassword = "onlyMyFamily";

    function loginAsEditSelf() {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(editSelfUser);
        cy.get("input[name=Password]").type(editSelfPassword + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");
    }

    it("Login redirects to /external/limited-access (not the dashboard)", () => {
        loginAsEditSelf();
        cy.contains("Welcome");
    });

    it("Shows the self-service Verify Family Info link to her own family", () => {
        loginAsEditSelf();
        cy.contains("Verify Family Info").should("exist");
        cy.contains("Log Out").should("exist");
        cy.contains("Verify Family Info").click();
        cy.url({ timeout: 10000 }).should("include", "/external/verify/");
        // Verify page is scoped to amanda's own family (Black, family 20)
        cy.get("body", { timeout: 10000 }).should("contain.text", "Black");
    });

    it("Direct visit to an internal page redirects to limited-access", () => {
        loginAsEditSelf();
        cy.visit("v2/dashboard", { failOnStatusCode: false });
        cy.url().should("include", "/external/limited-access");
    });

    it("Session-based internal API call to her OWN family is still blocked (403)", () => {
        loginAsEditSelf();
        // EditSelf grants no internal API access — even own family 20 must 403.
        cy.request({
            method: "GET",
            url: "/api/family/20",
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(403);
        });
    });
});
