/// <reference types="cypress" />

/**
 * The safety net for a self-service account (usr_EditSelf=1, not an
 * administrator) that the 7.9.0 upgrade did not remove (member sign-in design
 * §12). The upgrade deletes every such account and nothing creates one any more,
 * so the account is a fixture written in SQL. The confinement it relies on,
 * AuthMiddleware::isLimitedAccessAllowedPath() and the PageInit.php redirect,
 * stays for one more release: such an account reaches only the Member Portal,
 * and no internal API, by session or by API key (GHSA-jjcj-h3cm-p7x7).
 */
const PERSON_ID = 4; // Darren Campbell, family 1
const USERNAME = "safety.net.editself";
const PASSWORD = "changeme";
const API_KEY = "safetyNetEditSelfApiKeyForTesting1234567890";
const SHARED_CHANGEME_HASH = "$2y$12$e3o8rmvWUYdgzUNB/AAMK.pRvT9rwsIZx4wYB0brOmVPB1UL.HA5S";

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const removeAccount = () => db("DELETE FROM user_usr WHERE usr_per_ID = ?", [PERSON_ID]);

describe("Safety net — a self-service account the upgrade left behind", () => {
    before(() => {
        removeAccount();
        db(
            `INSERT INTO user_usr (usr_per_ID, usr_Password, usr_NeedPasswordChange, usr_UserName, usr_apiKey, usr_EditSelf, usr_Admin, usr_Notes)
             VALUES (?, ?, 0, ?, ?, 1, 0, 1)`,
            [PERSON_ID, SHARED_CHANGEME_HASH, USERNAME, API_KEY],
        );
    });

    after(() => {
        removeAccount();
    });

    const login = () => {
        cy.clearCookies();
        cy.visit("/admin/");
        cy.get("input[name=User]").type(USERNAME);
        cy.get("input[name=Password]").type(`${PASSWORD}{enter}`);
        cy.location("pathname", { timeout: 10000 }).should("eq", "/portal/");
    };

    it("A password sign-in lands in the Member Portal, not the admin shell", () => {
        login();
        cy.get(".portal-home").should("exist");
        cy.get("#sidebar").should("not.exist");
    });

    it("The account pages outside /portal have no portal session, so the account menu leaves out Sign-in and devices", () => {
        login();
        cy.get("#portal-account-toggle").click();
        cy.get("#portalAccountDevices").should("exist");

        cy.visit("/v2/user/current/changepassword");
        cy.get(".portal-shell").should("exist");
        cy.get("#portal-account-toggle").click();
        cy.get("#portal-account-menu").contains('[role="menuitem"]', "Sign out").should("be.visible");
        cy.get("#portalAccountDevices").should("not.exist");
    });

    it("Staff pages send it back to the portal", () => {
        login();
        ["v2/dashboard", "people/dashboard", "PropertyList.php", "LettersAndLabels.php"].forEach((path) => {
            cy.visit(path, { failOnStatusCode: false });
            cy.location("pathname", { timeout: 10000 }).should("eq", "/portal/");
        });
    });

    it("Its session is refused the internal API", () => {
        login();
        cy.request({ url: "/api/person/2", failOnStatusCode: false }).its("status").should("eq", 403);
    });

    it("Its API key is refused the internal API, even for its own family and record", () => {
        ["/api/family/1", "/api/family/20", `/api/person/${PERSON_ID}`, "/api/person/2/notes", "/api/timeline/family/1"].forEach(
            (url) => cy.makePrivateAPICall(API_KEY, "GET", url, null, 403),
        );
        cy.makePrivateAPICall(API_KEY, "POST", "/api/family/1/note", { text: "<p>blocked</p>", private: false }, 403);
    });
});
