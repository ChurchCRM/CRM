/// <reference types="cypress" />

/**
 * Volunteer v2 — the member self-service surface (#9712, epic #9701).
 *
 * Normative sections of `.agents/skills/churchcrm/volunteer-v2-design.md`:
 * §3.3.3 (the member API — `/me/opportunities`, `/me/signup`,
 * `/me/qualifications`, and the substitute picker S5 needs), §4.7 (the
 * `AuthMiddleware` exemption that makes an EditSelf-exclusive user able to
 * reach any of it at all), §4.8 (the negatives) and §6.6 (the idempotency
 * recipes).
 *
 * **The whole point of this spec is that the acting person is the session.**
 * Person 99 (`selfedit.api.key`) is the D14 volunteer persona — EditSelf-
 * exclusive, the least-authority account that can still log in. Every
 * assertion below is made through `cy.makePrivateEditSelfAPICall()`'s key, so
 * "unauthorized person IDs cannot be substituted into requests" is proven the
 * only way it can be proven from outside: by sending someone else's id and
 * observing that the server used the session's.
 *
 * The fixture is built through the real setup (#9715/#9707) and schedule
 * (#9708) APIs as admin. Two things have no API and are inserted directly,
 * exactly as `private.volunteer.assignment.spec.js` does: pool-group
 * membership (every `/api/groups` write needs the global Manage Groups flag,
 * §4.6) and the single PAST occurrence the I5 negative needs, which the
 * generate endpoint would never produce.
 *
 * Cleanup runs in `before` as well as `after` (cypress-testing.md): an `after`
 * hook does not run when the runner crashes mid-spec. Deletion order is
 * FK-safe.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const SELFEDIT_KEY = "selfedit.api.key";
const SELFEDIT_NOTES_KEY = "selfedit.plus.notes.api.key";
const PLAINAUTH_KEY = "plainauth.api.key";

const VOLUNTEER_URL = "/api/volunteer";

/** EditSelf-exclusive — THE volunteer persona (D14, §6.4). */
const PERSON_VOLUNTEER = 99;
/** EditSelf + Notes — the second volunteer, the substitute counterparty. */
const PERSON_SUBSTITUTE = 100;
/** Notes only, no volunteer rights and no qualifications — the empty/negative case. */
const PERSON_PLAIN = 900;

/** Seeded members of group 1 "Angels class" (seed.sql person2group2role_p2g2r). */
const POOL_MEMBER_A = 8;
const POOL_MEMBER_B = 9;

const CHURCH_SERVICE_TYPE = 1; // seed.sql — weekly, Sunday, 10:30

const FIXTURE_PREFIX = "SELF9712";
const EVENT_TITLE = `${FIXTURE_PREFIX} Hospitality Service`;

let ministryId = 0;
let teamId = 0;
let posDoor = 0; // min 1 / max 2 — room for two, so capacity is testable
let posCoffee = 0; // min 1 / max 1 — fills after one signup
let posSound = 0; // person 99 is deliberately NOT qualified — the I2 case
let scheduleId = 0;
let occurrenceOne = 0;
let occurrenceTwo = 0;
let occurrencePast = 0;
let originalVersion = "v1";
let seriesStart = "";
let seriesEnd = "";

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

/** `YYYY-MM-DD`, `offsetDays` from today. */
function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Days from today to the next occurrence of `dow` (0 = Sunday). Never 0. */
function daysToNext(dow) {
    const today = new Date().getDay();
    return ((dow - today + 7) % 7) || 7;
}

function createMinistry(name) {
    return api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries`, {
        name: `${FIXTURE_PREFIX} ${name}`,
        description: "volunteer v2 self-service fixture",
    }, 201).then((resp) => resp.body.ministry.id);
}

function createTeam(id, name) {
    return api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${id}/teams`, {
        name: `${FIXTURE_PREFIX} ${name}`,
        description: "volunteer v2 self-service fixture",
    }, 201).then((resp) => resp.body.team.id);
}

function createPosition(name, order) {
    return api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
        name: `${FIXTURE_PREFIX} ${name}`,
        description: "volunteer v2 self-service fixture",
        teamId,
        order,
    }, 201).then((resp) => resp.body.position.id);
}

