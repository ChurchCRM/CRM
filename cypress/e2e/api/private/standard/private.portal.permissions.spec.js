/// <reference types="cypress" />

/**
 * Member sign-in, row 2 (#10463): the portal permissions model
 * (docs/member-sign-in-design.md §8, §4 "Teenagers", §17 "Portal permissions").
 *
 * A portal-only session is reached through the real STAFF path: sign in, open
 * the portal, then let the staff session time out (iSessionTimeout = 1).
 * Given grants, the timeouts and a person's role and birth year have no HTTP
 * surface yet (row 9 adds the admin pages), so they are set with cy.dbQuery().
 */

const LEADER = { username: "lena.black.editself.notes@example.com", password: "changeme", personId: 100 };
const NON_ADULT = { username: "limited.user", password: "changeme", personId: 4, familyId: 1 };
const ADMIN = { username: "admin", password: "changeme" };
const AMANDA_EMAIL = "amanda.black@example.com";
const MATHEW_EMAIL = "mathew.campbell@example.com";
const PREFIX = "PERM10463";
const VERSION_URL = "/admin/api/system/config/sVolunteerVersion";
const touchedConfig = ["iSessionTimeout", "bPortalShowCalendar", "bPortalShowVolunteer"];

let ministryId = 0;
let teamLed = 0;
let teamOther = 0;
let leaderScopeId = 0;
let originalVersion = "v1";

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const setConfig = (name, value) =>
    db(
        "INSERT INTO config_cfg (cfg_name, cfg_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE cfg_value = VALUES(cfg_value)",
        [name, value],
    );

const signIn = ({ username, password }) => {
    cy.clearCookies();
    cy.request({ method: "POST", url: "/session/begin", form: true, body: { User: username, Password: password } });
    cy.request("/portal/").its("status").should("eq", 200);
};

const endStaffSession = () => {
    setConfig("iSessionTimeout", "1");
    cy.wait(2100);
};

const status = (url) => cy.request({ url, failOnStatusCode: false, followRedirect: false }).its("status");

const navIds = () =>
    cy.request("/portal/").then((response) => {
        const doc = new DOMParser().parseFromString(response.body, "text/html");
        return [...doc.querySelectorAll(".portal-nav-link")].map((link) => link.getAttribute("href"));
    });

const csrfToken = () =>
    cy.request("/portal/profile/edit").then((response) => {
        const match = response.body.match(/name="csrf_token"\s+value="([a-f0-9]+)"/i);
        expect(match, "csrf_token").to.not.be.null;
        return match[1];
    });

const giveGrant = (personId, capability, scopeType, scopeId, endsAtSql) =>
    db(
        `INSERT INTO portal_grant_pgr (pgr_per_ID, pgr_Capability, pgr_ScopeType, pgr_ScopeId, pgr_EndsAt, pgr_Reason, pgr_GrantedBy_usr_ID, pgr_GrantedAt)
         VALUES (?, ?, ?, ?, ${endsAtSql}, 'Cypress', 1, NOW())`,
        [personId, capability, scopeType, scopeId],
    );

