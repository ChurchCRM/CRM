/// <reference types="cypress" />

/**
 * Tests for a zero-permission user (all flags = 0, including usr_EditSelf=0).
 *
 * Seed data: user "noperm.user" (person ID 901, family 1 — Campbell) has every
 * permission flag set to 0: AddRecords=0, EditRecords=0, DeleteRecords=0,
 * MenuOptions=0, ManageGroups=0, Finance=0, Notes=0, Admin=0, EditSelf=0.
 * Password: "changeme".
 *
 * Business rule under test (read-default policy, #9003):
 *  - A zero-permission user CAN log in and READ people and family records.
 *    They are NOT redirected to /external/limited-access — that flow is now
 *    reserved for EditSelf-only users (see limited-access.spec.js).
 *  - They CANNOT write anything: no add/edit/delete of person or family, and no
 *    access to finance, notes, admin, or menu-option pages.
 *
 * The entry gate in PageInit.php and AuthMiddleware.php is now
 * User::isEditSelfExclusive(), replacing the removed hasNoAdminPermissions().
 * Zero-permission users fall through it; EditSelf-only users do not.
 *
 * Contrast: limited.user (EditSelf=1, person 4, family 1) IS confined to
 * /external/limited-access and gets the "Verify Family Info" button — see
 * limited-access.spec.js.
 */
describe("Zero-Permission User (EditSelf=0, all flags 0)", () => {
    const noPermUser = "noperm.user";
    const noPermPassword = "changeme";
    const noPermApiKey = "noPermUserApiKeyForTesting123456789012345678";

    const login = () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(noPermUser);
        cy.get("input[name=Password]").type(noPermPassword + "{enter}");
        cy.url({ timeout: 10000 }).should("not.include", "/session/begin");
    };

    describe("Can log in and read people/families", () => {
        beforeEach(login);

        it("Login lands in the CRM, NOT on limited-access", () => {
            cy.url().should("not.include", "/external/limited-access");
        });

        it("Can view the main dashboard", () => {
            cy.visit("v2/dashboard");
            cy.url().should("include", "/v2/dashboard");
            cy.url().should("not.include", "/external/limited-access");
        });

        it("Can view the people dashboard", () => {
            cy.visit("people/dashboard");
            cy.url().should("include", "/people/dashboard");
            cy.url().should("not.include", "/external/limited-access");
        });

        it("Can view the person listing", () => {
            cy.visit("people/list");
            cy.url().should("include", "/people/list");
            cy.url().should("not.include", "/v2/access-denied");
        });

        it("Can view the family listing", () => {
            cy.visit("people/family");
            cy.url().should("include", "/people/family");
            cy.url().should("not.include", "/v2/access-denied");
        });

        it("Session-based person API read succeeds", () => {
            cy.request({
                method: "GET",
                url: "/api/person/2",
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(200);
            });
        });
    });

    describe("Cannot write or reach privileged modules", () => {
        beforeEach(login);

        // Write pages are gated on AddRecords / EditRecords, which are 0.
        // PersonEditor.php redirects denied users to the dashboard (not access-denied);
        // FamilyEditor.php uses securityRedirect → access-denied (tested separately below).
        it("PersonEditor (add/edit person) is denied", () => {
            cy.visit("PersonEditor.php?PersonID=2", { failOnStatusCode: false });
            cy.url().should("include", "/v2/dashboard");
        });

        it("FamilyEditor (add/edit family) is denied", () => {
            cy.visit("FamilyEditor.php?FamilyID=1", { failOnStatusCode: false });
            cy.url().should("include", "/v2/access-denied");
        });

        // MenuOptions=0 — pages newly guarded alongside the gate change.
        it("LettersAndLabels is denied (MenuOptions)", () => {
            cy.visit("LettersAndLabels.php", { failOnStatusCode: false });
            cy.url().should("include", "/v2/access-denied");
        });

        it("WhyCameEditor is denied (MenuOptions)", () => {
            cy.visit("WhyCameEditor.php?PersonID=2", { failOnStatusCode: false });
            cy.url().should("include", "/v2/access-denied");
        });

        // Finance=0 — the finance module stays closed.
        it("Finance dashboard is denied", () => {
            cy.visit("finance/", { failOnStatusCode: false });
            cy.url().should("include", "/v2/access-denied");
        });

        // Notes=0 — note routes are wrapped in NotesRoleAuthMiddleware.
        it("Note API is blocked with 403", () => {
            cy.request({
                method: "GET",
                url: "/api/person/2/notes",
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(403);
            });
        });
    });

    describe("API-key access", () => {
        it("Person read via API key succeeds", () => {
            cy.apiRequest({
                method: "GET",
                url: "/api/person/1",
                headers: { "x-api-key": noPermApiKey },
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(200);
            });
        });

        it("Note read via API key is blocked with 403", () => {
            cy.apiRequest({
                method: "GET",
                url: "/api/person/1/notes",
                headers: { "x-api-key": noPermApiKey },
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(403);
            });
        });
    });

    describe("Menu reflects the zero-permission state", () => {
        beforeEach(login);

        it("Shows People, hides write actions", () => {
            cy.visit("v2/dashboard");
            // Read-default: the People menu is available.
            cy.contains("People").should("exist");
            // No write actions.
            cy.contains("a", "Add New Person").should("not.exist");
            cy.contains("a", "Add New Family").should("not.exist");
        });
    });

    describe("Log out", () => {
        beforeEach(login);

        it("Log Out returns to the login page", () => {
            cy.visit("v2/dashboard");
            // Open the user-menu dropdown, then click the 'Sign out' link
            cy.get('[aria-label="Open user menu"]').click();
            cy.contains("Sign out").click();
            cy.url().should("include", "/session/begin");
        });
    });
});