function qualify(positionId, personId) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/positions/${positionId}/qualifications`,
        { personId, notes: "" },
        201,
    );
}

function upsertRequirement(positionId, minCount, maxCount) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
        { positionId, minCount, maxCount },
        [200, 201],
    );
}

/** The member opportunity list, as the volunteer persona sees it. */
function myOpportunities(key = SELFEDIT_KEY, query = "") {
    return api(
        key,
        "GET",
        `${VOLUNTEER_URL}/me/opportunities?from=${seriesStart}&to=${seriesEnd}${query}`,
    ).then((resp) => resp.body.opportunities);
}

function signup(occurrenceId, positionId, expected = 201, extra = {}) {
    return api(
        SELFEDIT_KEY,
        "POST",
        `${VOLUNTEER_URL}/me/signup`,
        { occurrenceId, positionId, ...extra },
        expected,
    );
}

function myAssignments(key = SELFEDIT_KEY) {
    return api(key, "GET", `${VOLUNTEER_URL}/me/assignments`).then(
        (resp) => resp.body.assignments,
    );
}

function outboxRows(type, personId) {
    return dbOk(
        `SELECT vntf_ID, vntf_DedupeKey, vntf_Status, vntf_Type, vntf_vasg_ID
           FROM volunteer_notification_vntf
          WHERE vntf_Type = ? AND vntf_per_ID = ?`,
        [type, personId],
    );
}

/** Remove every assignment-side row this spec could have made, children first. */
function cleanupWorkflowRows() {
    const scoped = `
        JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
        JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
        JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
       WHERE vmin.vmin_Name LIKE ?`;

    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vntf.vntf_vasg_ID
           ${scoped}`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vntf.vntf_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vswp FROM volunteer_swap_vswp vswp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vswp.vswp_vasg_ID
           ${scoped}`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vrsp FROM volunteer_response_vrsp vrsp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vrsp.vrsp_vasg_ID
           ${scoped}`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `UPDATE volunteer_assignment_vasg vasg ${scoped.replace("WHERE", "SET vasg.vasg_Replaces_vasg_ID = NULL WHERE")}`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(`DELETE vasg FROM volunteer_assignment_vasg vasg ${scoped}`, [
        `${FIXTURE_PREFIX}%`,
    ]);
}

function cleanupFixtures() {
    cleanupWorkflowRows();

    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vreq.vreq_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vocc FROM volunteer_occurrence_vocc vocc
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vsch FROM volunteer_schedule_vsch vsch
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vqal FROM volunteer_qualification_vqal vqal
           JOIN volunteer_position_vpos vpos ON vpos.vpos_ID = vqal.vqal_vpos_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vpos FROM volunteer_position_vpos vpos
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    // D19: the pool is the ministry's own Group, and `grp_ministry_id` is ON DELETE
    // SET NULL — so the group and its memberships go BEFORE the ministry row, or the
    // installation is left with an orphan group nobody recognises.
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r
           JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE g FROM group_grp g
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    // #9869: a ministry created through the API now comes with its own calendar,
    // named after the ministry. `calendars.ministry_id` is ON DELETE SET NULL, so
    // deleting the ministry row directly would leave the calendar behind as an
    // unowned church calendar. It goes first, matched on the same prefix.
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, [
        `${FIXTURE_PREFIX}%`,
    ]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [
        `${FIXTURE_PREFIX}%`,
    ]);
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [`${EVENT_TITLE}%`]);
}

// ── fixture ────────────────────────────────────────────────────────────────

