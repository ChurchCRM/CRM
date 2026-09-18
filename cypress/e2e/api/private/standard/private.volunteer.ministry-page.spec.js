/// <reference types="cypress" />

/**
 * Volunteer v2 — the two API surfaces the restructured ministry page needs
 * (epic #9701, design §5.4 as amended by the product owner).
 *
 *   1. **`GET /ministries/{id}` now carries a `summary` block** — `teamCount`,
 *      `volunteerCount` (the pool Group's membership) and
 *      `unfilledPositionCount`. The last one is the interesting one: it is the sum
 *      of the open slots — `max(0, required − live)` per effective requirement —
 *      over every FUTURE, SCHEDULED occurrence of the ministry **the caller may
 *      see**. A ministry coordinator, a global manager and an administrator are
 *      counted over the whole ministry; a team leader only over the occurrences of
 *      the teams they lead, unioned, because a person may lead several teams in
 *      the same ministry. The arithmetic is `VolunteerAssignmentService::getGaps()`
 *      — the ONE gap implementation (§2.11.3) — not a second derivation.
 *
 *   2. **`DELETE /ministries/{id}/volunteers/{personId}`** — the Volunteers tab's
 *      "Remove Volunteer". One transaction: revoke every active qualification for
 *      a position of this ministry, cancel every live assignment on a still-to-come
 *      occurrence of it through the ordinary cancel path, and take them out of the
 *      pool Group. A PAST assignment is service history and is left alone. The
 *      route is ministry-level, so a team leader is refused with `403` — the page
 *      hides the menu item from them, but hiding is not security (D5).
 *
 * The fixture is one ministry with THREE teams, so "a leader of two of three" is a
 * real distinction rather than a degenerate one. Every row is built through the
 * real endpoints except the historical occurrence and assignment, which the API
 * refuses to create on purpose (I5) and which are therefore inserted with SQL —
 * the same exception `private.volunteer.assignment.spec.js` makes.
 *
 * Cleanup runs in `before` as well as `after` (cypress-testing.md): an `after` hook
 * does not run when the runner crashes mid-spec. The pool Group goes before the
 * ministry: `grp_ministry_id` is ON DELETE SET NULL, so deleting the ministry first
 * would leave its group behind as an orphan nobody can recognise.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
/** person 3 — tony.wade. Given the MINISTRY scope by this spec. */
const COORDINATOR_KEY = "user.api.key";
/** person 95 — judith.matthews. Given TEAM scopes on two of the three teams. */
const LEADER_KEY = "editrecords.api.key";

const VOLUNTEER_URL = "/api/ministries";
const MINISTRIES_URL = `${VOLUNTEER_URL}/ministries`;

const PERSON_COORDINATOR = 3;
const PERSON_LEADER = 95;
/** The volunteer this spec qualifies, assigns, pools — and then removes. */
const PERSON_VOLUNTEER = 8;
/** A second pool member, so `volunteerCount` is not 1 by accident. */
const PERSON_BYSTANDER = 9;

const PREFIX = "MINPAGE";
const MINISTRY_NAME = `${PREFIX} Childrens Ministry`;
const TEAM_ALPHA = `${PREFIX} Elementary`;
const TEAM_BETA = `${PREFIX} Nursery`;
const TEAM_GAMMA = `${PREFIX} Youth`;

let ministryId = 0;
let teamAlpha = 0;
let teamBeta = 0;
let teamGamma = 0;
let posAlpha = 0;
let posBeta = 0;
let posGamma = 0;
let schedAlpha = 0;
let occAlpha = 0;
let pastOccurrence = 0;
let pastAssignment = 0;
let futureAssignment = 0;
let originalVersion = "v1";

// ── helpers ────────────────────────────────────────────────────────────────

/** Run SQL and fail the test if the database rejected it. */
function dbOk(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(
                `Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`,
            );
        }
        return result.rows;
    });
}

