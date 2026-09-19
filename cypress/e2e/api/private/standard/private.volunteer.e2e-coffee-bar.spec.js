/// <reference types="cypress" />

/**
 * Volunteer v2 — #9714 scenario 1, "Coffee Bar", as ONE end-to-end run.
 *
 * Epic #9701, issue #9714, design `.agents/skills/churchcrm/volunteer-v2-design.md`
 * §0.4 UC1, §2.17 (the worked example this fixture is), §6.5 row 1.
 *
 * The per-issue specs each prove one seam. This spec proves the *seams hold
 * together*: a coordinator who is not an administrator sets a ministry up,
 * schedules it against a real calendar event type, staffs it, a volunteer drops
 * out, and a different qualified volunteer fills the hole from the member
 * surface — with nothing but the real HTTP APIs in between.
 *
 *     15 people in the ministry's own pool Group
 *       → 5 positions, Min 1/Max 1 x2 plus an optional Min 0/Max 1 third
 *       → several qualifications per person
 *       → a schedule over an event TYPE, generated (twice — idempotency)
 *       → coordinator assigns            → outbox row appears
 *       → the volunteer declines         → gapCount becomes 1
 *       → a DIFFERENT volunteer signs up → gapCount returns to 0
 *
 * The two member personas are the seeded EditSelf accounts (D14): person 100
 * (`selfedit.plus.notes.api.key`) is the one who declines, person 99
 * (`selfedit.api.key`) is the one who self-signs-up. They have to be two
 * different people — §6.5 requires the replacement to be somebody else.
 *
 * The acting coordinator is person 3 holding a ministry scope, NOT the
 * administrator: an admin bypasses every role middleware except
 * `AdminRoleAuthMiddleware` (`cypress-testing.md`), so staffing the whole
 * scenario as admin would prove nothing about §4.6.
 *
 * Two things are written with `cy.dbQuery` because they have no API at all:
 * reading the notification outbox (#9710 owns its read surface, and this spec
 * only needs to see that the row exists) and nothing else — pool membership
 * goes through `POST /api/groups/{id}/addperson/{id}` as admin, which is the
 * real API for it.
 *
 * Cleanup runs in `before` as well as `after`: an `after` hook does not run
 * when the runner crashes mid-spec (`cypress-testing.md`).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";
const SELFEDIT_KEY = "selfedit.api.key"; // person 99 — signs up
const SELFEDIT_NOTES_KEY = "selfedit.plus.notes.api.key"; // person 100 — declines

const VOLUNTEER_URL = "/api/ministries";

const PERSON_COORDINATOR = 3;
const PERSON_SIGNS_UP = 99;
const PERSON_DECLINES = 100;

const CHURCH_SERVICE_TYPE = 1; // seed.sql — weekly, Sunday 10:30

const PREFIX = "E2E9714CB";
const MINISTRY_NAME = `${PREFIX} Coffee Bar`;
const TEAM_NAME = `${PREFIX} Coffee Bar Team`;
const EVENT_TITLE = `${PREFIX} Sunday Coffee Bar`;

/**
 * The pool, fifteen people (§0.4 UC1: "15 volunteers in one ChurchCRM Group").
 * Thirteen ordinary seeded people plus the two member personas, so the decline and
 * the self-signup both come from inside the pool — I3 refuses a coordinator's
 * assignment to somebody outside it.
 */
