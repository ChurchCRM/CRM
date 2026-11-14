/// <reference types="cypress" />

/**
 * The safety net for a self-service account (usr_EditSelf=1, not an
 * administrator) that the 7.8.3 upgrade did not remove (member sign-in design
 * §12). The upgrade deletes every such account and nothing creates one any more,
 * so the account is a fixture written in SQL. The confinement it relies on,
 * AuthMiddleware::isLimitedAccessAllowedPath() and the PageInit.php redirect,
 * stays for one more release: such an account reaches only the Member Portal,
 * and no internal API, by session or by API key (GHSA-jjcj-h3cm-p7x7).
 */
const PERSON_ID = 4; // Darren Campbell, family 1
const USERNAME = "safety.net.editself";
const PASSWORD = "changeme";
const CHANGED_PASSWORD = "Forced-Change-9876";
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
             VALUES (?, ?, 1, ?, ?, 1, 0, 1)`,
            [PERSON_ID, SHARED_CHANGEME_HASH, USERNAME, API_KEY],
        );
    });

    const setConfig = (name, value) => cy.makePrivateAdminAPICall("POST", `/admin/api/system/config/${name}`, { value }, 200);

    after(() => {
        removeAccount();
        setConfig("bRequire2FA", "0");
        setConfig("i2FAGracePeriodDays", "7");
    });

    const signIn = (password = CHANGED_PASSWORD) => {
        cy.clearCookies();
        cy.visit("/admin/");
        cy.get("input[name=User]").type(USERNAME);
        cy.get("input[name=Password]").type(`${password}{enter}`);
    };
    const login = () => {
        signIn();
        cy.location("pathname", { timeout: 10000 }).should("match", /\/portal\/$/);
    };

    it("Its API key is refused the internal API, even for its own family and record", () => {
        ["/api/family/1", "/api/family/20", `/api/person/${PERSON_ID}`, "/api/person/2/notes", "/api/timeline/family/1"].forEach(
            (url) => cy.makePrivateAPICall(API_KEY, "GET", url, null, 403),
        );
        cy.makePrivateAPICall(API_KEY, "POST", "/api/family/1/note", { text: "<p>blocked</p>", private: false }, 403);
    });

    // The fixture account is created with a pending password change; every later test signs in with CHANGED_PASSWORD.
    it("A forced password change stays reachable when 2FA enrollment is also required", () => {
        setConfig("i2FAGracePeriodDays", "0");
        setConfig("bRequire2FA", "1");
        signIn(PASSWORD);
        cy.location("pathname", { timeout: 10000 }).should("match", /\/portal\/profile\/password$/);
        cy.get("#OldPassword").type(PASSWORD);
        cy.get("#NewPassword1").type(CHANGED_PASSWORD);
        cy.get("#NewPassword2").type(CHANGED_PASSWORD);
        cy.get("#passwordChangeForm").submit();
        cy.get("#portal-password-changed").should("exist");
        setConfig("bRequire2FA", "0");
        setConfig("i2FAGracePeriodDays", "7");
        cy.visit("/portal/");
        cy.location("pathname").should("match", /\/portal\/$/);
    });

    it("A password sign-in lands in the Member Portal, not the admin shell", () => {
        login();
        cy.get(".portal-home").should("exist");
        cy.get("#sidebar").should("not.exist");
    });

    it("A forced 2FA enrollment happens in the Member Portal", () => {
        setConfig("i2FAGracePeriodDays", "0");
        setConfig("bRequire2FA", "1");
        signIn();
        cy.location("pathname", { timeout: 10000 }).should("match", /\/portal\/profile\/two-factor$/);
        cy.get("#two-factor-enrollment-app").should("exist");
        cy.visit("/portal/");
        cy.location("pathname").should("match", /\/portal\/profile\/two-factor$/);
        setConfig("bRequire2FA", "0");
        setConfig("i2FAGracePeriodDays", "7");
    });

    it("Staff pages send it back to the portal", () => {
        login();
        ["account", "account/password", "account/2fa", "v2/dashboard", "people/dashboard", "PropertyList.php", "/people/reports/letters-and-labels"].forEach((path) => {
            cy.visit(path, { failOnStatusCode: false });
            cy.location("pathname", { timeout: 10000 }).should("match", /\/portal\/$/);
        });
    });

    it("Its session is refused the internal API", () => {
        login();
        cy.request({ url: "/api/person/2", failOnStatusCode: false }).its("status").should("eq", 403);
    });
});
