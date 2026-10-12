/// <reference types="cypress" />

/**
 * Volunteer v2 — the API behind the ministry page's team-only view (#10530).
 *
 * Persona: person 3 (`user.api.key`), a staff login with Manage My Ministries,
 * given one `team` scope and no ministry scope. The team view reads and writes
 * only through team-scoped endpoints; everything ministry-wide stays 403.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const LEADER_KEY = "user.api.key";
const VOLUNTEER_URL = "/api/ministries";

const PERSON_LEADER = 3;
const POOL_MEMBER = 8;
const OUTSIDER = 9;

const PREFIX = "TLA10530";
const EVENT_TITLE = `${PREFIX} Sunday`;

let ministryId = 0;
let teamLed = 0;
let teamOther = 0;
let posLed = 0;
let posOther = 0;
let scheduleOther = 0;
let occurrenceOther = 0;
let originalVersion = "v1";

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

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function cleanupFixtures() {
    const like = [`${PREFIX}%`];
    const bySchedule = `JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`;

    dbOk(
        `DELETE vasg FROM volunteer_assignment_vasg vasg
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID ${bySchedule}`,
        like,
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vreq.vreq_vocc_ID ${bySchedule}`,
        like,
    );
    dbOk(`DELETE vocc FROM volunteer_occurrence_vocc vocc ${bySchedule}`, like);
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vsch FROM volunteer_schedule_vsch vsch
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
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
           JOIN volunteer_team_vtem vtem ON vtem.vtem_ID = vscp.vscp_ScopeId
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vscp.vscp_ScopeType = 'team' AND vmin.vmin_Name LIKE ?`,
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
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, like);
}

function schedulePayload(teamId, name) {
    return {
        name: `${PREFIX} ${name}`,
        linkMode: "ministry",
        titleFilter: EVENT_TITLE,
        teamId,
        windowStart: isoDate(0),
        windowEnd: isoDate(28),
    };
}

before(() => {
    cy.rememberTestEnv([ADMIN_KEY, LEADER_KEY]);
    cy.useChurchTimeZone();
});

after(() => {
    cy.useHostTimeZone();
});

describe("Volunteer v2 — a Manage My Ministries team leader through the API (#10530)", () => {
    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
            originalVersion = resp.body.value ?? "v1";
        });
        cy.makePrivateAdminAPICall("POST", SETTING_URL, { value: "v2" }, 200);
        cleanupFixtures();

        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries`, { name: `${PREFIX} Hospitality` }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
        });
        cy.then(() => {
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, { name: `${PREFIX} Greeters` }, 201).then(
                (resp) => {
                    teamLed = resp.body.team.id;
                },
            );
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, { name: `${PREFIX} Ushers` }, 201).then(
                (resp) => {
                    teamOther = resp.body.team.id;
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
                teamId: teamOther,
            }, 201).then((resp) => {
                posOther = resp.body.position.id;
            });
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/scopes`, {
                personId: PERSON_LEADER,
                scopeType: "team",
                scopeId: teamLed,
            }, [200, 201]);
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${POOL_MEMBER}`, null, [200, 201]);
        });
        cy.then(() => {
            const first = (7 - new Date().getDay()) % 7 || 7;
            [0, 7].forEach((week) => {
                const day = isoDate(first + week);
                dbOk(
                    `INSERT INTO events_event (event_type, event_title, event_desc, event_text, event_start, event_end, inactive, event_ministry_id)
                     VALUES (1, ?, '', '', ?, ?, 0, ?)`,
                    [EVENT_TITLE, `${day} 09:00:00`, `${day} 10:00:00`, ministryId],
                );
            });
        });
        cy.then(() => {
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`, schedulePayload(teamOther, "Ushers"), 201).then(
                (resp) => {
                    scheduleOther = resp.body.schedule.id;
                },
            );
        });
        cy.then(() => {
            dbOk(`SELECT vocc_ID FROM volunteer_occurrence_vocc WHERE vocc_vsch_ID = ? ORDER BY vocc_ID LIMIT 1`, [
                scheduleOther,
            ]).then((rows) => {
                occurrenceOther = rows[0].vocc_ID;
            });
        });
    });

    after(() => {
        cleanupFixtures();
        cy.makePrivateAdminAPICall("POST", SETTING_URL, { value: originalVersion }, 200);
    });

    describe("Their own team", () => {
        it("Reads the team, its grid and its schedules", () => {
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/teams/${teamLed}`).then((resp) => {
                expect(resp.body.positions.map((p) => p.id)).to.deep.eq([posLed]);
            });
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/teams/${teamLed}/qualification-matrix`).then((resp) => {
                expect(resp.body.positions.map((p) => p.id)).to.deep.eq([posLed]);
            });
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/teams/${teamLed}/schedules`);
        });

        it("Creates and edits a position, and qualifies a volunteer for it", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
                name: `${PREFIX} Welcome Desk`,
                teamId: teamLed,
            }, 201).then((resp) => {
                api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/positions/${resp.body.position.id}`, {
                    name: `${PREFIX} Welcome Table`,
                });
            });
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/positions/${posLed}/qualifications`, { personId: POOL_MEMBER }, 201);
        });

        it("Creates a schedule, and lists only their own team's dates for the ministry", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`, schedulePayload(teamLed, "Greeters"), 201).then(
                (resp) => {
                    expect(resp.body.generated.created).to.be.greaterThan(0);
                },
            );
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/occurrences?ministryId=${ministryId}&from=${isoDate(0)}&to=${isoDate(28)}`).then(
                (resp) => {
                    expect(resp.body.occurrences.length).to.be.greaterThan(0);
                    expect(resp.body.occurrences.map((o) => o.teamId)).to.not.include(teamOther);
                },
            );
        });
    });

    describe("Another team's records", () => {
        it("Is refused the team, its grid and its schedules", () => {
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/teams/${teamOther}`, null, 403);
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/teams/${teamOther}/qualification-matrix`, null, 403);
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/teams/${teamOther}/schedules`, null, 403);
        });

        it("Is refused its positions, schedules and dates", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
                name: `${PREFIX} Balcony`,
                teamId: teamOther,
            }, 403);
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/positions/${posOther}`, { name: `${PREFIX} Renamed` }, 403);
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/positions/${posOther}/qualifications`, { personId: POOL_MEMBER }, 403);
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`, schedulePayload(teamOther, "Ushers 2"), 403);
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/schedules/${scheduleOther}`, { name: `${PREFIX} Renamed` }, 403);
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/occurrences/${occurrenceOther}/staffing`, null, 403);
            api(LEADER_KEY, "DELETE", `${VOLUNTEER_URL}/occurrences/${occurrenceOther}`, null, 403);
        });
    });

    describe("Ministry-wide actions", () => {
        it("Is refused the ministry document and its ministry-wide lists", () => {
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 403);
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/ministries/${ministryId}/qualification-matrix`, null, 403);
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`, null, 403);
            api(LEADER_KEY, "GET", `${VOLUNTEER_URL}/ministries/${ministryId}/events`, null, 403);
        });

        it("Is refused renaming, deactivating or deleting the ministry", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}`, { name: `${PREFIX} Renamed` }, 403);
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}`, { active: false }, 403);
            api(LEADER_KEY, "DELETE", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 403);
        });

        it("Is refused creating a team, or renaming or deleting their own", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, { name: `${PREFIX} New` }, 403);
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamLed}`, { name: `${PREFIX} Renamed` }, 403);
            api(LEADER_KEY, "DELETE", `${VOLUNTEER_URL}/teams/${teamLed}`, null, 403);
        });

        it("Is refused the pool, Remove Volunteer and scopes", () => {
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${OUTSIDER}`, null, 403);
            api(LEADER_KEY, "DELETE", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${POOL_MEMBER}`, null, 403);
            api(LEADER_KEY, "DELETE", `${VOLUNTEER_URL}/ministries/${ministryId}/volunteers/${POOL_MEMBER}`, null, 403);
            api(LEADER_KEY, "POST", `${VOLUNTEER_URL}/scopes`, {
                personId: OUTSIDER,
                scopeType: "team",
                scopeId: teamLed,
            }, 403);
        });

        it("Wrote nothing it was refused", () => {
            dbOk(`SELECT vmin_Name, vmin_Active FROM volunteer_ministry_vmin WHERE vmin_ID = ?`, [ministryId]).then((rows) => {
                expect(rows[0].vmin_Name).to.eq(`${PREFIX} Hospitality`);
                expect(Number(rows[0].vmin_Active)).to.eq(1);
            });
            dbOk(`SELECT vtem_ID FROM volunteer_team_vtem WHERE vtem_vmin_ID = ? ORDER BY vtem_ID`, [ministryId]).then((rows) => {
                expect(rows.map((r) => r.vtem_ID)).to.include.members([teamLed, teamOther]);
            });
            dbOk(`SELECT vpos_Name FROM volunteer_position_vpos WHERE vpos_ID = ?`, [posOther]).then((rows) => {
                expect(rows[0].vpos_Name).to.eq(`${PREFIX} Aisle`);
            });
        });
    });
});