const POOL_PEOPLE = [2, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
const POOL_ALL = [...POOL_PEOPLE, PERSON_DECLINES, PERSON_SIGNS_UP];

/** The five UC1 positions. Only three of them carry a staffing requirement. */
const POSITION_NAMES = [
    "Setup",
    "Cleanup",
    "Espresso",
    "Milk Station",
    "Expeditor",
];

let ministryId = 0;
let teamId = 0;
/** The ministry's own pool Group (D19); read back rather than created. */
let groupId = 0;
let scheduleId = 0;
let occurrenceId = 0;
let scopeId = 0;
const positions = {};
let originalVersion = "v1";
let seriesStart = "";
let seriesEnd = "";
let seriesEventIds = [];

// ── helpers (local functions, NOT cy.* commands — cypress-testing.md) ───────

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

/** Days from today to the next `dow` (0 = Sunday). Never 0 — the window is future. */
function daysToNext(dow) {
    const today = new Date().getDay();
    return ((dow - today + 7) % 7) || 7;
}

/** The staffing row for one position on the occurrence, read as the coordinator. */
function staffingFor(positionId) {
    return api(
        COORDINATOR_KEY,
        "GET",
        `${VOLUNTEER_URL}/occurrences/${occurrenceId}/staffing`,
    ).then((resp) =>
        resp.body.requirements.find((r) => r.positionId === positionId),
    );
}

function outboxRows(type, personId) {
    return dbOk(
        `SELECT vntf_ID, vntf_Type, vntf_Status, vntf_per_ID, vntf_vasg_ID
           FROM volunteer_notification_vntf
          WHERE vntf_Type = ? AND vntf_per_ID = ?`,
        [type, personId],
    );
}

function cleanupFixtures() {
    const scoped = `
        JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
        JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
        JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
       WHERE vmin.vmin_Name LIKE ?`;
    const like = [`${PREFIX}%`];

    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vntf.vntf_vasg_ID
           ${scoped}`,
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
           ${scoped}`,
        like,
    );
    dbOk(
        `DELETE vrsp FROM volunteer_response_vrsp vrsp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vrsp.vrsp_vasg_ID
           ${scoped}`,
        like,
    );
    // Replacement rows point at the row they replace — break the self-FK first.
    dbOk(
        `UPDATE volunteer_assignment_vasg vasg ${scoped.replace("WHERE", "SET vasg.vasg_Replaces_vasg_ID = NULL WHERE")}`,
        like,
    );
    dbOk(`DELETE vasg FROM volunteer_assignment_vasg vasg ${scoped}`, like);
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
    // D19: the pool is the ministry's own Group and `grp_ministry_id` is ON DELETE
    // SET NULL, so the group goes before the ministry or it is left an orphan.
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
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [
        PERSON_COORDINATOR,
    ]);
    // #9869: a ministry created through the API now comes with its own calendar,
    // named after the ministry. `calendars.ministry_id` is ON DELETE SET NULL, so
    // deleting the ministry row directly would leave the calendar behind as an
    // unowned church calendar. It goes first, matched on the same prefix.
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, like);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, like);
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, like);
    // The pool Group is this spec's own; take its memberships with it.
    dbOk(
        `DELETE p2g2r FROM person2group2role_p2g2r p2g2r
           JOIN group_grp grp ON grp.grp_ID = p2g2r.p2g2r_grp_ID
          WHERE grp.grp_Name LIKE ?`,
        like,
    );
    dbOk(`DELETE FROM group_grp WHERE grp_Name LIKE ?`, like);
}

// ── the fixture, built the way a coordinator would build it ────────────────