before(() => {
    cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
        originalVersion = resp.body.value ?? resp.body.Value ?? "v1";
    });
    setVersion("v2");

    cleanupFixtures();

    createMinistry("Hospitality").then((id) => {
        ministryId = id;
    });

    cy.then(() => {
        createTeam(ministryId, "Greeters").then((id) => {
            teamId = id;
        });
    });

    cy.then(() => {
        createPosition("Door", 1).then((id) => {
            posDoor = id;
        });
        createPosition("Coffee", 2).then((id) => {
            posCoffee = id;
        });
        createPosition("Sound", 3).then((id) => {
            posSound = id;
        });
    });

    // D19: the ministry came with its own pool Group, empty — both volunteer
    // personas go in through the API.
    cy.then(() => {
        for (const personId of [PERSON_VOLUNTEER, PERSON_SUBSTITUTE]) {
            api(
                ADMIN_KEY,
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`,
                null,
                [200, 201],
            );
        }
    });

    cy.then(() => {
        qualify(posDoor, PERSON_VOLUNTEER);
        qualify(posCoffee, PERSON_VOLUNTEER);
        // Sound is deliberately left unqualified for person 99.
        qualify(posSound, POOL_MEMBER_A);
        qualify(posDoor, PERSON_SUBSTITUTE);
        qualify(posDoor, POOL_MEMBER_A);
        qualify(posDoor, POOL_MEMBER_B);
        qualify(posCoffee, POOL_MEMBER_A);
    });

    cy.then(() => {
        seriesStart = isoDate(daysToNext(0));
        seriesEnd = isoDate(daysToNext(0) + 14);

        api(ADMIN_KEY, "POST", "/api/events/repeat", {
            Title: EVENT_TITLE,
            Type: CHURCH_SERVICE_TYPE,
            StartTime: "10:30:00",
            EndTime: "11:45:00",
            RecurType: "weekly",
            RecurDOW: "Sunday",
            RangeStart: seriesStart,
            RangeEnd: seriesEnd,
        }, 200);
    });

    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`, {
            name: `${FIXTURE_PREFIX} Hospitality — Sunday`,
            linkMode: "event_type",
            eventTypeId: CHURCH_SERVICE_TYPE,
            titleFilter: EVENT_TITLE,
            windowStart: seriesStart,
            teamId,
        }, 201).then((resp) => {
            scheduleId = resp.body.schedule.id;
        });
    });

    cy.then(() => {
        upsertRequirement(posDoor, 1, 2);
        upsertRequirement(posCoffee, 1, 1);
        upsertRequirement(posSound, 1, 1);
    });

    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`, {
            through: seriesEnd,
        }, 200);
    });

    cy.then(() => {
        api(
            ADMIN_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryId}`,
        ).then((resp) => {
            const rows = resp.body.occurrences;
            expect(rows.length).to.be.greaterThan(1);
            occurrenceOne = rows[0].id;
            occurrenceTwo = rows[1].id;
        });
    });

    // The I5 "past occurrence" negative. `POST /generate` will never produce one
    // and the API refuses to assign to it, so it is inserted directly — the same
    // exception `private.volunteer.assignment.spec.js` documents.
    cy.then(() => {
        dbOk(
            `INSERT INTO volunteer_occurrence_vocc
               (vocc_vsch_ID, vocc_event_id, vocc_OccurrenceDate, vocc_StartDateTime,
                vocc_EndDateTime, vocc_Status, vocc_GeneratedDate)
             VALUES (?, NULL, ?, ?, ?, 'scheduled', NOW())`,
            [
                scheduleId,
                isoDate(-21),
                `${isoDate(-21)} 10:30:00`,
                `${isoDate(-21)} 11:45:00`,
            ],
        );
        dbOk(
            `SELECT vocc_ID FROM volunteer_occurrence_vocc
              WHERE vocc_vsch_ID = ? AND vocc_OccurrenceDate = ?`,
            [scheduleId, isoDate(-21)],
        ).then((rows) => {
            occurrencePast = rows[0].vocc_ID;
        });
    });
});

after(() => {
    cleanupFixtures();
    setVersion(originalVersion);
});

// ── GET /me/qualifications (§3.3.3) ────────────────────────────────────────

describe("Volunteer v2 member API — GET /me/qualifications", () => {
    it("lists what the SESSION person is qualified for, with readable names", () => {
        api(SELFEDIT_KEY, "GET", `${VOLUNTEER_URL}/me/qualifications`).then((resp) => {
            const mine = resp.body.qualifications.filter((q) =>
                String(q.positionName ?? "").startsWith(FIXTURE_PREFIX),
            );
            const names = mine.map((q) => q.positionName);
            expect(names).to.include(`${FIXTURE_PREFIX} Door`);
            expect(names).to.include(`${FIXTURE_PREFIX} Coffee`);
            // Person 99 is not qualified for Sound, and a read-only endpoint can
            // never say otherwise — volunteers cannot grant themselves anything.
            expect(names).to.not.include(`${FIXTURE_PREFIX} Sound`);
            mine.forEach((q) => {
                expect(q.ministryName).to.contain(FIXTURE_PREFIX);
                expect(q.positionId).to.be.a("number");
            });
        });
    });

    it("ignores a personId in the query string — the session decides (§3.3.3)", () => {
        api(
            SELFEDIT_KEY,
            "GET",
            `${VOLUNTEER_URL}/me/qualifications?personId=${POOL_MEMBER_A}`,
        ).then((resp) => {
            const names = resp.body.qualifications.map((q) => q.positionName);
            // POOL_MEMBER_A holds Sound; person 99 does not. If the parameter had
            // been honoured, Sound would be here.
            expect(names).to.not.include(`${FIXTURE_PREFIX} Sound`);
            expect(names).to.include(`${FIXTURE_PREFIX} Door`);
        });
    });

    it("answers an empty list for a person with no qualifications", () => {
        api(PLAINAUTH_KEY, "GET", `${VOLUNTEER_URL}/me/qualifications`).then((resp) => {
            const mine = resp.body.qualifications.filter((q) =>
                String(q.positionName ?? "").startsWith(FIXTURE_PREFIX),
            );
            expect(mine).to.have.length(0);
        });
    });
});

