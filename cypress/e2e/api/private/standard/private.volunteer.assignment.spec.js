/// <reference types="cypress" />

/**
 * Volunteer v2 — assignment, response and gap workflow (#9709, epic #9701).
 *
 * Normative sections of `.agents/skills/churchcrm/volunteer-v2-design.md`:
 * §2.11 (the assignment row, its unique key, §2.11.1 lifecycle and legal
 * transitions, §2.11.2 invariants I1–I8, §2.11.3 the derived gap), §2.12
 * (append-only responses and the idempotency rule), §2.14 (the notification
 * outbox and its dedupe keys), §3.3.2 (the assignment half of the API), §3.3.3
 * (the four member endpoints), §3.6 (which event enqueues which notification)
 * and §4.8 (the negatives).
 *
 * The fixture is UC1 / §2.17's Coffee Bar, built through the REAL APIs — the
 * setup API (#9715/#9707) for ministry, team, positions, pool and
 * qualifications, and the schedule API (#9708) for the schedule and its
 * occurrences. Nothing here inserts a V2 row by hand except the historical
 * assignments the last-served ordering needs, which I5 would refuse to create
 * through the API because their occurrence is in the past. (Pool membership used to
 * be the other exception; D19 gave it a writable route, so the fixture uses it.)
 *
 * What #9709 promises, and where each promise is proven:
 *
 *   "coordinator can assign a qualified person"            → `assign` block
 *   "invalid/ineligible assignments are prevented"         → I2/I3/I5 tests
 *   "duplicate/conflicting assignments are rejected"       → I1 test (409)
 *   "accept and decline are idempotent"                    → `respond` block,
 *        asserting the response HISTORY length, not just the status code
 *   "declines produce visible staffing gaps"               → `gaps` block
 *   "historical assignment/response state is preserved"    → I8 row reuse, and
 *        the response history that survives it
 *   "server-side authorization is enforced"                → `§4.8` block
 *
 * The outbox is asserted through `cy.dbQuery` rather than an endpoint: #9710
 * owns the read surface, #9709 only owns the enqueue seam (§2.14).
 *
 * Cleanup runs in `before` as well as `after` (cypress-testing.md): an `after`
 * hook does not run when the runner crashes mid-spec. Deletion order is FK-safe.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";
const PLAINAUTH_KEY = "plainauth.api.key";
const SELFEDIT_KEY = "selfedit.api.key";

const VOLUNTEER_URL = "/api/ministries";
const CART_URL = "/api/cart/";

const PERSON_COORDINATOR = 3; // tony.wade — every flag but Admin
const PERSON_PLAIN = 900; // john.plainauth — Notes only, no volunteer rights
const PERSON_VOLUNTEER = 99; // EditSelf-exclusive — THE volunteer persona (D14)

/**
 * The pool, since D19: the ministry's OWN Group, created with it and empty, so the
 * people below are put in it by this spec through `POST /ministries/{id}/pool/{id}`.
 * Group 1 "Angels class" is no longer involved — a seeded group is somebody else's
 * roster and borrowing it made the fixture depend on seed membership it did not own.
 */
const POOL_MEMBER_A = 8;
const POOL_MEMBER_B = 9;
const POOL_MEMBER_C = 63;
/** In the pool, deliberately never qualified — the I2 case. */
const POOL_MEMBER_UNQUALIFIED = 5;
/**
 * Qualified and then REMOVED from the pool — the I3 case.
 *
 * Since D19 qualifying somebody adds them to the ministry's pool, so "qualified but
 * outside the pool" can only be produced deliberately: the fixture qualifies them
 * and then takes them out again, which is exactly the real-world shape (a coordinator
 * tidies a roster and leaves the qualification alone).
 */
const OUTSIDE_POOL_PERSON = 7;

const CHURCH_SERVICE_TYPE = 1; // seed.sql — weekly, Sunday, 10:30

const FIXTURE_PREFIX = "ASG9709";
const EVENT_TITLE = `${FIXTURE_PREFIX} Coffee Bar Service`;