before(() => {
    cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
        originalVersion = resp.body.value ?? resp.body.Value ?? "v1";
    });
    setVersion("v2");
    cleanupFixtures();

    // Ministry creation is manager-only (§3.3.1), so the administrator makes
    // the ministry and then hands person 3 the scope that makes them the
    // coordinator. Everything after this point is done with the coordinator key.
    cy.then(() => {
        api(
            ADMIN_KEY,
            "POST",
            `${VOLUNTEER_URL}/ministries`,
            { name: MINISTRY_NAME, description: "#9714 scenario 1 — UC1" },
            201,
        ).then((resp) => {
            ministryId = resp.body.ministry.id;
        });
    });

    cy.then(() => {
        api(
            ADMIN_KEY,
            "POST",
            `${VOLUNTEER_URL}/scopes`,
            {
                personId: PERSON_COORDINATOR,
                scopeType: "ministry",
                scopeId: ministryId,
            },
            [200, 201],
        ).then((resp) => {
            scopeId = resp.body.scope.id;
        });
    });

    // UC1 is one ministry with one team, and a ministry is now created with exactly
    // that: "<name> Team". So the coordinator's first act is to adopt the team they
    // were given rather than to make a second one — asking for a team of the same
    // name is a 409, and asking for a differently named one would leave the ministry
    // with two teams that UC1 does not have.
    cy.then(() => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/ministries/${ministryId}`,
            null,
            200,
        ).then((resp) => {
            expect(resp.body.teams, "the ministry came with one team").to.have.length(1);
            expect(resp.body.teams[0].name).to.eq(TEAM_NAME);
            teamId = resp.body.teams[0].id;
        });
    });

    // Five positions (§0.4 UC1).
    cy.then(() => {
        POSITION_NAMES.forEach((name, index) => {
            api(
                COORDINATOR_KEY,
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                { name: `${PREFIX} ${name}`, teamId, order: index + 1 },
                201,
            ).then((resp) => {
                positions[name] = resp.body.position.id;
            });
        });
    });

    // The pool of fifteen (§0.4 UC1: "15 volunteers"). D19: the ministry came with
    // its own Group, empty, so the coordinator fills it — no group to make first and
    // nothing to link. The Group is still the roster and V2 still copies nobody (D1);
    // what changed is that the coordinator can write it without the global Manage
    // Groups flag, which is exactly what this scenario's persona has.
    cy.then(() => {
        POOL_ALL.forEach((personId) => {
            api(
                COORDINATOR_KEY,
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`,
                null,
                [200, 201],
            );
        });
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/ministries/${ministryId}/pool`,
            null,
            200,
        ).then((resp) => {
            groupId = resp.body.groupId;
        });
    });

    // Qualifications: D16 — several per person is the normal case, not an edge
    // case. Every pool member is qualified for Setup and Cleanup; the two
    // personas hold three and two respectively.
    cy.then(() => {
        const qualify = (positionName, personId) =>
            api(
                COORDINATOR_KEY,
                "POST",
                `${VOLUNTEER_URL}/positions/${positions[positionName]}/qualifications`,
                { personId, notes: "" },
                // Idempotent by the unique key: 201 when new, 200 on a re-grant.
                [200, 201],
            );

        POOL_ALL.forEach((personId) => {
            qualify("Setup", personId);
            qualify("Cleanup", personId);
        });
        // Espresso — the position the whole scenario turns on.
        [8, 9, PERSON_DECLINES, PERSON_SIGNS_UP].forEach((p) =>
            qualify("Espresso", p),
        );
        // Milk Station — deliberately a different set, so a decline on
        // Espresso cannot be quietly covered by the Milk Station people.
        [10, 11, PERSON_DECLINES].forEach((p) => qualify("Milk Station", p));
        // Expeditor — the optional position.
        [12, 13, PERSON_SIGNS_UP].forEach((p) => qualify("Expeditor", p));
    });

    // A real, future weekly event series. V2 must never create events itself.
    cy.then(() => {
        seriesStart = isoDate(daysToNext(0));
        seriesEnd = isoDate(daysToNext(0) + 14);

        api(
            ADMIN_KEY,
            "POST",
            "/api/events/repeat",
            {
                Title: EVENT_TITLE,
                Type: CHURCH_SERVICE_TYPE,
                StartTime: "10:30:00",
                EndTime: "11:45:00",
                RecurType: "weekly",
                RecurDOW: "Sunday",
                RangeStart: seriesStart,
                RangeEnd: seriesEnd,
            },
            200,
        ).then((resp) => {
            seriesEventIds = resp.body.eventIds;
        });
    });

    // The schedule hangs off the event TYPE, not off any single event (§2.8).
    cy.then(() => {
        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
            {
                name: `${PREFIX} Coffee Bar — Sunday`,
                linkMode: "event_type",
                eventTypeId: CHURCH_SERVICE_TYPE,
                titleFilter: EVENT_TITLE,
                windowStart: seriesStart,
                teamId,
            },
            201,
        ).then((resp) => {
            scheduleId = resp.body.schedule.id;
        });
    });

    // Min 1 / Max 1 twice, plus one optional Min 0 / Max 1 third (§6.5 row 1).
    // Setup and Cleanup exist as positions but carry no requirement at all —
    // five positions, three requirements.
    cy.then(() => {
        const requirement = (positionName, minCount, maxCount) =>
            api(
                COORDINATOR_KEY,
                "POST",
                `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
                { positionId: positions[positionName], minCount, maxCount },
                [200, 201],
            );

        requirement("Espresso", 1, 1);
        requirement("Milk Station", 1, 1);
        requirement("Expeditor", 0, 1);
    });
});

after(() => {
    cleanupFixtures();
    setVersion(originalVersion);
});

// ── the run ────────────────────────────────────────────────────────────────