// ── GET /me/opportunities (§3.3.3, §5.6 S6) ────────────────────────────────

describe("Volunteer v2 member API — GET /me/opportunities", () => {
    beforeEach(() => {
        cleanupWorkflowRows();
    });

    it("lists only positions the session person is qualified for", () => {
        myOpportunities().then((rows) => {
            const mine = rows.filter((r) => r.occurrenceId === occurrenceOne);
            const positions = mine.map((r) => r.positionName);
            expect(positions).to.include(`${FIXTURE_PREFIX} Door`);
            expect(positions).to.include(`${FIXTURE_PREFIX} Coffee`);
            expect(positions).to.not.include(`${FIXTURE_PREFIX} Sound`);
        });
    });

    it("speaks the volunteer's language: date, ministry, position, how many are needed", () => {
        myOpportunities().then((rows) => {
            const row = rows.find(
                (r) => r.occurrenceId === occurrenceOne && r.positionId === posDoor,
            );
            expect(row, "the Door opportunity").to.not.be.undefined;
            expect(row.ministryName).to.contain(FIXTURE_PREFIX);
            expect(row.teamName).to.contain(FIXTURE_PREFIX);
            expect(row.start, "start comes from the linked event (D4)").to.contain("10:30:00");
            expect(row.openCount).to.eq(2);
        });
    });

    it("never lists a PAST occurrence — signing up for one is impossible (I5)", () => {
        myOpportunities().then((rows) => {
            expect(rows.map((r) => r.occurrenceId)).to.not.include(occurrencePast);
        });
    });

    it("drops a position once it is full, and shows it again when it is not", () => {
        // Coffee is Min 1 / Max 1. One accepted assignment closes it entirely.
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/assignments`, {
            positionId: posCoffee,
            personId: POOL_MEMBER_A,
        }, 201).then((resp) => {
            const assignmentId = resp.body.assignment.id;

            myOpportunities().then((rows) => {
                const coffee = rows.filter(
                    (r) => r.occurrenceId === occurrenceOne && r.positionId === posCoffee,
                );
                expect(coffee, "Coffee is full").to.have.length(0);
            });

            // Cancel it; the slot reopens with no gap-reopening code anywhere.
            api(ADMIN_KEY, "DELETE", `${VOLUNTEER_URL}/assignments/${assignmentId}`, null, [200, 204]);

            myOpportunities().then((rows) => {
                const coffee = rows.filter(
                    (r) => r.occurrenceId === occurrenceOne && r.positionId === posCoffee,
                );
                expect(coffee, "Coffee is open again").to.have.length(1);
            });
        });
    });

    it("never offers a position the caller already holds on that occurrence", () => {
        signup(occurrenceOne, posCoffee).then(() => {
            myOpportunities().then((rows) => {
                const coffee = rows.filter(
                    (r) => r.occurrenceId === occurrenceOne && r.positionId === posCoffee,
                );
                expect(coffee).to.have.length(0);
            });
        });
    });

    it("flags the §5.6 same-occurrence warning rather than hiding the row (D16/I7)", () => {
        signup(occurrenceOne, posCoffee).then(() => {
            myOpportunities().then((rows) => {
                const door = rows.find(
                    (r) => r.occurrenceId === occurrenceOne && r.positionId === posDoor,
                );
                expect(door, "Door is still offered").to.not.be.undefined;
                expect(door.alreadyServing, "already serving here").to.eq(true);
                expect(door.alreadyServingPositionNames).to.include(`${FIXTURE_PREFIX} Coffee`);
            });
        });
    });

    it("is empty for a person with no qualifications at all (§5.6 empty state)", () => {
        myOpportunities(PLAINAUTH_KEY).then((rows) => {
            expect(rows).to.have.length(0);
        });
    });

    it("ignores a personId in the query string — the session decides", () => {
        myOpportunities(SELFEDIT_KEY, `&personId=${POOL_MEMBER_A}`).then((rows) => {
            // POOL_MEMBER_A is qualified for Sound; person 99 is not. A honoured
            // parameter would leak Sound into this list.
            expect(rows.map((r) => r.positionName)).to.not.include(`${FIXTURE_PREFIX} Sound`);
        });
    });
});

// ── POST /me/signup (§3.3.3, §4.8) ─────────────────────────────────────────

describe("Volunteer v2 member API — POST /me/signup", () => {
    beforeEach(() => {
        cleanupWorkflowRows();
    });

    it("creates an ACCEPTED self-signup row for the session person", () => {
        signup(occurrenceOne, posDoor).then((resp) => {
            const assignment = resp.body.assignment;
            expect(assignment.personId).to.eq(PERSON_VOLUNTEER);
            expect(assignment.status).to.eq("accepted");
            expect(assignment.source).to.eq("self_signup");
            expect(assignment.occurrenceId).to.eq(occurrenceOne);
            expect(assignment.positionId).to.eq(posDoor);
        });
    });

    it("enqueues exactly one signup_confirm outbox row, and no second on a retry", () => {
        signup(occurrenceOne, posDoor).then((resp) => {
            const assignmentId = resp.body.assignment.id;
            outboxRows("signup_confirm", PERSON_VOLUNTEER).then((rows) => {
                const mine = rows.filter((r) => Number(r.vntf_vasg_ID) === Number(assignmentId));
                expect(mine, "one signup_confirm").to.have.length(1);
                expect(mine[0].vntf_Status).to.eq("pending");
            });

            // I1: a second signup is a conflict, and must not produce a second row.
            signup(occurrenceOne, posDoor, 409);
            outboxRows("signup_confirm", PERSON_VOLUNTEER).then((rows) => {
                const mine = rows.filter((r) => Number(r.vntf_vasg_ID) === Number(assignmentId));
                expect(mine, "still one signup_confirm").to.have.length(1);
            });
        });
    });

    it("shows up immediately on GET /me/assignments", () => {
        signup(occurrenceOne, posDoor).then((resp) => {
            const assignmentId = resp.body.assignment.id;
            myAssignments().then((rows) => {
                const mine = rows.find((a) => a.id === assignmentId);
                expect(mine, "the new commitment").to.not.be.undefined;
                expect(mine.status).to.eq("accepted");
                expect(mine.positionName).to.eq(`${FIXTURE_PREFIX} Door`);
            });
        });
    });

    it("403s on a position the caller is not qualified for, whatever the UI offered", () => {
        signup(occurrenceOne, posSound, 403);
    });

    it("409s when the requirement is already at capacity (server-side check)", () => {
        // Door is Min 1 / Max 2. Fill both slots with other people first.
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/assignments`, {
            positionId: posDoor,
            personId: POOL_MEMBER_A,
        }, 201);
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/assignments`, {
            positionId: posDoor,
            personId: POOL_MEMBER_B,
        }, 201);

        signup(occurrenceOne, posDoor, 409);
    });

    it("409s on a cancelled occurrence (I5)", () => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/occurrences/${occurrenceTwo}/status`, {
            status: "cancelled",
        }, 200);

        signup(occurrenceTwo, posDoor, 409);

        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/occurrences/${occurrenceTwo}/status`, {
            status: "scheduled",
        }, 200);
    });

    it("409s on a past occurrence (I5)", () => {
        signup(occurrencePast, posDoor, 409);
    });

    it("404s on an occurrence or position that does not exist", () => {
        signup(99999999, posDoor, 404);
        signup(occurrenceOne, 99999999, 404);
    });

    it("ignores a personId in the BODY — the row belongs to the session person", () => {
        signup(occurrenceOne, posDoor, 201, { personId: POOL_MEMBER_A }).then((resp) => {
            expect(resp.body.assignment.personId).to.eq(PERSON_VOLUNTEER);
        });
        dbOk(
            `SELECT vasg_per_ID FROM volunteer_assignment_vasg
              WHERE vasg_vocc_ID = ? AND vasg_vpos_ID = ?`,
            [occurrenceOne, posDoor],
        ).then((rows) => {
            expect(rows).to.have.length(1);
            expect(Number(rows[0].vasg_per_ID)).to.eq(PERSON_VOLUNTEER);
        });
    });

    it("403s for a person with no qualification for anything", () => {
        api(
            PLAINAUTH_KEY,
            "POST",
            `${VOLUNTEER_URL}/me/signup`,
            { occurrenceId: occurrenceOne, positionId: posDoor },
            403,
        );
    });
});

// ── Decline reopens what self-signup filled (§5.6, AC "declining reopens") ──

describe("Volunteer v2 member API — decline reopens the opportunity", () => {
    beforeEach(() => {
        cleanupWorkflowRows();
    });

    it("a declined self-signup puts the slot back on my opportunities list", () => {
        signup(occurrenceOne, posCoffee).then((resp) => {
            const assignmentId = resp.body.assignment.id;

            myOpportunities().then((rows) => {
                const coffee = rows.filter(
                    (r) => r.occurrenceId === occurrenceOne && r.positionId === posCoffee,
                );
                expect(coffee, "taken by me").to.have.length(0);
            });

            api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/me/assignments/${assignmentId}/respond`, {
                response: "declined",
                comment: "Out of town",
            }, 200).then((r) => {
                expect(r.body.assignment.status).to.eq("declined");
            });

            myOpportunities().then((rows) => {
                const coffee = rows.filter(
                    (r) => r.occurrenceId === occurrenceOne && r.positionId === posCoffee,
                );
                expect(coffee, "open again").to.have.length(1);
            });
        });
    });
});