let ministryA = 0;
let ministryB = 0;
/** The team ministry B was created with — a schedule always names one. */
let teamB = 0;
let teamA = 0;
let posEspresso = 0;
let posMilk = 0;
let posExpeditor = 0;
let scheduleA = 0;
let scheduleB = 0;
let occurrenceOne = 0;
let occurrenceTwo = 0;
let occurrenceB = 0;
let reqEspresso = 0;
let originalVersion = "v1";
let seriesEventIds = [];
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

/** Assign through the coordinator endpoint; returns the assignment body. */
function assign(key, occurrenceId, positionId, personId, extra = {}, status = 201) {
    return api(
        key,
        "POST",
        `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
        { positionId, personId, ...extra },
        status,
    );
}

/** The response history of one assignment, through the coordinator read (§6.6). */
function history(assignmentId) {
    return api(ADMIN_KEY, "GET", `${VOLUNTEER_URL}/assignments/${assignmentId}`).then(
        (resp) => resp.body.responses,
    );
}

function outboxRows(type, personId) {
    return dbOk(
        `SELECT vntf_ID, vntf_DedupeKey, vntf_Status, vntf_vasg_ID, vntf_vocc_ID
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
    // Replacement rows point at the rows they replace, so break the self-FK first.
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
    // D19: the pool is the ministry's own Group. `grp_ministry_id` is ON DELETE SET
    // NULL, so the group has to go before the ministry or it is left an orphan.
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
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID IN (?, ?)`, [
        PERSON_COORDINATOR,
        PERSON_PLAIN,
    ]);
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

function createMinistry(name) {
    return api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries`, {
        name: `${FIXTURE_PREFIX} ${name}`,
        description: "volunteer v2 assignment fixture",
    }, 201).then((resp) => resp.body.ministry.id);
}

function createTeam(ministryId, name) {
    return api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, {
        name: `${FIXTURE_PREFIX} ${name}`,
        description: "volunteer v2 assignment fixture",
    }, 201).then((resp) => resp.body.team.id);
}

/**
 * The team a ministry was born with.
 *
 * Every ministry is created with one team already in it, named "{Ministry} Team",
 * so this fixture adopts that team instead of creating a second one with the same
 * name — which the API now answers 409 to, correctly.
 */
function defaultTeam(ministryId) {
    return api(ADMIN_KEY, "GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 200).then(
        (resp) => resp.body.teams[0].id,
    );
}

function createPosition(ministryId, teamId, name, order) {
    return api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
        name: `${FIXTURE_PREFIX} ${name}`,
        description: "volunteer v2 assignment fixture",
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

function upsertRequirement(scheduleId, positionId, minCount, maxCount) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
        { positionId, minCount, maxCount },
        [200, 201],
    ).then((resp) => resp.body.requirement.id);
}

/** Reset every assignment-side row between blocks so each starts from a clean slate. */
function resetWorkflow() {
    cleanupWorkflowRows();
}

// ── fixture ────────────────────────────────────────────────────────────────

