/// <reference types="cypress" />

/**
 * Volunteer v2 — a team leader adds someone to the volunteer pool (#10531).
 *
 * Person 3 (`user.api.key`, Manage My Ministries) leads one team of the fixture
 * ministry; person 52 coordinates it; person 12 leads the same team too. Person 99
 * (`selfedit.api.key`) is a member-login team leader of another team, for the portal's
 * person search. Seeded people the search must leave out: 55 (deceased), 18 (in the
 * deactivated Ray family), 911 (deactivated).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const TIMERJOBS_LAST_RUN_URL = "/admin/api/system/config/sLastTimerJobsRunDateTime";
const ADMIN_KEY = "admin.api.key";
const LEADER_KEY = "user.api.key";
const MEMBER_LEADER_KEY = "selfedit.api.key";
const MEMBER_KEY = "selfedit.plus.notes.api.key";
const STAFF_NO_SCOPE_KEY = "nofinance.api.key";
const VOLUNTEER_URL = "/api/ministries";
const SEARCH_URL = `${VOLUNTEER_URL}/people/search`;

const PERSON_LEADER = 3;
const PERSON_MEMBER_LEADER = 99;
const PERSON_OTHER_LEADER = 12;
const PERSON_COORDINATOR = 52;
const COORDINATOR_EMAIL = "vernon.newman@example.com";
const PERSON_ADDED = 54;
const ADDED_EMAIL = "connor.silva@example.com";
const PERSON_QUALIFIED = 56;
const PERSON_DECEASED = 55;
const PERSON_INACTIVE_FAMILY = 18;
const PERSON_DEACTIVATED = 911;
const PERSON_BY_COORDINATOR = 8;
const PERSON_GRANTED = 57;
const PERSON_GRANTED_BY_COORDINATOR = 58;

const PREFIX = "TLP10531";

let ministryId = 0;
let otherMinistryId = 0;
let teamLed = 0;
let teamSibling = 0;
let teamElsewhere = 0;
let posLed = 0;
let posSibling = 0;
let originalVersion = "v1";
let mailpitAvailable = false;

function dbOk(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(`Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`);
        }
        return result.rows;
    });
}

function api(key, method, url, body, expectedStatus = 200) {
    return cy.makePrivateAPICall(Cypress.testEnv(key), method, url, body, expectedStatus);
}

function poolMembers(id) {
    return dbOk(
        `SELECT r.p2g2r_per_ID AS personId FROM person2group2role_p2g2r r
           JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
          WHERE g.grp_ministry_id = ?`,
        [id],
    ).then((rows) => rows.map((row) => Number(row.personId)));
}

function poolAddRows() {
    return dbOk(
        `SELECT vntf_per_ID AS personId, vntf_Context AS context FROM volunteer_notification_vntf
          WHERE vntf_Type = 'pool_add' AND vntf_DedupeKey LIKE ?`,
        [`pool_add:${ministryId}:%`],
    );
}

function drain() {
    cy.makePrivateAdminAPICall("POST", TIMERJOBS_LAST_RUN_URL, { value: "" }, 200);
    cy.makePrivateAdminAPICall("POST", "/api/background/timerjobs", {}, 200);
}

function mailTo(address) {
    return cy.task("mail:list", { limit: 100 }).then((result) =>
        (result.ok ? result.body.messages || [] : []).filter((message) =>
            (message.To || []).some((to) => (to.Address || "").toLowerCase() === address),
        ),
    );
}

function cleanupFixtures() {
    const like = [`${PREFIX}%`];
    dbOk(
        `DELETE n FROM volunteer_notification_vntf n
           JOIN volunteer_ministry_vmin m ON n.vntf_DedupeKey LIKE CONCAT('pool_add:', m.vmin_ID, ':%')
          WHERE m.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vqal FROM volunteer_qualification_vqal vqal
           JOIN volunteer_position_vpos vpos ON vpos.vpos_ID = vqal.vqal_vpos_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vpos FROM volunteer_position_vpos vpos
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vscp FROM volunteer_scope_vscp vscp
           JOIN volunteer_team_vtem vtem ON vtem.vtem_ID = vscp.vscp_ScopeId AND vscp.vscp_ScopeType = 'team'
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vscp FROM volunteer_scope_vscp vscp
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vscp.vscp_ScopeId AND vscp.vscp_ScopeType = 'ministry'
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r
           JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE g FROM group_grp g
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, like);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, like);
}

before(() => {
    cy.rememberTestEnv([ADMIN_KEY, LEADER_KEY, MEMBER_LEADER_KEY, MEMBER_KEY, STAFF_NO_SCOPE_KEY]);
});

describe("Volunteer v2 — a team leader adds someone to the pool (#10531)", () => {
    before(() => {
        cy.task("mail:available").then((result) => {
            mailpitAvailable = result.available;
        });
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
            originalVersion = resp.body.value ?? "v1";
        });
        cy.makePrivateAdminAPICall("POST", SETTING_URL, { value: "v2" }, 200);
        cleanupFixtures();

        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries`, { name: `${PREFIX} Hospitality` }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
        });
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries`, { name: `${PREFIX} Missions` }, 201).then((resp) => {
            otherMinistryId = resp.body.ministry.id;
        });
        cy.then(() => {
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, { name: `${PREFIX} Greeters` }, 201).then(
                (resp) => {
                    teamLed = resp.body.team.id;
                },
            );
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, { name: `${PREFIX} Ushers` }, 201).then(
                (resp) => {
                    teamSibling = resp.body.team.id;
                },
            );
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${otherMinistryId}/teams`, { name: `${PREFIX} Builders` }, 201).then(
                (resp) => {
                    teamElsewhere = resp.body.team.id;
                },
            );
        });
        cy.then(() => {
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
                name: `${PREFIX} Door`,
                teamId: teamLed,
            }, 201).then((resp) => {
                posLed = resp.body.position.id;
            });
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
                name: `${PREFIX} Aisle`,
                teamId: teamSibling,
            }, 201).then((resp) => {
                posSibling = resp.body.position.id;
            });
            [
                { personId: PERSON_LEADER, scopeType: "team", scopeId: teamLed },
                { personId: PERSON_OTHER_LEADER, scopeType: "team", scopeId: teamLed },
                { personId: PERSON_MEMBER_LEADER, scopeType: "team", scopeId: teamElsewhere },
                { personId: PERSON_COORDINATOR, scopeType: "ministry", scopeId: ministryId },
            ].forEach((grant) => api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/scopes`, grant, [200, 201]));
        });
    });

    after(() => {
        cleanupFixtures();
        cy.makePrivateAdminAPICall("POST", SETTING_URL, { value: originalVersion }, 200);
    });

    describe("The person search", () => {
        it("Returns id, name and family name only, for active people", () => {
            api(LEADER_KEY, "GET", `${SEARCH_URL}?q=Newman`).then((resp) => {
                expect(resp.body).to.be.an("array").and.not.be.empty;
                resp.body.forEach((row) => {
                    expect(Object.keys(row).sort()).to.deep.eq(["familyName", "id", "name"]);
                });
                const ids = resp.body.map((row) => row.id);
                expect(ids).to.include(PERSON_COORDINATOR);
                expect(ids, "deceased").to.not.include(PERSON_DECEASED);
                expect(resp.body.find((row) => row.id === PERSON_COORDINATOR).familyName).to.eq("Newman");
            });
        });

        it("Leaves out people in a deactivated family and deactivated people", () => {
            api(LEADER_KEY, "GET", `${SEARCH_URL}?q=Ray`).then((resp) => {
                expect(resp.body.map((row) => row.id)).to.not.include(PERSON_INACTIVE_FAMILY);
            });
            api(LEADER_KEY, "GET", `${SEARCH_URL}?q=Signin`).then((resp) => {
                expect(resp.body.map((row) => row.id)).to.not.include(PERSON_DEACTIVATED);
            });
        });

        it("Needs two characters and returns at most 20 people", () => {
            api(LEADER_KEY, "GET", `${SEARCH_URL}?q=e`).then((resp) => {
                expect(resp.body).to.deep.eq([]);
            });
            api(LEADER_KEY, "GET", `${SEARCH_URL}?q=er`).then((resp) => {
                expect(resp.body).to.have.length(20);
            });
        });

        it("Matches every word against first or last names", () => {
            api(LEADER_KEY, "GET", `${SEARCH_URL}?q=${encodeURIComponent("vern newm")}`).then((resp) => {
                expect(resp.body.map((row) => row.id)).to.deep.eq([PERSON_COORDINATOR]);
            });
            api(LEADER_KEY, "GET", `${SEARCH_URL}?q=${encodeURIComponent("vern newm vern zzqx")}`).then((resp) => {
                expect(resp.body, "a fourth word that matches nobody").to.deep.eq([]);
            });
        });

        it("Answers a member-login team leader, and refuses a member who leads nothing", () => {
            api(MEMBER_LEADER_KEY, "GET", `${SEARCH_URL}?q=Newman`).then((resp) => {
                expect(resp.body.map((row) => row.id)).to.include(PERSON_COORDINATOR);
            });
            api(MEMBER_KEY, "GET", `${SEARCH_URL}?q=Newman`, null, 403);
            api(STAFF_NO_SCOPE_KEY, "GET", `${SEARCH_URL}?q=Newman`, null, 403);
        });
    });

    describe("Adding to the pool", () => {
        it("Adds a person to their own ministry's pool and tells the coordinator only", () => {
            if (mailpitAvailable) {
                cy.task("mail:clear");
            }
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamLed}/pool/${PERSON_ADDED}`, {}, 201).then((resp) => {
                expect(resp.body).to.include({ personId: PERSON_ADDED, added: true, notified: 1 });
                expect(resp.body.qualified).to.deep.eq([]);
            });
            poolMembers(ministryId).then((ids) => expect(ids).to.include(PERSON_ADDED));
            poolAddRows().then((rows) => {
                expect(rows.map((row) => Number(row.personId))).to.deep.eq([PERSON_COORDINATOR]);
                expect(JSON.parse(rows[0].context)).to.include({ personId: PERSON_ADDED, addedBy: PERSON_LEADER, teamId: teamLed });
            });
        });

        it("Emails the coordinator who added whom, and nothing to the added person", function () {
            if (!mailpitAvailable) {
                this.skip();
            }
            dbOk("UPDATE volunteer_team_vtem SET vtem_Name = ? WHERE vtem_ID = ?", [`${PREFIX} <b>Greeters</b>`, teamLed]);
            drain();
            dbOk("UPDATE volunteer_team_vtem SET vtem_Name = ? WHERE vtem_ID = ?", [`${PREFIX} Greeters`, teamLed]);
            mailTo(COORDINATOR_EMAIL).then((messages) => {
                const about = messages.filter((message) => (message.Subject || "").includes("added Connor Newman to the"));
                expect(about, "one email to the coordinator").to.have.length(1);
                expect(about[0].Subject).to.include(`${PREFIX} Hospitality volunteers`);
                return cy.task("mail:get", { id: about[0].ID });
            }).then((result) => {
                expect(result.body.Text).to.include(`who leads ${PREFIX}`);
                expect(result.body.Text).to.include("Only a coordinator can take someone out of the pool.");
                expect(result.body.HTML).to.include(`${PREFIX} &lt;b&gt;Greeters&lt;/b&gt;`);
                expect(result.body.HTML).to.not.include("<b>Greeters</b>");
            });
            mailTo(ADDED_EMAIL).then((messages) => {
                expect(messages, "nothing to the person who was added").to.have.length(0);
            });
        });

        it("Answers 200 for somebody already in the pool and tells nobody again", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamLed}/pool/${PERSON_ADDED}`, {}, 200).then((resp) => {
                expect(resp.body).to.include({ added: false, notified: 0 });
            });
            poolAddRows().then((rows) => expect(rows).to.have.length(1));
        });

        it("Qualifies them for positions of the leader's own team", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamLed}/pool/${PERSON_QUALIFIED}`, { positionIds: [posLed] }, 201).then(
                (resp) => {
                    expect(resp.body.qualified).to.deep.eq([posLed]);
                },
            );
            dbOk(
                `SELECT vqal_ID FROM volunteer_qualification_vqal WHERE vqal_vpos_ID = ? AND vqal_per_ID = ? AND vqal_Active = 1`,
                [posLed, PERSON_QUALIFIED],
            ).then((rows) => expect(rows).to.have.length(1));
        });

        it("Refuses a position of another team, and writes nothing", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamLed}/pool/${PERSON_BY_COORDINATOR}`, { positionIds: [posSibling] }, 400);
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamLed}/pool/${PERSON_BY_COORDINATOR}`, { positionIds: ["door"] }, 400);
            poolMembers(ministryId).then((ids) => expect(ids).to.not.include(PERSON_BY_COORDINATOR));
            dbOk(`SELECT vqal_ID FROM volunteer_qualification_vqal WHERE vqal_vpos_ID = ?`, [posSibling]).then((rows) =>
                expect(rows).to.have.length(0),
            );
        });

        it("Refuses people the search would not offer", () => {
            [PERSON_DECEASED, PERSON_INACTIVE_FAMILY, PERSON_DEACTIVATED, 999999].forEach((personId) => {
                api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamLed}/pool/${personId}`, {}, 404);
            });
        });

        it("Refuses another team's pool route, in this ministry or another", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamSibling}/pool/${PERSON_BY_COORDINATOR}`, {}, 403);
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamElsewhere}/pool/${PERSON_BY_COORDINATOR}`, {}, 403);
            poolMembers(otherMinistryId).then((ids) => expect(ids).to.not.include(PERSON_BY_COORDINATOR));
        });

        it("Refuses a member and a staff login who lead no team", () => {
            api(MEMBER_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamLed}/pool/${PERSON_BY_COORDINATOR}`, {}, 403);
            api(STAFF_NO_SCOPE_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamLed}/pool/${PERSON_BY_COORDINATOR}`, {}, 403);
            poolMembers(ministryId).then((ids) => expect(ids).to.not.include(PERSON_BY_COORDINATOR));
        });

        it("Still refuses a team leader removing anyone from the pool", () => {
            api(LEADER_KEY, "DELETE", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${PERSON_ADDED}`, null, 403);
            api(LEADER_KEY, "DELETE", `${VOLUNTEER_URL}/ministries/${ministryId}/volunteers/${PERSON_ADDED}`, null, 403);
            poolMembers(ministryId).then((ids) => expect(ids).to.include(PERSON_ADDED));
        });

        it("Holds a team leader's qualification grant to the same rules when it adds someone new", () => {
            const grant = (personId, status) =>
                api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/positions/${posLed}/qualifications`, { personId }, status);
            grant(PERSON_DECEASED, 404);
            grant(PERSON_INACTIVE_FAMILY, 404);
            poolMembers(ministryId).then((ids) => {
                expect(ids).to.not.include(PERSON_DECEASED);
                expect(ids).to.not.include(PERSON_INACTIVE_FAMILY);
            });

            grant(PERSON_GRANTED, [200, 201]);
            poolMembers(ministryId).then((ids) => expect(ids).to.include(PERSON_GRANTED));
            poolAddRows().then((rows) => {
                const about = rows.filter((row) => JSON.parse(row.context).personId === PERSON_GRANTED);
                expect(about.map((row) => Number(row.personId)), "the coordinator is told").to.deep.eq([PERSON_COORDINATOR]);
                expect(JSON.parse(about[0].context)).to.include({ addedBy: PERSON_LEADER, teamId: teamLed });
            });
        });

        it("Leaves a coordinator's qualification grant as it was: no email", () => {
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/positions/${posLed}/qualifications`, {
                personId: PERSON_GRANTED_BY_COORDINATOR,
            }, [200, 201]);
            poolMembers(ministryId).then((ids) => expect(ids).to.include(PERSON_GRANTED_BY_COORDINATOR));
            poolAddRows().then((rows) => {
                expect(rows.map((row) => JSON.parse(row.context).personId)).to.not.include(PERSON_GRANTED_BY_COORDINATOR);
            });
        });

        it("Leaves the coordinator path as it was: no email for a coordinator's own add", () => {
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${PERSON_BY_COORDINATOR}`, null, 201);
            poolAddRows().then((rows) => {
                expect(rows.map((row) => JSON.parse(row.context).personId)).to.not.include(PERSON_BY_COORDINATOR);
            });
        });
    });
});