describe("Volunteer v2 e2e — #9714 scenario 1, Coffee Bar", () => {
    it("built the UC1 fixture: a 15-person pool, five positions, three requirements", () => {
        // The pool reports the Group's real size, with no sync step (D1).
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/ministries/${ministryId}/pool`,
        ).then((resp) => {
            // The fifteen volunteers plus the coordinator, who joined the pool
            // Group with their scope grant (review, 2026-09-18).
            expect(resp.body.members).to.have.length(POOL_ALL.length + 1);
            expect(POOL_ALL.length).to.eq(15);
        });

        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
        ).then((resp) => {
            expect(resp.body.positions).to.have.length(5);
        });

        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
        ).then((resp) => {
            expect(resp.body.requirements).to.have.length(3);
            const espresso = resp.body.requirements.find(
                (r) => r.positionId === positions.Espresso,
            );
            expect(espresso.minCount).to.eq(1);
            expect(espresso.maxCount).to.eq(1);
            const expeditor = resp.body.requirements.find(
                (r) => r.positionId === positions.Expeditor,
            );
            expect(expeditor.minCount).to.eq(0);
            expect(expeditor.maxCount).to.eq(1);
        });
    });

    it("carries several qualifications per person (D16)", () => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/people/${PERSON_DECLINES}/qualifications`,
        ).then((resp) => {
            const ids = resp.body.qualifications.map((q) => q.positionId);
            expect(ids).to.include(positions.Setup);
            expect(ids).to.include(positions.Espresso);
            expect(ids).to.include(positions["Milk Station"]);
            expect(ids.length).to.be.at.least(4);
        });
    });

    it("generates occurrences over the event type, and generating again creates nothing", () => {
        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`,
            { through: seriesEnd },
            200,
        ).then((resp) => {
            expect(resp.body.created).to.eq(seriesEventIds.length);
            expect(resp.body.existing).to.eq(0);
        });

        // §6.6 — the second run reports `created: 0` AND the list is unchanged.
        let firstCount = 0;
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryId}`,
        ).then((resp) => {
            firstCount = resp.body.occurrences.length;
            expect(firstCount).to.eq(seriesEventIds.length);
            occurrenceId = resp.body.occurrences[0].id;
        });

        cy.then(() => {
            api(
                COORDINATOR_KEY,
                "POST",
                `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`,
                { through: seriesEnd },
                200,
            ).then((resp) => {
                expect(resp.body.created).to.eq(0);
                expect(resp.body.existing).to.eq(firstCount);
            });
        });

        cy.then(() => {
            api(
                COORDINATOR_KEY,
                "GET",
                `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryId}`,
            ).then((resp) => {
                expect(resp.body.occurrences).to.have.length(firstCount);
            });
        });
    });

    it("reports the occurrence's times from the calendar event, and wrote nothing to do it", () => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}`,
        ).then((resp) => {
            const occurrence = resp.body.occurrence;
            expect(occurrence.eventId).to.be.a("number");
            expect(occurrence.startDateTime).to.eq(null);
            expect(occurrence.start).to.include("10:30:00");
            expect(occurrence.end).to.include("11:45:00");
        });

        dbOk(
            `SELECT vocc_StartDateTime FROM volunteer_occurrence_vocc WHERE vocc_ID = ?`,
            [occurrenceId],
        ).then((rows) => {
            expect(rows[0].vocc_StartDateTime).to.eq(null);
        });
    });

    it("starts short: two gaps from the two Min 1 requirements, none from the optional one", () => {
        staffingFor(positions.Espresso).then((row) => {
            expect(row.liveCount).to.eq(0);
            expect(row.gapCount).to.eq(1);
        });
        staffingFor(positions["Milk Station"]).then((row) => {
            expect(row.gapCount).to.eq(1);
        });
        // Min 0: never a gap, but a slot is open for self-signup.
        staffingFor(positions.Expeditor).then((row) => {
            expect(row.gapCount).to.eq(0);
            expect(row.openCount).to.eq(1);
        });
    });

    it("offers only qualified pool members in the eligible picker", () => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/eligible?positionId=${positions.Espresso}`,
        ).then((resp) => {
            const ids = resp.body.people.map((p) => p.personId);
            expect(ids).to.include(PERSON_DECLINES);
            expect(ids).to.include(PERSON_SIGNS_UP);
            // Qualified for Milk Station only — must not be offered for Espresso.
            expect(ids).to.not.include(10);
        });
    });

    it("assigns from the pool and enqueues exactly one assignment notification", () => {
        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
            { positionId: positions.Espresso, personId: PERSON_DECLINES },
            201,
        ).then((resp) => {
            expect(resp.body.assignment.status).to.eq("pending");
            expect(resp.body.assignment.personId).to.eq(PERSON_DECLINES);
        });

        // A pending row counts as live — the gap closes before anyone replies.
        staffingFor(positions.Espresso).then((row) => {
            expect(row.liveCount).to.eq(1);
            expect(row.gapCount).to.eq(0);
        });

        // The outbox row is written inside the same transaction (§2.14/§3.6).
        outboxRows("assignment", PERSON_DECLINES).then((rows) => {
            expect(rows).to.have.length(1);
            expect(rows[0].vntf_Status).to.eq("pending");
        });
    });

    it("re-opens the gap when the volunteer declines from the member surface", () => {
        api(
            SELFEDIT_NOTES_KEY,
            "GET",
            `${VOLUNTEER_URL}/me/assignments`,
        ).then((resp) => {
            const mine = resp.body.assignments.filter(
                (a) => a.occurrenceId === occurrenceId,
            );
            expect(mine).to.have.length(1);

            api(
                SELFEDIT_NOTES_KEY,
                "POST",
                `${VOLUNTEER_URL}/me/assignments/${mine[0].id}/respond`,
                { response: "declined", comment: "Out of town" },
                200,
            ).then((declined) => {
                expect(declined.body.assignment.status).to.eq("declined");
            });
        });

        // The gap is derived — nothing recalculates or caches it (§2.11.3).
        cy.then(() => {
            staffingFor(positions.Espresso).then((row) => {
                expect(row.liveCount).to.eq(0);
                expect(row.gapCount).to.eq(1);
            });
        });
    });

    it("offers the now-open Espresso slot to the other qualified volunteer", () => {
        api(
            SELFEDIT_KEY,
            "GET",
            `${VOLUNTEER_URL}/me/opportunities`,
        ).then((resp) => {
            const espresso = resp.body.opportunities.filter(
                (o) =>
                    o.occurrenceId === occurrenceId &&
                    o.positionId === positions.Espresso,
            );
            expect(espresso).to.have.length(1);
        });
    });

    it("closes the gap when a DIFFERENT qualified volunteer signs themselves up", () => {
        api(
            SELFEDIT_KEY,
            "POST",
            `${VOLUNTEER_URL}/me/signup`,
            { occurrenceId, positionId: positions.Espresso },
            201,
        ).then((resp) => {
            const assignment = resp.body.assignment;
            // The actor is the session, never a body parameter (§3.3.3).
            expect(assignment.personId).to.eq(PERSON_SIGNS_UP);
            expect(assignment.personId).to.not.eq(PERSON_DECLINES);
            expect(assignment.status).to.eq("accepted");
            expect(assignment.source).to.eq("self_signup");
        });

        // The loop closes: gapCount is back to 0, and the declined row is still
        // there as history rather than being deleted (§2.11.2 I8).
        cy.then(() => {
            staffingFor(positions.Espresso).then((row) => {
                expect(row.liveCount).to.eq(1);
                expect(row.gapCount).to.eq(0);
                const people = row.assignments.map((a) => a.personId);
                expect(people).to.include(PERSON_SIGNS_UP);
            });
        });

        cy.then(() => {
            dbOk(
                `SELECT vasg_per_ID, vasg_Status FROM volunteer_assignment_vasg
                  WHERE vasg_vocc_ID = ? AND vasg_vpos_ID = ? ORDER BY vasg_ID`,
                [occurrenceId, positions.Espresso],
            ).then((rows) => {
                const declined = rows.find(
                    (r) => Number(r.vasg_per_ID) === PERSON_DECLINES,
                );
                expect(declined, "the decline survives as history").to.not.eq(
                    undefined,
                );
                expect(declined.vasg_Status).to.eq("declined");
            });
        });
    });

    it("leaves the Milk Station gap open — filling one position never fills another", () => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/gaps?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryId}`,
        ).then((resp) => {
            const here = resp.body.gaps.filter(
                (g) => g.occurrenceId === occurrenceId,
            );
            const positionIds = here.map((g) => g.positionId);
            expect(positionIds).to.include(positions["Milk Station"]);
            expect(positionIds).to.not.include(positions.Espresso);
            expect(positionIds).to.not.include(positions.Expeditor);
        });
    });

    it("refuses the same person on the same position twice (I1)", () => {
        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
            { positionId: positions.Espresso, personId: PERSON_SIGNS_UP },
            409,
        );
    });

    it("refuses a pool member who is not qualified for the position (I2)", () => {
        // Person 2 is in the pool and qualified for Setup/Cleanup only.
        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
            { positionId: positions.Espresso, personId: 2 },
            403,
        );
    });
});