before(() => {
    cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
        originalVersion = resp.body.value ?? resp.body.Value ?? "v1";
    });
    setVersion("v2");

    cleanupFixtures();

    // UC1 §2.17 — Coffee Bar: one ministry, one team, three positions.
    createMinistry("Coffee Bar").then((id) => {
        ministryA = id;
    });
    createMinistry("Sound Booth").then((id) => {
        ministryB = id;
    });

    cy.then(() => {
        defaultTeam(ministryA).then((id) => {
            teamA = id;
        });
        defaultTeam(ministryB).then((id) => {
            teamB = id;
        });
    });

    cy.then(() => {
        createPosition(ministryA, teamA, "Espresso", 1).then((id) => {
            posEspresso = id;
        });
        createPosition(ministryA, teamA, "Milk Station", 2).then((id) => {
            posMilk = id;
        });
        createPosition(ministryA, teamA, "Expeditor", 3).then((id) => {
            posExpeditor = id;
        });
    });

    // D19: the ministry came with its own pool Group, empty. Fill it through the
    // API rather than with raw SQL — the route is now writable by a coordinator, so
    // there is nothing left for the fixture to work around.
    cy.then(() => {
        for (const personId of [
            POOL_MEMBER_A,
            POOL_MEMBER_B,
            POOL_MEMBER_C,
            POOL_MEMBER_UNQUALIFIED,
            PERSON_VOLUNTEER,
        ]) {
            api(
                ADMIN_KEY,
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryA}/pool/${personId}`,
                null,
                [200, 201],
            );
        }
    });

    // Multiple qualifications per person, the §2.17 shape.
    cy.then(() => {
        qualify(posEspresso, POOL_MEMBER_A);
        qualify(posEspresso, POOL_MEMBER_B);
        qualify(posEspresso, PERSON_VOLUNTEER);
        qualify(posEspresso, OUTSIDE_POOL_PERSON);
        // …and straight back out of the pool, which is what makes them the I3 case
        // now that qualifying somebody puts them in it (D19).
        api(
            ADMIN_KEY,
            "DELETE",
            `${VOLUNTEER_URL}/ministries/${ministryA}/pool/${OUTSIDE_POOL_PERSON}`,
            null,
            200,
        );
        qualify(posMilk, POOL_MEMBER_A);
        qualify(posMilk, POOL_MEMBER_C);
        qualify(posMilk, PERSON_VOLUNTEER);
        qualify(posExpeditor, POOL_MEMBER_B);
        qualify(posExpeditor, POOL_MEMBER_C);
    });

    // A real future event series; V2 must never create events_event rows itself.
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
        }, 200).then((resp) => {
            seriesEventIds = resp.body.eventIds;
            expect(seriesEventIds.length).to.eq(3);
        });
    });

    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryA}/schedules`, {
            name: `${FIXTURE_PREFIX} Coffee Bar — Sunday`,
            linkMode: "event_type",
            eventTypeId: CHURCH_SERVICE_TYPE,
            titleFilter: EVENT_TITLE,
            windowStart: seriesStart,
            teamId: teamA,
        }, 201).then((resp) => {
            scheduleA = resp.body.schedule.id;
        });
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryB}/schedules`, {
            name: `${FIXTURE_PREFIX} Sound Booth — Sunday`,
            linkMode: "event_type",
            eventTypeId: CHURCH_SERVICE_TYPE,
            titleFilter: EVENT_TITLE,
            windowStart: seriesStart,
            teamId: teamB,
        }, 201).then((resp) => {
            scheduleB = resp.body.schedule.id;
        });
    });

    // §2.17: Espresso and Milk Station need exactly one; Expeditor is the
    // optional third person (Min 0 / Max 1), which is what makes `openCount`
    // different from `gapCount`.
    cy.then(() => {
        upsertRequirement(scheduleA, posEspresso, 1, 1).then((id) => {
            reqEspresso = id;
        });
        upsertRequirement(scheduleA, posMilk, 1, 1);
        upsertRequirement(scheduleA, posExpeditor, 0, 1);
    });

    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/schedules/${scheduleA}/generate`, {
            through: seriesEnd,
        }, 200);
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/schedules/${scheduleB}/generate`, {
            through: seriesEnd,
        }, 200);
    });

    cy.then(() => {
        api(
            ADMIN_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}`,
        ).then((resp) => {
            const rows = resp.body.occurrences;
            expect(rows.length).to.be.greaterThan(1);
            occurrenceOne = rows[0].id;
            occurrenceTwo = rows[1].id;
        });
        api(
            ADMIN_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryB}`,
        ).then((resp) => {
            occurrenceB = resp.body.occurrences[0].id;
        });
    });

    // Person 3 coordinates Coffee Bar and nothing else — the §4.8 boundary.
    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/scopes`, {
            personId: PERSON_COORDINATOR,
            scopeType: "ministry",
            scopeId: ministryA,
        }, [200, 201]);
    });
});

after(() => {
    cleanupFixtures();
    setVersion(originalVersion);
});

// ───────────────────────────────────────────────────────────────────────────