// ── GET /me/assignments/{id}/substitutes (§5.6 "Find a sub", CR1) ──────────

describe("Volunteer v2 member API — the substitute picker", () => {
    beforeEach(() => {
        cleanupWorkflowRows();
    });

    it("offers qualified people, never the caller, never someone already on that position", () => {
        // POOL_MEMBER_A takes the second Door slot, so they cannot be a substitute
        // for the first one — proposeSubstitute would refuse them.
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/assignments`, {
            positionId: posDoor,
            personId: POOL_MEMBER_A,
        }, 201);

        signup(occurrenceOne, posDoor).then((resp) => {
            const assignmentId = resp.body.assignment.id;

            api(
                SELFEDIT_KEY,
                "GET",
                `${VOLUNTEER_URL}/me/assignments/${assignmentId}/substitutes`,
            ).then((r) => {
                const ids = r.body.people.map((p) => p.personId);
                expect(ids, "myself").to.not.include(PERSON_VOLUNTEER);
                expect(ids, "already on this position").to.not.include(POOL_MEMBER_A);
                expect(ids, "qualified and free").to.include(PERSON_SUBSTITUTE);
                expect(ids).to.include(POOL_MEMBER_B);
                r.body.people.forEach((p) => {
                    expect(p.displayName).to.be.a("string").and.not.be.empty;
                });
            });
        });
    });

    it("filters by ?q= so a long roster is searchable", () => {
        signup(occurrenceOne, posDoor).then((resp) => {
            const assignmentId = resp.body.assignment.id;
            api(
                SELFEDIT_KEY,
                "GET",
                `${VOLUNTEER_URL}/me/assignments/${assignmentId}/substitutes?q=zzzznobody`,
            ).then((r) => {
                expect(r.body.people).to.have.length(0);
            });
        });
    });

    it("403s on someone else's assignment — never 404 (§4.8)", () => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/assignments`, {
            positionId: posDoor,
            personId: POOL_MEMBER_A,
        }, 201).then((resp) => {
            const otherId = resp.body.assignment.id;
            api(
                SELFEDIT_KEY,
                "GET",
                `${VOLUNTEER_URL}/me/assignments/${otherId}/substitutes`,
                null,
                403,
            );
        });
    });

    it("feeds a proposal the server then accepts — the picker cannot offer an ineligible person", () => {
        signup(occurrenceOne, posDoor).then((resp) => {
            const assignmentId = resp.body.assignment.id;
            api(
                SELFEDIT_KEY,
                "GET",
                `${VOLUNTEER_URL}/me/assignments/${assignmentId}/substitutes`,
            ).then((r) => {
                const candidate = r.body.people[0].personId;
                api(
                    SELFEDIT_KEY,
                    "POST",
                    `${VOLUNTEER_URL}/me/assignments/${assignmentId}/propose-substitute`,
                    { personId: candidate, comment: "Can you cover?" },
                    201,
                ).then((p) => {
                    expect(p.body.swap.status).to.eq("proposed");
                });
            });
        });
    });
});

