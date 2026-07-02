/// <reference types="cypress" />

/**
 * Tests for a zero-permission user (all flags = 0, including usr_EditSelf=0).
 *
 * Seed data: user "noperm.user" (person ID 901, family 1 — Campbell) has
 * every permission flag set to 0: AddRecords=0, EditRecords=0, DeleteRecords=0,
 * MenuOptions=0, ManageGroups=0, Finance=0, Notes=0, Admin=0, EditSelf=0.
 * Password: "changeme".
 *
 * Business rule under test:
 *  - Self-edit is NOT a default right; usr_EditSelf must be explicitly 1.
 *  - A user with all flags 0 can log in but gains no CRM access:
 *      * Redirected to /external/limited-access (GHSA-5w59-32c8-933v gate)
 *      * Must NOT see "Verify Family Info" button even though they belong to
 *        a family (fixes GitHub issue #9079)
 *      * All internal CRM pages redirect to limited-access
 *      * Internal API calls return 403
 *
 * Contrast: limited.user (EditSelf=1, person 4, family 1) DOES get the
 * "Verify Family Info" button — see limited-access.spec.js.
 */
describe("Zero-Permission User (EditSelf=0, all flags 0)", () => {
    const noPermUser = "noperm.user";
    const noPermPassword = "changeme";
    const noPermApiKey = "noPermUserApiKeyForTesting123456789012345678";

    it("Login redirects to /external/limited-access", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(noPermUser);
        cy.get("input[name=Password]").type(noPermPassword + "{enter}");

        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");
        cy.contains("Welcome");
    });

    it("Limited-access page does NOT show 'Verify Family Info' (fix #9079)", () => {
        // Person 901 belongs to family 1 (Campbell). Without the fix, a user with
        // EditSelf=0 who has a family would incorrectly receive a verify token and
        // see this button. After the fix, isEditSelfEnabled()=false suppresses it.
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(noPermUser);
        cy.get("input[name=Password]").type(noPermPassword + "{enter}");

        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");
        cy.contains("Verify Family Info").should("not.exist");
        cy.contains("Log Out").should("exist");
    });

    it("Log Out returns to login page", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(noPermUser);
        cy.get("input[name=Password]").type(noPermPassword + "{enter}");

        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");
        cy.contains("Log Out").click();
        cy.url().should("include", "/session/begin");
    });

    it("Direct visit to /v2/dashboard redirects to limited-access", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(noPermUser);
        cy.get("input[name=Password]").type(noPermPassword + "{enter}");

        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");

        cy.visit("v2/dashboard", { failOnStatusCode: false });
        cy.url().should("include", "/external/limited-access");
    });

    it("Direct visit to /people/dashboard redirects to limited-access", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(noPermUser);
        cy.get("input[name=Password]").type(noPermPassword + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");

        cy.visit("people/dashboard", { failOnStatusCode: false });
        cy.url().should("include", "/external/limited-access");
    });

    it("Direct visit to PersonEditor (edit attempt) redirects to limited-access", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(noPermUser);
        cy.get("input[name=Password]").type(noPermPassword + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");

        cy.visit("PersonEditor.php?PersonID=2", { failOnStatusCode: false });
        cy.url().should("include", "/external/limited-access");
    });

    it("Direct visit to FamilyEditor (edit attempt) redirects to limited-access", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(noPermUser);
        cy.get("input[name=Password]").type(noPermPassword + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");

        cy.visit("FamilyEditor.php?FamilyID=1", { failOnStatusCode: false });
        cy.url().should("include", "/external/limited-access");
    });

    it("Session-based internal API call is blocked with 403", () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(noPermUser);
        cy.get("input[name=Password]").type(noPermPassword + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/external/limited-access");

        cy.request({
            method: "GET",
            url: "/api/person/2",
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(403);
        });
    });

    it("API call with zero-permission user key returns 403", () => {
        cy.apiRequest({
            method: "GET",
            url: "/api/person/1",
            headers: {
                "x-api-key": noPermApiKey,
            },
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(403);
        });
    });
});