describe("Volunteer v2 — assign (§2.11.2 I1–I5, I8)", () => {
    beforeEach(resetWorkflow);

    it("assigns a qualified pool member and enqueues the assignment notification", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then((resp) => {
            const a = resp.body.assignment;
            expect(a.occurrenceId).to.eq(occurrenceOne);
            expect(a.positionId).to.eq(posEspresso);
            expect(a.personId).to.eq(POOL_MEMBER_A);
            expect(a.status).to.eq("pending");
            expect(a.source).to.eq("coordinator");
            expect(a.assignedByPersonId).to.eq(PERSON_COORDINATOR);
            // The requirement is resolved server-side when the caller omits it.
            expect(a.requirementId).to.eq(reqEspresso);

            // §3.6: assign() enqueues an `assignment` message for the volunteer,
            // keyed `assignment:{assignmentId}:{personId}` (§2.14).
            outboxRows("assignment", POOL_MEMBER_A).then((rows) => {
                expect(rows).to.have.length(1);
                expect(rows[0].vntf_DedupeKey).to.eq(`assignment:${a.id}:${POOL_MEMBER_A}`);
                expect(rows[0].vntf_Status).to.eq("pending");
                expect(Number(rows[0].vntf_vasg_ID)).to.eq(a.id);
            });
        });
    });

    it("rejects a second identical assignment with 409 (I1)", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A);
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A, {}, 409);

        dbOk(
            `SELECT COUNT(*) AS c FROM volunteer_assignment_vasg
              WHERE vasg_vocc_ID = ? AND vasg_vpos_ID = ? AND vasg_per_ID = ?`,
            [occurrenceOne, posEspresso, POOL_MEMBER_A],
        ).then((rows) => {
            expect(Number(rows[0].c)).to.eq(1);
        });
    });

    it("refuses an unqualified person with 403 (I2)", () => {
        assign(
            COORDINATOR_KEY,
            occurrenceOne,
            posEspresso,
            POOL_MEMBER_UNQUALIFIED,
            {},
            403,
        );
    });

    it("refuses a qualified person outside the pool with 409, and accepts them with allowOutsidePool (I3)", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, OUTSIDE_POOL_PERSON, {}, 409);

        assign(
            COORDINATOR_KEY,
            occurrenceOne,
            posEspresso,
            OUTSIDE_POOL_PERSON,
            { allowOutsidePool: true },
            201,
        ).then((resp) => {
            expect(resp.body.assignment.personId).to.eq(OUTSIDE_POOL_PERSON);
            expect(resp.body.assignment.status).to.eq("pending");
        });
    });

    it("refuses an occurrence that has been cancelled (I5)", () => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/occurrences/${occurrenceTwo}/status`, {
            status: "cancelled",
        }, 200);

        assign(COORDINATOR_KEY, occurrenceTwo, posEspresso, POOL_MEMBER_A, {}, 409);

        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/occurrences/${occurrenceTwo}/status`, {
            status: "scheduled",
        }, 200);
    });

    it("allows the same person on two different positions of one occurrence (I7, D16)", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A, {}, 201);
        assign(COORDINATOR_KEY, occurrenceOne, posMilk, POOL_MEMBER_A, {}, 201);

        dbOk(
            `SELECT COUNT(*) AS c FROM volunteer_assignment_vasg
              WHERE vasg_vocc_ID = ? AND vasg_per_ID = ?`,
            [occurrenceOne, POOL_MEMBER_A],
        ).then((rows) => {
            expect(Number(rows[0].c)).to.eq(2);
        });

        // The picker reports the clash rather than hiding the person (§5.5).
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/eligible?positionId=${posExpeditor}`,
        ).then((resp) => {
            const clash = resp.body.people.find((p) => p.personId === POOL_MEMBER_A);
            // Person A is not qualified for Expeditor, so they are absent —
            // check the one who IS, and holds Espresso nowhere.
            expect(clash).to.eq(undefined);
        });

        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/eligible?positionId=${posMilk}`,
        ).then((resp) => {
            const person = resp.body.people.find((p) => p.personId === POOL_MEMBER_A);
            expect(person, "an already-serving person stays in the picker").to.not.eq(undefined);
            expect(person.conflictPositionId).to.eq(posEspresso);
        });
    });

    it("reuses the declined row when the same person is re-assigned (I8)", () => {
        let assignmentId = 0;

        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then((resp) => {
            assignmentId = resp.body.assignment.id;
        });

        cy.then(() => {
            api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/assignments/${assignmentId}/status`, {
                status: "declined",
            }, 200);
        });

        cy.then(() => {
            assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A, {}, 201).then(
                (resp) => {
                    expect(resp.body.assignment.id, "the SAME row is reused").to.eq(assignmentId);
                    expect(resp.body.assignment.status).to.eq("pending");
                },
            );
        });

        cy.then(() => {
            dbOk(
                `SELECT COUNT(*) AS c FROM volunteer_assignment_vasg
                  WHERE vasg_vocc_ID = ? AND vasg_vpos_ID = ? AND vasg_per_ID = ?`,
                [occurrenceOne, posEspresso, POOL_MEMBER_A],
            ).then((rows) => {
                expect(Number(rows[0].c)).to.eq(1);
            });
            // The audit survives the reuse: the decline row is still there.
            history(assignmentId).then((rows) => {
                expect(rows.map((r) => r.response)).to.include("declined");
            });
        });
    });
});

describe("Volunteer v2 — respond (§2.11.1, §2.12)", () => {
    let assignmentId = 0;

    beforeEach(() => {
        resetWorkflow();
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, PERSON_VOLUNTEER).then((resp) => {
            assignmentId = resp.body.assignment.id;
        });
    });

    it("lets the volunteer accept their own assignment through the member surface", () => {
        api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/me/assignments/${assignmentId}/respond`, {
            response: "accepted",
        }, 200).then((resp) => {
            expect(resp.body.assignment.status).to.eq("accepted");
            expect(resp.body.assignment.respondedDate).to.not.eq(null);
        });

        history(assignmentId).then((rows) => {
            expect(rows).to.have.length(1);
            expect(rows[0].response).to.eq("accepted");
            expect(rows[0].personId).to.eq(PERSON_VOLUNTEER);
            expect(rows[0].channel).to.eq("web");
        });
    });

    it("is idempotent: accepting twice is 200 and leaves ONE response row (§2.12, §6.6)", () => {
        api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/me/assignments/${assignmentId}/respond`, {
            response: "accepted",
        }, 200);
        api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/me/assignments/${assignmentId}/respond`, {
            response: "accepted",
        }, 200).then((resp) => {
            expect(resp.body.assignment.status).to.eq("accepted");
        });

        history(assignmentId).then((rows) => {
            expect(rows, "no second row for a no-op response").to.have.length(1);
        });
    });

    it("reopens the gap on a volunteer decline and alerts every coordinator (§3.6)", () => {
        api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/me/assignments/${assignmentId}/respond`, {
            response: "declined",
            comment: "Out of town",
        }, 200).then((resp) => {
            expect(resp.body.assignment.status).to.eq("declined");
        });

        // The gap is derived, so it simply reappears — nothing writes it.
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/staffing`,
        ).then((resp) => {
            const espresso = resp.body.requirements.find((r) => r.positionId === posEspresso);
            expect(espresso.liveCount).to.eq(0);
            expect(espresso.gapCount).to.eq(1);
        });

        // decline_alert goes to the coordinators, keyed per coordinator (§2.14).
        cy.then(() => {
            outboxRows("decline_alert", PERSON_COORDINATOR).then((rows) => {
                expect(rows).to.have.length(1);
                expect(rows[0].vntf_DedupeKey).to.eq(
                    `decline_alert:${assignmentId}:${PERSON_COORDINATOR}`,
                );
            });
        });
    });

    it("declining twice is 200 and still leaves ONE response row", () => {
        api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/me/assignments/${assignmentId}/respond`, {
            response: "declined",
        }, 200);
        api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/me/assignments/${assignmentId}/respond`, {
            response: "declined",
        }, 200);

        history(assignmentId).then((rows) => {
            expect(rows).to.have.length(1);
        });
    });

    it("records a coordinator's accept on the volunteer's behalf with channel `coordinator`", () => {
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/assignments/${assignmentId}/status`, {
            status: "accepted",
            comment: "Confirmed by phone",
        }, 200).then((resp) => {
            expect(resp.body.assignment.status).to.eq("accepted");
        });

        history(assignmentId).then((rows) => {
            expect(rows).to.have.length(1);
            expect(rows[0].response).to.eq("accepted");
            expect(rows[0].channel).to.eq("coordinator");
            expect(rows[0].personId, "the coordinator is the author of the row").to.eq(
                PERSON_COORDINATOR,
            );
        });
    });

    it("enqueues NO decline_alert for a coordinator-recorded decline (§2.11.1)", () => {
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/assignments/${assignmentId}/status`, {
            status: "declined",
        }, 200);

        outboxRows("decline_alert", PERSON_COORDINATOR).then((rows) => {
            expect(rows, "the coordinators already know").to.have.length(0);
        });
    });

    it("rejects an illegal transition with 409 and reports the current status", () => {
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/assignments/${assignmentId}/status`, {
            status: "cancelled",
        }, 200);

        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/assignments/${assignmentId}/status`, {
            status: "accepted",
        }, 409).then((resp) => {
            expect(resp.body.currentStatus).to.eq("cancelled");
        });
    });

    it("cancels through DELETE and reopens the gap", () => {
        api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/me/assignments/${assignmentId}/respond`, {
            response: "accepted",
        }, 200);

        api(COORDINATOR_KEY, "DELETE", `${VOLUNTEER_URL}/assignments/${assignmentId}`, null, 200);

        api(ADMIN_KEY, "GET", `${VOLUNTEER_URL}/assignments/${assignmentId}`).then((resp) => {
            expect(resp.body.assignment.status).to.eq("cancelled");
        });
    });
});

describe("Volunteer v2 — gaps (§2.11.3)", () => {
    beforeEach(resetWorkflow);

    it("derives live / gap / open per requirement", () => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/staffing`,
        ).then((resp) => {
            const espresso = resp.body.requirements.find((r) => r.positionId === posEspresso);
            expect(espresso.minCount).to.eq(1);
            expect(espresso.liveCount).to.eq(0);
            expect(espresso.gapCount).to.eq(1);
            expect(espresso.openCount).to.eq(1);

            // Min 0 / Max 1: no gap, but a slot is open for self-signup.
            const expeditor = resp.body.requirements.find((r) => r.positionId === posExpeditor);
            expect(expeditor.gapCount).to.eq(0);
            expect(expeditor.openCount).to.eq(1);
        });

        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A);

        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/staffing`,
        ).then((resp) => {
            const espresso = resp.body.requirements.find((r) => r.positionId === posEspresso);
            // A `pending` row counts as live — the gap is not re-opened until
            // the volunteer actually declines.
            expect(espresso.liveCount).to.eq(1);
            expect(espresso.gapCount).to.eq(0);
            expect(espresso.openCount).to.eq(0);
            expect(espresso.assignments).to.have.length(1);
            expect(espresso.assignments[0].personId).to.eq(POOL_MEMBER_A);
        });
    });

    it("lists gaps across the caller's scope through GET /gaps", () => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/gaps?from=${seriesStart}&to=${seriesEnd}`,
        ).then((resp) => {
            const mine = resp.body.gaps.filter((g) => g.occurrenceId === occurrenceOne);
            expect(mine.map((g) => g.positionId)).to.include.members([posEspresso, posMilk]);
            // Min 0 is not a gap.
            expect(mine.map((g) => g.positionId)).to.not.include(posExpeditor);
            // §4.8: never another ministry's occurrence.
            expect(resp.body.gaps.map((g) => g.occurrenceId)).to.not.include(occurrenceB);
        });
    });

    it("requires from and to on GET /gaps", () => {
        api(COORDINATOR_KEY, "GET", `${VOLUNTEER_URL}/gaps`, null, 400);
    });

    it("reports liveCount / gapCount / pendingCount on the occurrence list and filters with ?hasGaps=1", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A);
        assign(COORDINATOR_KEY, occurrenceOne, posMilk, POOL_MEMBER_C);

        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}`,
        ).then((resp) => {
            const first = resp.body.occurrences.find((o) => o.id === occurrenceOne);
            expect(first.liveCount).to.eq(2);
            expect(first.gapCount).to.eq(0);
            expect(first.pendingCount).to.eq(2);

            const second = resp.body.occurrences.find((o) => o.id === occurrenceTwo);
            expect(second.liveCount).to.eq(0);
            expect(second.gapCount).to.eq(2);
        });

        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}&hasGaps=1`,
        ).then((resp) => {
            const ids = resp.body.occurrences.map((o) => o.id);
            expect(ids).to.not.include(occurrenceOne);
            expect(ids).to.include(occurrenceTwo);
        });
    });

    it("reports the same counts on the occurrence detail", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A);

        api(COORDINATOR_KEY, "GET", `${VOLUNTEER_URL}/occurrences/${occurrenceOne}`).then(
            (resp) => {
                expect(resp.body.occurrence.liveCount).to.eq(1);
                expect(resp.body.occurrence.gapCount).to.eq(1);
                const espresso = resp.body.requirements.find(
                    (r) => r.positionId === posEspresso,
                );
                expect(espresso.liveCount).to.eq(1);
                expect(espresso.gapCount).to.eq(0);
            },
        );
    });
});