// ── §4.8 negatives on the member surface ───────────────────────────────────

describe("Volunteer v2 member API — §4.8 negatives", () => {
    beforeEach(() => {
        cleanupWorkflowRows();
    });

    it("403s, never 404s, when answering for someone else's assignment", () => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/assignments`, {
            positionId: posDoor,
            personId: POOL_MEMBER_A,
        }, 201).then((resp) => {
            const otherId = resp.body.assignment.id;
            api(
                SELFEDIT_KEY,
                "POST",
                `${VOLUNTEER_URL}/me/assignments/${otherId}/respond`,
                { response: "accepted" },
                403,
            );
        });
    });

    it("still refuses the coordinator surface to an EditSelf-exclusive volunteer", () => {
        api(SELFEDIT_KEY, "GET", `${VOLUNTEER_URL}/ministries`, null, 403);
    });

    it("302s the retired member MVC URLs, which are no longer exempt (§4.7, #9867)", () => {
        // The pages moved into the Member Portal, so `isLimitedAccessAllowedPath()`
        // names only `/api/volunteer/me/` now. An EditSelf-exclusive caller asking
        // for either old URL is redirected — by AuthMiddleware first, and by the
        // volunteer module's own forwarding route for anyone it lets through.
        api(SELFEDIT_KEY, "GET", "/volunteer/my-schedule", null, [302, 403]);
        api(SELFEDIT_KEY, "GET", "/volunteer/opportunities", null, [302, 403]);
    });

    it("still reaches the member API the exemption does name (§4.7)", () => {
        api(SELFEDIT_KEY, "GET", `${VOLUNTEER_URL}/me/assignments`, null, 200);
        api(SELFEDIT_KEY, "GET", `${VOLUNTEER_URL}/me/permissions`, null, 200);
    });

    it("still redirects an EditSelf-exclusive volunteer away from the coordinator MVC area", () => {
        // 403 from the role middleware, or a redirect to the access-denied page —
        // never the dashboard itself.
        api(SELFEDIT_KEY, "GET", "/volunteer/dashboard", null, [302, 403]);
    });

    it("401s the whole member surface with no credentials at all", () => {
        cy.request({
            method: "GET",
            url: `${VOLUNTEER_URL}/me/opportunities`,
            failOnStatusCode: false,
            withCredentials: false,
        }).then((resp) => {
            expect(resp.status).to.be.oneOf([401, 403]);
        });
    });

    it("403s every member route when the rollout flag is v1", () => {
        setVersion("v1");
        api(SELFEDIT_KEY, "GET", `${VOLUNTEER_URL}/me/opportunities`, null, 403);
        api(SELFEDIT_KEY, "GET", `${VOLUNTEER_URL}/me/qualifications`, null, 403);
        api(
            SELFEDIT_KEY,
            "POST",
            `${VOLUNTEER_URL}/me/signup`,
            { occurrenceId: occurrenceOne, positionId: posDoor },
            403,
        );
        setVersion("v2");
    });
});

// ── The second volunteer, reached the same way ─────────────────────────────

describe("Volunteer v2 member API — a second volunteer sees only their own", () => {
    beforeEach(() => {
        cleanupWorkflowRows();
    });

    it("person 100's opportunities and assignments are person 100's", () => {
        signup(occurrenceOne, posDoor).then((resp) => {
            const mineId = resp.body.assignment.id;

            myAssignments(SELFEDIT_NOTES_KEY).then((rows) => {
                expect(rows.map((a) => a.id)).to.not.include(mineId);
            });

            // Person 100 is qualified for Door only.
            myOpportunities(SELFEDIT_NOTES_KEY).then((rows) => {
                const names = [
                    ...new Set(
                        rows
                            .map((r) => r.positionName)
                            .filter((n) => String(n).startsWith(FIXTURE_PREFIX)),
                    ),
                ];
                expect(names).to.deep.eq([`${FIXTURE_PREFIX} Door`]);
            });
        });
    });
});