function api(key, method, url, body, expectedStatus = 200) {
    return cy.makePrivateAPICall(
        Cypress.env(key),
        method,
        url,
        body,
        expectedStatus,
    );
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const DAY_NAMES = [
    "Sunday",
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday",
];

function dayNameIn(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    return DAY_NAMES[d.getDay()];
}

/**
 * One standalone weekly schedule whose window is exactly the next week, so it
 * generates EXACTLY ONE occurrence — three days out, comfortably in the future
 * whatever time of day the suite runs.
 */
function makeSchedule(name, teamId, positionId, minCount) {
    return api(
        ADMIN_KEY,
        "POST",
        `${MINISTRIES_URL}/${ministryId}/schedules`,
        {
            name,
            linkMode: "standalone",
            teamId,
            recurType: "weekly",
            recurDow: dayNameIn(3),
            startTime: "10:30",
            endTime: "11:45",
            windowStart: isoDate(0),
            windowEnd: isoDate(6),
        },
        201,
    ).then((resp) => {
        const scheduleId = resp.body.schedule.id;
        api(
            ADMIN_KEY,
            "POST",
            `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
            { positionId, minCount, maxCount: minCount },
            [200, 201],
        );
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`, {
            through: isoDate(6),
        }).then((gen) => {
            expect(gen.body.created, `${name} generated exactly one date`).to.eq(1);
        });
        return cy.wrap(scheduleId);
    });
}

/**
 * Every row this spec could have made, children first — the FK graph is deep
 * enough that order is not optional, and the pool Group has to go before the
 * ministry because `grp_ministry_id` is ON DELETE SET NULL and would otherwise
 * leave an orphan nobody can recognise (D19).
 */
function cleanupFixtures() {
    const like = [`${PREFIX}%`];
    const scopedToAssignment = `
        JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
        JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
        JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
       WHERE vmin.vmin_Name LIKE ?`;

    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vntf.vntf_vasg_ID
           ${scopedToAssignment}`,
        like,
    );
    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vntf.vntf_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vswp FROM volunteer_swap_vswp vswp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vswp.vswp_vasg_ID
           ${scopedToAssignment}`,
        like,
    );
    dbOk(
        `DELETE vrsp FROM volunteer_response_vrsp vrsp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vrsp.vrsp_vasg_ID
           ${scopedToAssignment}`,
        like,
    );
    dbOk(`DELETE vasg FROM volunteer_assignment_vasg vasg ${scopedToAssignment}`, like);
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vreq.vreq_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vocc FROM volunteer_occurrence_vocc vocc
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
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
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID IN (?, ?)`, [
        PERSON_COORDINATOR,
        PERSON_LEADER,
    ]);
    // #9869: a ministry created through the API now comes with its own calendar,
    // named after the ministry. `calendars.ministry_id` is ON DELETE SET NULL, so
    // deleting the ministry row directly would leave the calendar behind as an
    // unowned church calendar. It goes first, matched on the same prefix.
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, like);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, like);
}

/** The summary as the ministry PAGE reads it — embedded in the detail document. */
function summaryFor(key) {
    return api(key, "GET", `${MINISTRIES_URL}/${ministryId}`).then(
        (resp) => resp.body.summary,
    );
}

/**
 * The same block from the standalone endpoint, which is the only one a team
 * leader can reach: `GET /ministries/{id}` is ministry-scoped and answers 403 for
 * them, so `.../summary` authorizes itself and lets a leader ask for their own
 * narrower number.
 */
function summaryEndpointFor(key, expectedStatus = 200) {
    return api(
        key,
        "GET",
        `${MINISTRIES_URL}/${ministryId}/summary`,
        null,
        expectedStatus,
    ).then((resp) => resp.body.summary);
}

// ── fixture ────────────────────────────────────────────────────────────────

before(() => {
    cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
        originalVersion = resp.body.value ?? "v1";
    });
    setVersion("v2");
    cleanupFixtures();

    api(ADMIN_KEY, "POST", MINISTRIES_URL, { name: MINISTRY_NAME }, 201).then(
        (resp) => {
            ministryId = resp.body.ministry.id;

            // The ministry was created with one team (D18); rename it and add two.
            api(ADMIN_KEY, "GET", `${MINISTRIES_URL}/${ministryId}`).then((detail) => {
                teamAlpha = detail.body.teams[0].id;
                api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamAlpha}`, {
                    name: TEAM_ALPHA,
                });
            });
            api(
                ADMIN_KEY,
                "POST",
                `${MINISTRIES_URL}/${ministryId}/teams`,
                { name: TEAM_BETA },
                201,
            ).then((team) => {
                teamBeta = team.body.team.id;
            });
            api(
                ADMIN_KEY,
                "POST",
                `${MINISTRIES_URL}/${ministryId}/teams`,
                { name: TEAM_GAMMA },
                201,
            ).then((team) => {
                teamGamma = team.body.team.id;
            });
        },
    );

    cy.then(() => {
        api(
            ADMIN_KEY,
            "POST",
            `${MINISTRIES_URL}/${ministryId}/positions`,
            { name: `${PREFIX} Lead Teacher`, teamId: teamAlpha, order: 1 },
            201,
        ).then((p) => {
            posAlpha = p.body.position.id;
        });
        api(
            ADMIN_KEY,
            "POST",
            `${MINISTRIES_URL}/${ministryId}/positions`,
            { name: `${PREFIX} Helper`, teamId: teamBeta, order: 2 },
            201,
        ).then((p) => {
            posBeta = p.body.position.id;
        });
        api(
            ADMIN_KEY,
            "POST",
            `${MINISTRIES_URL}/${ministryId}/positions`,
            { name: `${PREFIX} Mentor`, teamId: teamGamma, order: 3 },
            201,
        ).then((p) => {
            posGamma = p.body.position.id;
        });
    });

    // Two people in the pool. Qualifying somebody adds them too (D19), so the
    // bystander is added explicitly and the volunteer arrives through both doors.
    cy.then(() => {
        api(
            ADMIN_KEY,
            "POST",
            `${MINISTRIES_URL}/${ministryId}/pool/${PERSON_BYSTANDER}`,
            null,
            [200, 201],
        );
        api(
            ADMIN_KEY,
            "POST",
            `${VOLUNTEER_URL}/positions/${posAlpha}/qualifications`,
            { personId: PERSON_VOLUNTEER },
            [200, 201],
        );
        api(
            ADMIN_KEY,
            "POST",
            `${VOLUNTEER_URL}/positions/${posBeta}/qualifications`,
            { personId: PERSON_VOLUNTEER },
            [200, 201],
        );
    });

    // Each team needs TWO people on its one occurrence, so the whole ministry is
    // short of six and a leader of two teams is short of four.
    cy.then(() => {
        makeSchedule(`${PREFIX} Elementary Sunday`, teamAlpha, posAlpha, 2).then(
            (id) => {
                schedAlpha = id;
            },
        );
        makeSchedule(`${PREFIX} Nursery Sunday`, teamBeta, posBeta, 2);
        makeSchedule(`${PREFIX} Youth Sunday`, teamGamma, posGamma, 2);
    });

    // The scope grants the two non-admin tiers are read through.
    cy.then(() => {
        api(
            ADMIN_KEY,
            "POST",
            `${VOLUNTEER_URL}/scopes`,
            { personId: PERSON_COORDINATOR, scopeType: "ministry", scopeId: ministryId },
            [200, 201],
        );
        api(
            ADMIN_KEY,
            "POST",
            `${VOLUNTEER_URL}/scopes`,
            { personId: PERSON_LEADER, scopeType: "team", scopeId: teamAlpha },
            [200, 201],
        );
        api(
            ADMIN_KEY,
            "POST",
            `${VOLUNTEER_URL}/scopes`,
            { personId: PERSON_LEADER, scopeType: "team", scopeId: teamBeta },
            [200, 201],
        );
    });

    // The future occurrence Elementary generated, and one live assignment on it.
    cy.then(() => {
        api(
            ADMIN_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${isoDate(0)}&to=${isoDate(6)}&ministryId=${ministryId}&scheduleId=${schedAlpha}`,
        ).then((resp) => {
            expect(resp.body.occurrences).to.have.length(1);
            occAlpha = resp.body.occurrences[0].id;
        });
    });

    cy.then(() => {
        api(
            ADMIN_KEY,
            "POST",
            `${VOLUNTEER_URL}/occurrences/${occAlpha}/assignments`,
            { personId: PERSON_VOLUNTEER, positionId: posAlpha },
            201,
        ).then((resp) => {
            futureAssignment = resp.body.assignment.id;
        });
    });

    // A PAST occurrence and a completed assignment on it. Both are inserted
    // directly: generation never back-fills (§2.9) and I5 refuses an assignment on
    // an occurrence that has already ended — which is exactly what this is.
    cy.then(() => {
        dbOk(
            `INSERT INTO volunteer_occurrence_vocc
                 (vocc_vsch_ID, vocc_OccurrenceDate, vocc_StartDateTime, vocc_EndDateTime,
                  vocc_Status, vocc_GeneratedDate)
             VALUES (?, ?, ?, ?, 'scheduled', NOW())`,
            [schedAlpha, isoDate(-28), `${isoDate(-28)} 10:30:00`, `${isoDate(-28)} 11:45:00`],
        ).then((rows) => {
            pastOccurrence = rows.insertId;
            dbOk(
                `INSERT INTO volunteer_assignment_vasg
                     (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                 VALUES (?, ?, ?, 'accepted', 'coordinator', NOW())`,
                [pastOccurrence, posAlpha, PERSON_VOLUNTEER],
            ).then((inserted) => {
                pastAssignment = inserted.insertId;
            });
        });
    });
});

after(() => {
    cleanupFixtures();
    setVersion(originalVersion);
});

// ── the summary block ──────────────────────────────────────────────────────

describe("Volunteer v2 — the ministry overview summary (#9701)", () => {
    it("counts teams, pool members and unfilled positions for an administrator", () => {
        summaryFor(ADMIN_KEY).then((summary) => {
            expect(summary.teamCount, "three teams").to.eq(3);
            // The volunteer arrived through the qualification, the bystander through
            // the pool route — both are members of the ministry's one Group (D19).
            expect(summary.volunteerCount, "two people in the pool").to.eq(2);
            // Three occurrences needing two each, one of the six slots filled.
            expect(summary.unfilledPositionCount).to.eq(5);
        });
    });

    it("gives a ministry coordinator the whole ministry", () => {
        summaryFor(COORDINATOR_KEY).then((summary) => {
            expect(summary.teamCount).to.eq(3);
            expect(summary.unfilledPositionCount).to.eq(5);
        });
        summaryEndpointFor(COORDINATOR_KEY).then((summary) => {
            expect(summary.unfilledPositionCount).to.eq(5);
        });
    });

    it("refuses the ministry detail to a team leader, who is not a coordinator", () => {
        // The page itself is ministry-scoped (§4.6), so the embedded summary is too.
        api(LEADER_KEY, "GET", `${MINISTRIES_URL}/${ministryId}`, null, 403);
    });

    it("gives a leader of two of the three teams only their own occurrences", () => {
        summaryEndpointFor(LEADER_KEY).then((summary) => {
            // Elementary is short one (one of its two slots is filled) and Nursery
            // is short two; Youth is somebody else's team and is not counted.
            expect(summary.unfilledPositionCount).to.eq(3);
        });
    });

    it("carries each team's leader on the team row, for the Team Leader column", () => {
        api(ADMIN_KEY, "GET", `${MINISTRIES_URL}/${ministryId}`).then((resp) => {
            const byId = {};
            for (const team of resp.body.teams) {
                byId[team.id] = team;
            }
            expect(byId[teamAlpha].leaders.map((l) => l.personId)).to.deep.eq([
                PERSON_LEADER,
            ]);
            expect(byId[teamAlpha].leaders[0].scopeId).to.be.greaterThan(0);
            expect(byId[teamGamma].leaders).to.deep.eq([]);
        });
    });
});

// ── DELETE /ministries/{id}/volunteers/{personId} ───────────────────────────

describe("Volunteer v2 — removing a volunteer from a ministry (#9701)", () => {
    it("refuses a team leader with 403", () => {
        api(
            LEADER_KEY,
            "DELETE",
            `${MINISTRIES_URL}/${ministryId}/volunteers/${PERSON_VOLUNTEER}`,
            null,
            403,
        );
    });

    it("answers 404 for a person who does not exist", () => {
        api(
            ADMIN_KEY,
            "DELETE",
            `${MINISTRIES_URL}/${ministryId}/volunteers/99999999`,
            null,
            404,
        );
    });

    it("revokes the qualifications, cancels only the future assignment, empties the pool seat", () => {
        api(
            ADMIN_KEY,
            "DELETE",
            `${MINISTRIES_URL}/${ministryId}/volunteers/${PERSON_VOLUNTEER}`,
        ).then((resp) => {
            expect(resp.body.personId).to.eq(PERSON_VOLUNTEER);
            expect(resp.body.qualifications, "both qualifications").to.eq(2);
            expect(resp.body.assignments, "only the upcoming one").to.eq(1);
            expect(resp.body.removedFromPool).to.eq(true);
        });

        // Qualifications: kept as rows, deactivated — §2.7's "revocation is
        // deactivation", so the grant history is still readable.
        dbOk(
            `SELECT vqal_Active AS active FROM volunteer_qualification_vqal
              WHERE vqal_per_ID = ? AND vqal_vpos_ID IN (?, ?)`,
            [PERSON_VOLUNTEER, posAlpha, posBeta],
        ).then((rows) => {
            expect(rows).to.have.length(2);
            for (const row of rows) {
                expect(Number(row.active)).to.eq(0);
            }
        });

        // The upcoming assignment is cancelled through the ordinary path, so the
        // row survives with an appended response rather than disappearing.
        dbOk(`SELECT vasg_Status AS status FROM volunteer_assignment_vasg WHERE vasg_ID = ?`, [
            futureAssignment,
        ]).then((rows) => {
            expect(rows[0].status).to.eq("cancelled");
        });
        dbOk(
            `SELECT COUNT(*) AS n FROM volunteer_response_vrsp WHERE vrsp_vasg_ID = ?`,
            [futureAssignment],
        ).then((rows) => {
            expect(Number(rows[0].n), "the cancel appended a response row").to.be.greaterThan(0);
        });

        // The past one is service history and is untouched.
        dbOk(`SELECT vasg_Status AS status FROM volunteer_assignment_vasg WHERE vasg_ID = ?`, [
            pastAssignment,
        ]).then((rows) => {
            expect(rows[0].status, "a past assignment is left alone").to.eq("accepted");
        });

        // And they are out of the pool Group, while the bystander is not.
        api(ADMIN_KEY, "GET", `${MINISTRIES_URL}/${ministryId}/pool`).then((resp) => {
            const ids = resp.body.members.map((m) => m.personId);
            expect(ids).to.not.include(PERSON_VOLUNTEER);
            expect(ids).to.include(PERSON_BYSTANDER);
        });
    });

    it("re-opens the slot the cancelled assignment held", () => {
        summaryFor(ADMIN_KEY).then((summary) => {
            expect(summary.volunteerCount, "one pool member left").to.eq(1);
            // Six slots, none of them filled any more.
            expect(summary.unfilledPositionCount).to.eq(6);
        });
    });

    it("is idempotent: a second removal changes nothing and still answers 200", () => {
        api(
            ADMIN_KEY,
            "DELETE",
            `${MINISTRIES_URL}/${ministryId}/volunteers/${PERSON_VOLUNTEER}`,
        ).then((resp) => {
            expect(resp.body.qualifications).to.eq(0);
            expect(resp.body.assignments).to.eq(0);
            expect(resp.body.removedFromPool).to.eq(false);
        });
    });

    it("leaves the ministry create endpoint exactly as it was", () => {
        // The "New ministry" modal that replaced the wizard posts the same body the
        // wizard did, to the same route, and is still manager-only.
        api(
            ADMIN_KEY,
            "POST",
            MINISTRIES_URL,
            { name: `${PREFIX} Modal Created`, description: "from the new modal" },
            201,
        ).then((resp) => {
            expect(resp.body.ministry.name).to.eq(`${PREFIX} Modal Created`);
            expect(resp.body.ministry.teamCount, "created with its first team").to.eq(1);
            expect(resp.body.poolGroupId, "created with its pool Group").to.be.greaterThan(0);
        });
    });
});