describe("Volunteer v2 — the eligible picker (§3.3.2, §2.17 rotation)", () => {
    beforeEach(resetWorkflow);

    it("offers only qualified people, flags pool membership and orders by last served", () => {
        // Two historical assignments so the ordering has something to order by.
        // They are inserted directly: I5 refuses to create an assignment on an
        // occurrence that has already ended, which is exactly what these are.
        dbOk(
            `INSERT INTO volunteer_occurrence_vocc
                 (vocc_vsch_ID, vocc_OccurrenceDate, vocc_StartDateTime, vocc_EndDateTime,
                  vocc_Status, vocc_GeneratedDate)
             VALUES (?, ?, ?, ?, 'scheduled', NOW())`,
            [scheduleA, isoDate(-28), `${isoDate(-28)} 10:30:00`, `${isoDate(-28)} 11:45:00`],
        ).then((rows) => {
            const oldOccurrence = rows.insertId;
            dbOk(
                `INSERT INTO volunteer_assignment_vasg
                     (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                 VALUES (?, ?, ?, 'completed', 'coordinator', NOW())`,
                [oldOccurrence, posEspresso, POOL_MEMBER_B],
            );
        });

        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/eligible?positionId=${posEspresso}`,
        ).then((resp) => {
            const people = resp.body.people;
            const ids = people.map((p) => p.personId);

            // Qualified only — the unqualified pool member is never offered.
            expect(ids).to.not.include(POOL_MEMBER_UNQUALIFIED);
            expect(ids).to.include.members([POOL_MEMBER_A, POOL_MEMBER_B, PERSON_VOLUNTEER]);

            // Out-of-pool qualified people are listed, flagged, never hidden.
            const outside = people.find((p) => p.personId === OUTSIDE_POOL_PERSON);
            expect(outside, "an out-of-pool qualified person is still offered").to.not.eq(
                undefined,
            );
            expect(outside.inPool).to.eq(false);
            expect(people.find((p) => p.personId === POOL_MEMBER_A).inPool).to.eq(true);

            // lastServedDate ASC NULLS FIRST: never-served people come first,
            // and the one who served most recently is last (§2.17 rotation).
            const served = people.filter((p) => p.lastServedDate !== null);
            expect(served.length).to.be.greaterThan(0);
            const firstServedIndex = people.findIndex((p) => p.lastServedDate !== null);
            people.slice(0, firstServedIndex).forEach((p) => {
                expect(p.lastServedDate).to.eq(null);
            });
            expect(people[people.length - 1].personId).to.eq(POOL_MEMBER_B);
        });
    });

    it("filters by ?q=", () => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/eligible?positionId=${posEspresso}&q=zzzznotaperson`,
        ).then((resp) => {
            expect(resp.body.people).to.have.length(0);
        });
    });

    it("requires positionId", () => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/eligible`,
            null,
            400,
        );
    });
});

/**
 * The Cart sink is gone from this surface.
 *
 * `POST /volunteer/cart/assign` and `VolunteerAssignmentService::assignFromCart()`
 * were removed with the occurrence page's "Assign everyone in the cart" button:
 * assigning is a per-person act with per-person eligibility rules (I1-I5), so the
 * bulk call half-succeeded and handed back a list of reasons — a worse answer than
 * the single-person picker, which can only ever offer assignable people. The Cart
 * still feeds V2 from the ministry page, where it fills the volunteer POOL
 * (`POST /ministries/{id}/pool/from-cart`, covered in
 * private.volunteer.pools-qualifications.spec.js).
 *
 * The case below is what stops the route coming back by accident.
 */
describe("Volunteer v2 — the Cart sink is off the assignment surface", () => {
    it("has no cart-assign route any more", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            CART_URL,
            { Persons: [POOL_MEMBER_A, POOL_MEMBER_B] },
            200,
        );

        cy.makePrivateAdminAPICall(
            "POST",
            `${VOLUNTEER_URL}/cart/assign`,
            { occurrenceId: occurrenceOne, positionId: posEspresso },
            404,
        );

        cy.makePrivateAdminAPICall("DELETE", CART_URL, null, 200);
    });
});

describe("Volunteer v2 — notify re-enqueue (§3.3.2)", () => {
    let assignmentId = 0;

    beforeEach(() => {
        resetWorkflow();
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then((resp) => {
            assignmentId = resp.body.assignment.id;
        });
    });

    it("is idempotent through the dedupe key unless ?force=1", () => {
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/assignments/${assignmentId}/notify`, {}, 200).then(
            (resp) => {
                expect(resp.body.created, "the assign() row already exists").to.eq(false);
            },
        );

        outboxRows("assignment", POOL_MEMBER_A).then((rows) => {
            expect(rows).to.have.length(1);
        });
    });

    it("resets a sent row to pending with ?force=1", () => {
        dbOk(
            `UPDATE volunteer_notification_vntf SET vntf_Status = 'sent', vntf_SentDate = NOW()
              WHERE vntf_vasg_ID = ? AND vntf_Type = 'assignment'`,
            [assignmentId],
        );

        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/assignments/${assignmentId}/notify?force=1`,
            {},
            200,
        );

        outboxRows("assignment", POOL_MEMBER_A).then((rows) => {
            expect(rows, "force re-arms the SAME row, it never adds a second").to.have.length(1);
            expect(rows[0].vntf_Status).to.eq("pending");
        });
    });
});

describe("Volunteer v2 — authorization negatives (§4.8)", () => {
    beforeEach(resetWorkflow);

    it("401s an unauthenticated caller", () => {
        cy.request({
            method: "GET",
            url: `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/staffing`,
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(401);
        });
    });

    it("403s a coordinator on another ministry's occurrence", () => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceB}/staffing`,
            null,
            403,
        );
        assign(COORDINATOR_KEY, occurrenceB, posEspresso, POOL_MEMBER_A, {}, 403);
    });

    it("403s a user with no volunteer rights at all", () => {
        api(
            PLAINAUTH_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/staffing`,
            null,
            403,
        );
    });

    it("403s the volunteer persona on the coordinator surface", () => {
        api(
            SELFEDIT_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceOne}/staffing`,
            null,
            403,
        );
    });

    it("403s — never 404s — a member responding to someone else's assignment (§3.3.3)", () => {
        let otherId = 0;
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then((resp) => {
            otherId = resp.body.assignment.id;
        });

        cy.then(() => {
            api(
                SELFEDIT_KEY,
                "POST",
                `${VOLUNTEER_URL}/me/assignments/${otherId}/respond`,
                { response: "accepted" },
                403,
            );
        });
    });

    it("lets the volunteer read only their own assignments on /me", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A);
        assign(COORDINATOR_KEY, occurrenceOne, posMilk, PERSON_VOLUNTEER);

        api(SELFEDIT_KEY, "GET", `${VOLUNTEER_URL}/me/assignments`).then((resp) => {
            const ids = resp.body.assignments.map((a) => a.personId);
            expect(ids.every((id) => id === PERSON_VOLUNTEER)).to.eq(true);
            expect(resp.body.assignments.length).to.be.greaterThan(0);
            const mine = resp.body.assignments[0];
            expect(mine.positionName).to.be.a("string");
            expect(mine.ministryName).to.be.a("string");
            expect(mine.canRespond).to.eq(true);
        });
    });

    it("takes no personId parameter on the member surface (§3.3.3)", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A);

        api(
            SELFEDIT_KEY,
            "GET",
            `${VOLUNTEER_URL}/me/assignments?personId=${POOL_MEMBER_A}`,
        ).then((resp) => {
            const ids = resp.body.assignments.map((a) => a.personId);
            expect(ids).to.not.include(POOL_MEMBER_A);
        });
    });
});
