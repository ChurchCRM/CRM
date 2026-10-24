/// <reference types="cypress" />

/**
 * Member sign-in, row 3 (#10463): the portal plugin API
 * (docs/member-sign-in-design.md §8.5, §17 "Portal permissions").
 *
 * cypress/fixtures/plugins/portal-example is installed as a community plugin
 * through the fake registry. It declares two capabilities: a roster of each
 * group a person belongs to (from its own rule), and a notice board that only
 * an administrator gives. Given grants have no HTTP surface until row 9, so
 * they are written with cy.dbQuery().
 */

const ID = "portal-example";
const PLUGIN_URL = `/plugins/api/plugins/${ID}`;
const MEMBER = { username: "limited.user", password: "changeme", personId: 4 };
const MEMBER_GROUP = 1;
const OTHER_GROUP = 4;
const ROSTERS = `/portal/plugins/${ID}/rosters`;
const NOTICES = `/portal/plugins/${ID}/notices`;

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const giveGrant = (capability, scopeType = "none", scopeId = 0) =>
    db(
        `INSERT INTO portal_grant_pgr (pgr_per_ID, pgr_Capability, pgr_ScopeType, pgr_ScopeId, pgr_Reason, pgr_GrantedBy_usr_ID, pgr_GrantedAt)
         VALUES (?, ?, ?, ?, 'Cypress', 1, NOW())`,
        [MEMBER.personId, capability, scopeType, scopeId],
    );

const pluginGrantCount = () =>
    db("SELECT COUNT(*) AS n FROM portal_grant_pgr WHERE pgr_Capability LIKE ?", [`plugin.${ID}.%`]).then((rows) => Number(rows[0].n));

const signIn = () => {
    cy.clearCookies();
    cy.request({ method: "POST", url: "/session/begin", form: true, body: { User: MEMBER.username, Password: MEMBER.password } });
    cy.request("/portal/").its("status").should("eq", 200);
};

const status = (url) => cy.request({ url, failOnStatusCode: false, followRedirect: false }).its("status");

const navLinks = () =>
    cy.request("/portal/").then((response) => {
        const doc = new DOMParser().parseFromString(response.body, "text/html");
        return [...doc.querySelectorAll(".portal-nav-link")].map((link) => link.getAttribute("href"));
    });

const install = () => {
    cy.task("pluginFixtures:write", { id: ID, version: "1.0.0", source: ID }).then(([entry]) => {
        cy.makePrivateAdminAPICall("POST", "/plugins/api/registry/refresh");
        cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/install", { downloadUrl: entry.downloadUrl });
    });
    cy.makePrivateAdminAPICall("POST", `${PLUGIN_URL}/enable`);
};

describe("Member Portal plugin API (portal capabilities, rules, pages and nav)", () => {
    before(() => {
        cy.makePrivateAdminAPICall("DELETE", PLUGIN_URL, null, [200, 400]);
        db("DELETE FROM portal_grant_pgr WHERE pgr_Reason = 'Cypress'");
        install();
    });

    afterEach(() => {
        db("DELETE FROM portal_grant_pgr WHERE pgr_Reason = 'Cypress' AND pgr_Capability NOT LIKE ?", [`plugin.${ID}.%`]);
        cy.clearCookies();
    });

    after(() => {
        cy.makePrivateAdminAPICall("DELETE", PLUGIN_URL, null, [200, 400]);
        db("DELETE FROM portal_grant_pgr WHERE pgr_Reason = 'Cypress'");
        cy.task("pluginFixtures:clear");
        cy.makePrivateAdminAPICall("POST", "/plugins/api/registry/refresh");
    });

    it("grants its capability by its own rule, on its own pages, API and nav item", () => {
        signIn();
        navLinks().then((links) => {
            expect(links).to.include(ROSTERS);
            expect(links).to.not.include(NOTICES);
            expect(links.indexOf(ROSTERS)).to.be.lessThan(links.indexOf("/portal/family"));
        });

        cy.request(ROSTERS).then((response) => {
            expect(response.body).to.contain("portal-example-rosters");
            expect(response.body).to.contain("Angels class");
            expect(response.body).to.contain("portal-nav");
        });
        cy.request(`${ROSTERS}/${MEMBER_GROUP}`).its("body").should("contain", "Campbell");
        status(`${ROSTERS}/${OTHER_GROUP}`).should("eq", 404);
        status(`${ROSTERS}/987654`).should("eq", 404);
        cy.request(`/api/portal/plugins/${ID}/groups`).its("body.groupIds").should("deep.equal", [MEMBER_GROUP]);

        status(NOTICES).should("eq", 403);
    });

    it("denies a capability the plugin did not declare, even one given to the person", () => {
        giveGrant(`plugin.${ID}.undeclared`);
        signIn();
        status(`/portal/plugins/${ID}/undeclared`).should("eq", 404);
        db("DELETE FROM portal_grant_pgr WHERE pgr_Capability = ?", [`plugin.${ID}.undeclared`]);
    });

    it("opens a page and its nav item for a given grant", () => {
        giveGrant(`plugin.${ID}.notices`);
        signIn();
        navLinks().should("include", NOTICES);
        cy.request(NOTICES).its("body").should("contain", "portal-example-notices-text");
    });

    it("takes away the capabilities, rules, pages and nav with the plugin, and keeps given grants", () => {
        signIn();
        cy.then(() => cy.makePrivateAdminAPICall("POST", `${PLUGIN_URL}/disable`));

        navLinks().then((links) => {
            expect(links).to.not.include(ROSTERS);
            expect(links).to.not.include(NOTICES);
        });
        status(ROSTERS).should("eq", 404);
        status(NOTICES).should("eq", 404);
        status(`/api/portal/plugins/${ID}/groups`).should("eq", 404);
        pluginGrantCount().should("eq", 1);

        cy.then(() => cy.makePrivateAdminAPICall("POST", `${PLUGIN_URL}/enable`));
        status(NOTICES).should("eq", 200);
        status(ROSTERS).should("eq", 200);
    });

    it("deletes the plugin's given grants on uninstall, and no others", () => {
        giveGrant("portal.family.contacts.view", "family", 1);
        cy.makePrivateAdminAPICall("DELETE", PLUGIN_URL, null, 200);

        pluginGrantCount().should("eq", 0);
        db("SELECT COUNT(*) AS n FROM portal_grant_pgr WHERE pgr_Reason = 'Cypress' AND pgr_Capability = ?", [
            "portal.family.contacts.view",
        ]).then((rows) => expect(Number(rows[0].n)).to.eq(1));

        signIn();
        status(NOTICES).should("eq", 404);
    });
});
