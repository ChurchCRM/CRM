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
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");

        // Should end up on the limited access page, not the dashboard
        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");
        cy.contains("Welcome");
    });

    it("Shows Verify Family Info button and Log Out button", () => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");

        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");
        cy.contains("Verify Family Info").should("exist");
        cy.contains("Log Out").should("exist");
    });

    it("Verify Family Info link goes to /external/verify/{token}", () => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");

        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");
        cy.contains("Verify Family Info").click();
        cy.url({ timeout: 10000 }).should("include", "/external/verify/");
        // Verify page should show the family name (Hernandez — person 4, family 2)
        cy.get("body", { timeout: 10000 }).should("contain.text", "Hernandez");
    });

    it("Log Out returns to login page", () => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");

        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");
        cy.contains("Log Out").click();
        cy.url().should("include", "/session/begin");
    });

    it("Direct visit to /v2/dashboard redirects to limited-access", () => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(limitedUser);
        cy.get("input[name=Password]").type(limitedPassword + "{enter}");

        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");

        // Try to access an admin page directly
        cy.visit("/v2/dashboard", { failOnStatusCode: false });
        cy.url().should("include", "/external/limited-access");
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