function cleanupFixtures() {
    db("DELETE FROM portal_grant_pgr WHERE pgr_Reason = 'Cypress'");
    db(
        `DELETE vscp FROM volunteer_scope_vscp vscp
           JOIN volunteer_team_vtem vtem ON vscp.vscp_ScopeType = 'team' AND vscp.vscp_ScopeID = vtem.vtem_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    db("SELECT vmin_ID FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?", [`${PREFIX}%`]).then((rows) => {
        rows.forEach((row) => {
            cy.makePrivateAdminAPICall("POST", `/api/ministries/ministries/${row.vmin_ID}`, { active: false }, [200, 404]);
            cy.makePrivateAdminAPICall("DELETE", `/api/ministries/ministries/${row.vmin_ID}`, null, [200, 204, 404]);
        });
    });
}

describe("Member Portal permissions (portal permissions model)", () => {
    const savedConfig = {};

    before(() => {
        db(`SELECT cfg_name, cfg_value FROM config_cfg WHERE cfg_name IN (${touchedConfig.map(() => "?").join(", ")})`, touchedConfig).then(
            (rows) => {
                rows.forEach((row) => {
                    savedConfig[row.cfg_name] = row.cfg_value;
                });
            },
        );
        cy.makePrivateAdminAPICall("GET", VERSION_URL, null, 200).then((response) => {
            originalVersion = response.body.value ?? response.body.Value ?? "v1";
        });
        cy.makePrivateAdminAPICall("POST", VERSION_URL, { value: "v2" }, 200);
        cleanupFixtures();

        cy.makePrivateAdminAPICall("POST", "/api/ministries/ministries", { name: `${PREFIX} Hospitality`, description: "" }, 201).then(
            (response) => {
                ministryId = response.body.ministry.id;
            },
        );
        cy.then(() => {
            cy.makePrivateAdminAPICall("POST", `/api/ministries/ministries/${ministryId}/teams`, { name: `${PREFIX} Greeters` }, 201).then(
                (response) => {
                    teamLed = response.body.team.id;
                },
            );
            cy.makePrivateAdminAPICall("POST", `/api/ministries/ministries/${ministryId}/teams`, { name: `${PREFIX} Ushers` }, 201).then(
                (response) => {
                    teamOther = response.body.team.id;
                },
            );
        });
    });

    beforeEach(() => {
        cy.then(() => {
            if (leaderScopeId === 0) {
                cy.makePrivateAdminAPICall(
                    "POST",
                    "/api/ministries/scopes",
                    { personId: LEADER.personId, scopeType: "team", scopeId: teamLed },
                    [200, 201],
                ).then((response) => {
                    leaderScopeId = response.body.scope.id;
                });
            }
        });
    });

    afterEach(() => {
        touchedConfig.forEach((name) => {
            if (Object.hasOwn(savedConfig, name)) {
                setConfig(name, savedConfig[name]);
            } else {
                db("DELETE FROM config_cfg WHERE cfg_name = ?", [name]);
            }
        });
        db("DELETE FROM portal_grant_pgr WHERE pgr_Reason = 'Cypress'");
        db("UPDATE person_per SET per_fmr_ID = 4, per_BirthYear = 1910, per_BirthMonth = 7, per_BirthDay = 23 WHERE per_ID = ?", [
            NON_ADULT.personId,
        ]);
        cy.clearCookies();
    });

    after(() => {
        cleanupFixtures();
        cy.makePrivateAdminAPICall("POST", VERSION_URL, { value: originalVersion }, 200);
    });

    describe("Team leaders", () => {
        it("manages only their own team in a portal-only session, and gets 404 for another", () => {
            signIn(LEADER);
            endStaffSession();

            cy.request("/portal/teams").then((response) => {
                expect(response.body).to.contain(`${PREFIX} Greeters`);
                expect(response.body).to.not.contain(`${PREFIX} Ushers`);
            });
            navIds().should("include", "/portal/teams");
            status(`/portal/teams/${teamLed}`).should("eq", 200);
            status(`/portal/teams/${teamOther}`).should("eq", 404);
            status("/portal/teams/987654").should("eq", 404);

            status(`/api/ministries/teams/${teamLed}`).should("eq", 200);
            status(`/api/ministries/teams/${teamOther}`).should("eq", 403);
            status("/api/ministries/me/assignments").should("eq", 200);
            status(`/api/person/${LEADER.personId}`).should("eq", 401);
        });

        it("loses the capability on the next request when removed from the team", () => {
            signIn(LEADER);
            endStaffSession();
            status(`/portal/teams/${teamLed}`).should("eq", 200);

            cy.then(() => cy.makePrivateAdminAPICall("DELETE", `/api/ministries/scopes/${leaderScopeId}`, null, [200, 204]));
            cy.then(() => {
                leaderScopeId = 0;
            });

            status(`/portal/teams/${teamLed}`).should("eq", 404);
            status("/portal/teams").should("eq", 403);
            status(`/api/ministries/teams/${teamLed}`).should("eq", 403);
            navIds().should("not.include", "/portal/teams");
        });

        it("gives a STAFF principal no team it does not lead", () => {
            signIn(ADMIN);
            status("/portal/teams").should("eq", 403);
            status(`/portal/teams/${teamLed}`).should("eq", 404);
            navIds().should("not.include", "/portal/teams");
        });
    });

    describe("Teenagers and other non-adults", () => {
        it("lets an adult of the family see the members' contact details and confirm", () => {
            signIn(LEADER);
            cy.request("/api/portal/family").then(({ body }) => {
                expect(body.canSeeContacts).to.eq(true);
                expect(body.canConfirm).to.eq(true);
                expect(body.members.map((member) => member.email)).to.include(AMANDA_EMAIL);
            });
            cy.request("/portal/family").its("body").should("contain", "data-member-contact");
        });

        it("hides other members' email and phone from a non-adult, who cannot confirm the family", () => {
            signIn(NON_ADULT);
            cy.request("/api/portal/family").then(({ body }) => {
                expect(body.canSeeContacts).to.eq(false);
                expect(body.canConfirm).to.eq(false);
                expect(body.canEdit).to.eq(false);
                body.members
                    .filter((member) => !member.isSelf)
                    .forEach((member) => {
                        expect(member.email, member.fullName).to.eq("");
                        expect(member.cellPhone, member.fullName).to.eq("");
                    });
                expect(body.members.find((member) => member.isSelf).email).to.not.eq("");
            });
            cy.request("/portal/family").then(({ body }) => {
                expect(body).to.not.contain("data-member-contact");
                expect(body).to.not.contain("portal-family-confirm-card");
                expect(body).to.not.contain(MATHEW_EMAIL);
            });
            status("/portal/family/confirm").should("eq", 403);
            csrfToken().then((token) => {
                cy.request({
                    method: "POST",
                    url: "/api/portal/family/confirm",
                    body: { result: "no-change" },
                    headers: { "X-CSRF-Token": token },
                    failOnStatusCode: false,
                })
                    .its("status")
                    .should("eq", 403);
            });
        });

        it("counts a head of household or spouse as an adult from 18, or when the birth year is unknown", () => {
            const year = new Date().getFullYear();
            const contactsVisible = () => cy.request("/api/portal/family").its("body.canSeeContacts");

            db("UPDATE person_per SET per_fmr_ID = 2, per_BirthYear = ?, per_BirthMonth = 1, per_BirthDay = 1 WHERE per_ID = ?", [
                year - 16,
                NON_ADULT.personId,
            ]);
            signIn(NON_ADULT);
            contactsVisible().should("eq", false);

            db("UPDATE person_per SET per_BirthYear = ? WHERE per_ID = ?", [year - 30, NON_ADULT.personId]);
            contactsVisible().should("eq", true);

            db("UPDATE person_per SET per_BirthYear = NULL WHERE per_ID = ?", [NON_ADULT.personId]);
            contactsVisible().should("eq", true);
        });
    });

    describe("Given grants", () => {
        it("works until its end date", () => {
            giveGrant(NON_ADULT.personId, "portal.family.contacts.view", "family", NON_ADULT.familyId, "DATE_ADD(NOW(), INTERVAL 1 DAY)");
            signIn(NON_ADULT);
            cy.request("/api/portal/family").then(({ body }) => {
                expect(body.canSeeContacts).to.eq(true);
                expect(body.canConfirm).to.eq(false);
                expect(body.members.map((member) => member.email)).to.include(MATHEW_EMAIL);
            });

            db("UPDATE portal_grant_pgr SET pgr_EndsAt = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE pgr_Reason = 'Cypress'");
            cy.request("/api/portal/family").its("body.canSeeContacts").should("eq", false);
        });

        it("lets a member run a team they were given, through the volunteer API too, until it ends", () => {
            giveGrant(LEADER.personId, "volunteer.team.manage", "team", teamOther, "NULL");
            signIn(LEADER);
            endStaffSession();
            status(`/portal/teams/${teamOther}`).should("eq", 200);
            status(`/api/ministries/teams/${teamOther}`).should("eq", 200);

            db("UPDATE portal_grant_pgr SET pgr_EndsAt = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE pgr_Reason = 'Cypress'");
            status(`/portal/teams/${teamOther}`).should("eq", 404);
            status(`/api/ministries/teams/${teamOther}`).should("eq", 403);
        });

        it("is ignored when its scope type does not match the capability", () => {
            giveGrant(NON_ADULT.personId, "portal.family.contacts.view", "team", NON_ADULT.familyId, "NULL");
            signIn(NON_ADULT);
            cy.request("/api/portal/family").its("body.canSeeContacts").should("eq", false);
        });
    });

    describe("Feature switches", () => {
        it("denies the calendar to everyone while it is switched off", () => {
            signIn(LEADER);
            status("/portal/calendar").should("eq", 200);
            status("/api/portal/calendar/events").should("eq", 200);

            setConfig("bPortalShowCalendar", "0");
            status("/portal/calendar").should("eq", 404);
            status("/api/portal/calendar/events").should("eq", 404);
            navIds().should("not.include", "/portal/calendar");
        });

        it("denies volunteering and team management while volunteering is switched off", () => {
            signIn(LEADER);
            endStaffSession();
            status(`/portal/teams/${teamLed}`).should("eq", 200);

            setConfig("bPortalShowVolunteer", "0");
            status("/portal/volunteer/schedule").should("eq", 404);
            status("/portal/teams").should("eq", 404);
            status(`/portal/teams/${teamLed}`).should("eq", 404);
            status("/api/ministries/me/assignments").should("eq", 403);
            status(`/api/ministries/teams/${teamLed}`).should("eq", 403);
        });
    });
});
