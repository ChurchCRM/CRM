/// <reference types="cypress" />

/**
 * Volunteer v2 — recurring schedules and occurrence generation (#9708, epic #9701).
 *
 * Normative sections of `.agents/skills/churchcrm/volunteer-v2-design.md`:
 * §2.8 (schedule + its invariants), §2.9 (occurrence, the two unique keys, the
 * "linked rows carry no times of their own" rule), §2.10 (requirement template
 * vs per-occurrence override), §3.3.2 (the schedule half of the API) and §3.4
 * (`VolunteerScheduleService`).
 *
 * What the issue actually promises, and where each promise is proven here:
 *
 *   "linked event occurrences remain authoritative"
 *       → `linked schedules` block: every occurrence carries `eventId`, the
 *         reported `start`/`end` come from `events_event`, and moving the event
 *         through the **events** API moves the occurrence's reported time with
 *         no V2 write at all (asserted against the raw row through `cy.dbQuery`).
 *
 *   "no second competing event occurrence is created for linked events"
 *       → the same block counts `events_event` before and after generation.
 *
 *   "occurrences can be generated idempotently"
 *       → a new schedule's Save generates it (D33); Generate after that returns
 *         `created: 0` and the occurrence count is unchanged
 *         (`vocc_schedule_event_uidx`, §2.9).
 *
 * Every occurrence is anchored to an event (D20), so there is no standalone
 * mode to prove here any more; the class, ministry and event modes, the
 * offsets and Staff this event live in `private.volunteer.anchored.spec.js`.
 *
 * Fixtures. Ministries, teams and positions go in through `cy.dbQuery()`. Scopes
 * go in through `POST /api/ministries/scopes` as admin. The seeded calendar has
 * only three events, all in 2016/2017, so the spec makes its own future events:
 * a weekly Wednesday series straight into `events_event`, and the linked block's
 * Sunday series through `POST /api/events/repeat`; both are deleted afterwards.
 *
 * Cleanup runs in `before` as well as `after` (cypress-testing.md): an `after`
 * hook does not run when the runner crashes mid-spec, and the next run must not
 * inherit rows. Deletion order is FK-safe, children first.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const HORIZON_URL = "/admin/api/system/config/iVolunteerSchedulingHorizonWeeks";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";
const PLAINAUTH_KEY = "plainauth.api.key";

const PERSON_COORDINATOR = 3; // tony.wade — every flag but Admin
const PERSON_PLAIN = 900; // john.plainauth — Notes only, no volunteer rights

const CHURCH_SERVICE_TYPE = 1; // seed.sql:410 — weekly, Sunday, 10:30

/** Prefix on every fixture name so cleanup can delete exactly what this spec made. */
const FIXTURE_PREFIX = "SCHED9708";
/** Title of the generated event series; also the schedule's title filter. */
const EVENT_TITLE = `${FIXTURE_PREFIX} Linked Service`;
/** The Wednesday series most schedules here follow — five weeks from the next Wednesday. */
const WEDNESDAY_TITLE = `${FIXTURE_PREFIX} Wednesday Night`;

let ministryA = 0;
let ministryB = 0;
let teamA1 = 0;
/** Ministry B's team — a schedule always names a team of its own ministry. */
let teamB1 = 0;
let positionOne = 0;
let positionTwo = 0;
let scopeIdCoordinator = 0;
let originalVersion = "v1";

/** The event ids created for the linked fixture, so they can be deleted again. */
let seriesEventIds = [];

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
        Cypress.testEnv(key),
        method,
        url,
        body,
        expectedStatus,
    );
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

/** `YYYY-MM-DD`, `offsetDays` from today, in the browser's own calendar. */
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

function cleanupFixtures() {
    // Children before parents: requirement → occurrence → schedule → position →
    // team → ministry. Occurrences and requirements would cascade, but deleting
    // them explicitly keeps the order readable and survives a partial fixture.
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
        `DELETE vpos FROM volunteer_position_vpos vpos
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
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
    // Every event this spec made, identified by its prefix.
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [
        `${FIXTURE_PREFIX}%`,
    ]);
}

/** One Church Service event per day offset, at the given wall-clock times. */
function createEvents(title, dayOffsets, startTime = "19:00:00", endTime = "20:30:00") {
    dayOffsets.forEach((offset) => {
        dbOk(
            `INSERT INTO events_event (event_type, event_title, event_desc, event_text, event_start, event_end, inactive)
             VALUES (?, ?, '', '', ?, ?, 0)`,
            [CHURCH_SERVICE_TYPE, title, `${isoDate(offset)} ${startTime}`, `${isoDate(offset)} ${endTime}`],
        );
    });
}

function createMinistry(suffix) {
    return dbOk(
        `INSERT INTO volunteer_ministry_vmin (vmin_Name, vmin_Description, vmin_Active, vmin_CreatedDate)
         VALUES (?, 'volunteer v2 schedule fixture', 1, NOW())`,
        [`${FIXTURE_PREFIX} ${suffix}`],
    ).then((rows) => rows.insertId);
}

function createTeam(ministryId, suffix) {
    return dbOk(
        `INSERT INTO volunteer_team_vtem (vtem_vmin_ID, vtem_Name, vtem_Description, vtem_Active)
         VALUES (?, ?, 'volunteer v2 schedule fixture', 1)`,
        [ministryId, `${FIXTURE_PREFIX} ${suffix}`],
    ).then((rows) => rows.insertId);
}

function createPosition(ministryId, teamId, name, order) {
    return dbOk(
        `INSERT INTO volunteer_position_vpos (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Description, vpos_Active, vpos_Order)
         VALUES (?, ?, ?, 'volunteer v2 schedule fixture', 1, ?)`,
        [ministryId, teamId, `${FIXTURE_PREFIX} ${name}`, order],
    ).then((rows) => rows.insertId);
}

/** A minimal valid linked-schedule body; callers override what they are testing. */
function linkedScheduleBody(overrides = {}) {
    return {
        name: `${FIXTURE_PREFIX} Linked`,
        // Every schedule belongs to a team, so the minimal VALID body names one.
        // The tests about a bad team still override it.
        teamId: teamA1,
        linkMode: "event_type",
        eventTypeId: CHURCH_SERVICE_TYPE,
        windowStart: isoDate(0),
        ...overrides,
    };
}

/** A schedule following this spec's own Wednesday series. */
function wednesdayScheduleBody(overrides = {}) {
    return linkedScheduleBody({
        name: `${FIXTURE_PREFIX} Wednesday`,
        titleFilter: WEDNESDAY_TITLE,
        ...overrides,
    });
}

function createSchedule(ministryId, body, key = ADMIN_KEY) {
    return createScheduleRun(ministryId, body, key).then((created) => created.schedule.id);
}

/** The new schedule and the run its Save made (D33). */
function createScheduleRun(ministryId, body, key = ADMIN_KEY) {
    return api(
        key,
        "POST",
        `/api/ministries/ministries/${ministryId}/schedules`,
        body,
        201,
    ).then((resp) => resp.body);
}

/**
 * A schedule whose occurrences are left for Generate: saved paused, so its Save
 * makes none (D33), then switched on, which generates nothing either.
 */
function createPausedThenActive(ministryId, body) {
    return createScheduleRun(ministryId, { ...body, active: false }).then((created) => {
        expect(created.generated, "a paused schedule's Save makes nothing").to.eq(null);
        api(ADMIN_KEY, "POST", `/api/ministries/schedules/${created.schedule.id}`, { active: true });

        return cy.wrap(created.schedule.id);
    });
}

// ── suite ──────────────────────────────────────────────────────────────────

before(() => {
    cy.rememberTestEnv(["admin.api.key", "user.api.key", "plainauth.api.key"]);
});

describe("Volunteer v2 — schedules and occurrence generation (#9708)", () => {
    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then(
            (resp) => {
                originalVersion = resp.body.value ?? resp.body.data ?? "v1";
            },
        );
        setVersion("v2");

        cleanupFixtures();

        const firstWednesday = daysToNext(3);
        createEvents(WEDNESDAY_TITLE, [0, 7, 14, 21, 28].map((week) => firstWednesday + week));

        createMinistry("Ministry A").then((id) => {
            ministryA = id;
            createTeam(ministryA, "Team A1").then((teamId) => {
                teamA1 = teamId;
                createPosition(ministryA, teamId, "Espresso", 1).then((p) => {
                    positionOne = p;
                });
                createPosition(ministryA, teamId, "Milk Station", 2).then(
                    (p) => {
                        positionTwo = p;
                    },
                );
            });
        });
        createMinistry("Ministry B").then((id) => {
            ministryB = id;
            createTeam(ministryB, "Team B1").then((teamId) => {
                teamB1 = teamId;
            });
        });

        // The coordinator persona: person 3 with a ministry scope on A only.
        cy.then(() => {
            api(
                ADMIN_KEY,
                "POST",
                "/api/ministries/scopes",
                {
                    personId: PERSON_COORDINATOR,
                    scopeType: "ministry",
                    scopeId: ministryA,
                },
                [200, 201],
            ).then((resp) => {
                scopeIdCoordinator = resp.body.scope.id;
            });
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion(originalVersion);
    });

    // ── §2.8 schedule CRUD and invariants ──────────────────────────────────

    describe("schedule CRUD (§2.8, §3.3.2)", () => {
        it("creates a linked schedule and echoes the stored row", () => {
            createSchedule(
                ministryA,
                linkedScheduleBody({ teamId: teamA1, titleFilter: WEDNESDAY_TITLE }),
            ).then((id) => {
                api(ADMIN_KEY, "GET", `/api/ministries/schedules/${id}`).then(
                    (resp) => {
                        const s = resp.body.schedule;
                        expect(s.ministryId).to.eq(ministryA);
                        expect(s.teamId).to.eq(teamA1);
                        expect(s.linkMode).to.eq("event_type");
                        expect(s.eventTypeId).to.eq(CHURCH_SERVICE_TYPE);
                        expect(s.titleFilter).to.eq(WEDNESDAY_TITLE);
                        expect(s.groupId).to.eq(null);
                        expect(s.eventId).to.eq(null);
                        expect(s.startOffsetMinutes).to.eq(0);
                        expect(s.endOffsetMinutes).to.eq(0);
                        // No recurrence or times of its own (D20).
                        expect(s).to.not.have.property("recurType");
                        expect(s).to.not.have.property("startTime");
                        // How far ahead is the church-wide horizon, not the schedule's (D31).
                        expect(s).to.not.have.property("generateAheadDays");
                        expect(s.horizonWeeks).to.eq(8);
                        expect(s.generateThrough).to.eq(isoDate(56));
                        expect(s.active).to.eq(true);
                    },
                );
                api(ADMIN_KEY, "DELETE", `/api/ministries/schedules/${id}`);
            });
        });

        it("lists a ministry's schedules and reports the occurrence count", () => {
            createPausedThenActive(ministryA, wednesdayScheduleBody()).then((id) => {
                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/ministries/ministries/${ministryA}/schedules`,
                ).then((resp) => {
                    const ids = resp.body.schedules.map((s) => s.id);
                    expect(ids).to.include(id);
                    const mine = resp.body.schedules.find((s) => s.id === id);
                    expect(mine.occurrenceCount).to.eq(0);
                });
                api(ADMIN_KEY, "DELETE", `/api/ministries/schedules/${id}`);
            });
        });

        it("generates a new schedule on Save, up to the horizon, and says so (D33)", () => {
            createScheduleRun(ministryA, wednesdayScheduleBody()).then((created) => {
                expect(created.generated).to.include({
                    created: 5,
                    existing: 0,
                    from: isoDate(0),
                    through: isoDate(56),
                    assigned: 0,
                    noEvents: false,
                });
                expect(created.generated.searched).to.include({ linkMode: "event_type", titleFilter: WEDNESDAY_TITLE });
                expect(created.schedule.occurrenceCount).to.eq(5);
                api(ADMIN_KEY, "DELETE", `/api/ministries/schedules/${created.schedule.id}`);
            });
        });

        it("updates a schedule's name and window", () => {
            createSchedule(ministryA, wednesdayScheduleBody()).then((id) => {
                api(ADMIN_KEY, "POST", `/api/ministries/schedules/${id}`, {
                    name: `${FIXTURE_PREFIX} Renamed`,
                    windowEnd: isoDate(120),
                    active: false,
                }).then((resp) => {
                    expect(resp.body.schedule.name).to.eq(
                        `${FIXTURE_PREFIX} Renamed`,
                    );
                    expect(resp.body.schedule.windowEnd).to.eq(isoDate(120));
                    expect(resp.body.schedule.active).to.eq(false);
                });
                api(ADMIN_KEY, "DELETE", `/api/ministries/schedules/${id}`);
            });
        });

        it("404s an unknown schedule id", () => {
            api(ADMIN_KEY, "GET", "/api/ministries/schedules/99999999", null, 404);
        });

        describe("§2.8 invariants are rejected with 400", () => {
            const cases = [
                [
                    "linked schedule with no event type",
                    linkedScheduleBody({ eventTypeId: null }),
                ],
                [
                    "a schedule carrying its own recurrence",
                    linkedScheduleBody({ recurType: "weekly" }),
                ],
                [
                    "a schedule carrying its own start time",
                    linkedScheduleBody({ startTime: "10:30:00" }),
                ],
                [
                    "the retired standalone link mode",
                    linkedScheduleBody({ linkMode: "standalone", eventTypeId: null }),
                ],
                [
                    "window that ends before it starts",
                    wednesdayScheduleBody({
                        windowStart: isoDate(30),
                        windowEnd: isoDate(10),
                    }),
                ],
                [
                    "an event type that does not exist",
                    linkedScheduleBody({ eventTypeId: 987654 }),
                ],
                [
                    "a team that belongs to another ministry",
                    linkedScheduleBody({ teamId: 987654 }),
                ],
            ];

            cases.forEach(([label, body]) => {
                it(`rejects ${label}`, () => {
                    api(
                        ADMIN_KEY,
                        "POST",
                        `/api/ministries/ministries/${ministryA}/schedules`,
                        body,
                        400,
                    );
                });
            });

            it("rejects an unknown link mode", () => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/ministries/${ministryA}/schedules`,
                    linkedScheduleBody({ linkMode: "sometimes" }),
                    400,
                );
            });
        });
    });

    // ── §2.10 requirements ─────────────────────────────────────────────────

    describe("staffing requirements (§2.10)", () => {
        let scheduleId = 0;
        let occurrenceId = 0;

        beforeEach(() => {
            createSchedule(
                ministryA,
                wednesdayScheduleBody(),
            ).then((id) => {
                scheduleId = id;
                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/ministries/occurrences?from=${isoDate(0)}&to=${isoDate(14)}&scheduleId=${id}`,
                ).then((resp) => {
                    occurrenceId = resp.body.occurrences[0].id;
                });
            });
        });

        afterEach(() => {
            api(ADMIN_KEY, "DELETE", `/api/ministries/schedules/${scheduleId}`);
        });

        it("upserts a template requirement on the schedule", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/requirements`,
                { positionId: positionOne, minCount: 1, maxCount: 1 },
                201,
            ).then((first) => {
                const reqId = first.body.requirement.id;
                expect(first.body.requirement.scheduleId).to.eq(scheduleId);
                expect(first.body.requirement.occurrenceId).to.eq(null);

                // Same position again → upsert on vreq_schedule_position_uidx:
                // 200, the SAME row, new counts — never a duplicate, never 409.
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${scheduleId}/requirements`,
                    { positionId: positionOne, minCount: 2, maxCount: 3 },
                    200,
                ).then((second) => {
                    expect(second.body.requirement.id).to.eq(reqId);
                    expect(second.body.requirement.minCount).to.eq(2);
                    expect(second.body.requirement.maxCount).to.eq(3);
                });

                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/ministries/schedules/${scheduleId}/requirements`,
                ).then((resp) => {
                    expect(resp.body.requirements).to.have.length(1);
                });
            });
        });

        it("rejects a maxCount below minCount and a negative minCount", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/requirements`,
                { positionId: positionOne, minCount: 3, maxCount: 1 },
                400,
            );
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/requirements`,
                { positionId: positionOne, minCount: -1 },
                400,
            );
        });

        it("rejects a position from another ministry", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/requirements`,
                { positionId: 987654, minCount: 1 },
                400,
            );
        });

        it("merges a per-occurrence override over the template (getEffectiveRequirements)", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/requirements`,
                { positionId: positionOne, minCount: 1, maxCount: 1 },
                201,
            );
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/requirements`,
                { positionId: positionTwo, minCount: 1, maxCount: 1 },
                201,
            );

            cy.then(() => {
                // "this week we need four, not one" — an override on ONE position.
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/occurrences/${occurrenceId}/requirements`,
                    { positionId: positionOne, minCount: 4, maxCount: 4 },
                    201,
                ).then((resp) => {
                    expect(resp.body.requirement.occurrenceId).to.eq(
                        occurrenceId,
                    );
                    expect(resp.body.requirement.scheduleId).to.eq(null);
                });

                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/ministries/occurrences/${occurrenceId}`,
                ).then((resp) => {
                    const reqs = resp.body.requirements;
                    expect(reqs).to.have.length(2);
                    const overridden = reqs.find(
                        (r) => r.positionId === positionOne,
                    );
                    const inherited = reqs.find(
                        (r) => r.positionId === positionTwo,
                    );
                    expect(overridden.minCount).to.eq(4);
                    expect(overridden.source).to.eq("occurrence");
                    expect(inherited.minCount).to.eq(1);
                    expect(inherited.source).to.eq("schedule");
                });
            });
        });

        it("stores exactly one parent per requirement row (vreq_one_parent_chk)", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/requirements`,
                { positionId: positionOne, minCount: 1 },
                201,
            );
            cy.then(() => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/occurrences/${occurrenceId}/requirements`,
                    { positionId: positionOne, minCount: 2 },
                    201,
                );
            });
            cy.then(() => {
                dbOk(
                    `SELECT vreq_vsch_ID, vreq_vocc_ID FROM volunteer_requirement_vreq
                      WHERE vreq_vsch_ID = ? OR vreq_vocc_ID = ?`,
                    [scheduleId, occurrenceId],
                ).then((rows) => {
                    expect(rows).to.have.length(2);
                    rows.forEach((row) => {
                        const hasSchedule = row.vreq_vsch_ID !== null;
                        const hasOccurrence = row.vreq_vocc_ID !== null;
                        expect(hasSchedule).to.not.eq(hasOccurrence);
                    });
                });
            });
        });

        it("deletes a requirement", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/requirements`,
                { positionId: positionOne, minCount: 1 },
                201,
            ).then((resp) => {
                const reqId = resp.body.requirement.id;
                api(
                    ADMIN_KEY,
                    "DELETE",
                    `/api/ministries/requirements/${reqId}`,
                );
                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/ministries/schedules/${scheduleId}/requirements`,
                ).then((after) => {
                    expect(after.body.requirements).to.have.length(0);
                });
                api(
                    ADMIN_KEY,
                    "DELETE",
                    `/api/ministries/requirements/${reqId}`,
                    null,
                    404,
                );
            });
        });
    });

    // ── §6.5 scenario 5, linked half ───────────────────────────────────────

    describe("linked schedules — the event occurrence is authoritative", () => {
        let scheduleId = 0;
        let seriesStart = "";
        let seriesEnd = "";
        let eventsBeforeSave = 0;
        let savedRun = null;

        before(() => {
            // The seed has three events, all in the past, so build a future
            // weekly series of our own through the EVENTS api — V2 must never
            // create events_event rows itself.
            seriesStart = isoDate(daysToNext(0)); // the next Sunday
            seriesEnd = isoDate(daysToNext(0) + 21); // four Sundays in total

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
                expect(seriesEventIds.length).to.eq(4);
            });

            dbOk(`SELECT COUNT(*) AS c FROM events_event`).then((rows) => {
                eventsBeforeSave = rows[0].c;
            });
            cy.then(() => {
                createScheduleRun(
                    ministryA,
                    linkedScheduleBody({
                        name: `${FIXTURE_PREFIX} Linked Worship`,
                        titleFilter: EVENT_TITLE,
                        windowStart: seriesStart,
                    }),
                ).then((created) => {
                    scheduleId = created.schedule.id;
                    savedRun = created.generated;
                });
            });
        });

        it("creates one occurrence per event, with no times of its own and no new event rows", () => {
            // The schedule's Save generated it (D33).
            expect(savedRun).to.include({ created: 4, existing: 0 });
            dbOk(`SELECT COUNT(*) AS c FROM events_event`).then((after) => {
                // "No second competing event occurrence is created."
                expect(after[0].c).to.eq(eventsBeforeSave);
            });
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/generate`,
                { through: seriesEnd },
            ).then((resp) => {
                expect(resp.body).to.include({ created: 0, existing: 4, through: seriesEnd });
            });

            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}`,
            ).then((resp) => {
                const occ = resp.body.occurrences;
                expect(occ).to.have.length(4);
                occ.forEach((o) => {
                    expect(o.eventId).to.be.a("number");
                    expect(seriesEventIds).to.include(o.eventId);
                    // §2.9: the occurrence stores no times of its own and reports the event's.
                    expect(o).to.not.have.property("startDateTime");
                    expect(o.start).to.contain("10:30:00");
                    expect(o.end).to.contain("11:45:00");
                });
            });
        });

        it("reports the event's new time after the EVENTS api moves it, with no V2 write", () => {
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}`,
            ).then((resp) => {
                const target = resp.body.occurrences[0];
                const newStart = `${seriesStart} 14:15:00`;
                const newEnd = `${seriesStart} 15:30:00`;

                api(ADMIN_KEY, "POST", `/api/events/${target.eventId}/time`, {
                    startTime: newStart,
                    endTime: newEnd,
                });

                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/ministries/occurrences/${target.id}`,
                ).then((after) => {
                    expect(after.body.occurrence.start).to.eq(newStart);
                    expect(after.body.occurrence.end).to.eq(newEnd);
                });

                // The V2 row itself was never written: same event, same date, same
                // generated timestamp. That is what "the event is the source of
                // truth" means operationally (§2.9).
                dbOk(
                    `SELECT vocc_event_id, DATE_FORMAT(vocc_OccurrenceDate, '%Y-%m-%d') AS d FROM volunteer_occurrence_vocc WHERE vocc_ID = ?`,
                    [target.id],
                ).then((rows) => {
                    expect(Number(rows[0].vocc_event_id)).to.eq(target.eventId);
                    expect(rows[0].d).to.eq(target.occurrenceDate);
                });
            });
        });

        it("is idempotent: a second generation over the same window creates nothing", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/generate`,
                { through: seriesEnd },
            ).then((resp) => {
                expect(resp.body.created).to.eq(0);
                expect(resp.body.existing).to.eq(4);
            });

            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}`,
            ).then((resp) => {
                expect(resp.body.occurrences).to.have.length(4);
            });
        });

        // ── `?text=` on the occurrence list — the Occurrences tab's Event box ──
        //
        // An occurrence's TITLE is its schedule's name, except when it is linked to a
        // calendar event, where the event owns the words a coordinator would search
        // for (D4: a linked occurrence keeps no times and no title of its own). This
        // fixture is the only place in the suite where the two differ — the schedule
        // is "… Linked Worship" and its events are "… Linked Service" — which is what
        // makes the two halves of the filter separable.
        describe("filtering the occurrence list by title (?text=)", () => {
            const listByText = (text) =>
                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/ministries/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}` +
                        `&text=${encodeURIComponent(text)}`,
                );

            it("matches the schedule's own name", () => {
                listByText("Linked Worship").then((resp) => {
                    expect(resp.body.occurrences).to.have.length(4);
                    resp.body.occurrences.forEach((o) => {
                        expect(o.scheduleId).to.eq(scheduleId);
                    });
                });
            });

            it("matches the LINKED EVENT's title, which the schedule's name does not contain", () => {
                // "Service" appears only on the events; the schedule is "Linked Worship".
                // Lower case on purpose — the match is case-insensitive.
                listByText("linked service").then((resp) => {
                    expect(resp.body.occurrences).to.have.length(4);
                    resp.body.occurrences.forEach((o) => {
                        expect(o.eventId, "matched through the event, so it is linked").to.be.a("number");
                    });
                });
            });

            it("returns nothing when neither the schedule nor the event matches", () => {
                listByText(`${FIXTURE_PREFIX} NoSuchTitleAnywhere`).then((resp) => {
                    expect(resp.body.occurrences).to.have.length(0);
                });
            });

            it("treats % and _ as literal characters, not LIKE wildcards", () => {
                // Unescaped, `%` would match every title there is and this would
                // return the whole window.
                listByText("%").then((resp) => {
                    expect(resp.body.occurrences).to.have.length(0);
                });
                listByText("Linked_Worship").then((resp) => {
                    expect(resp.body.occurrences).to.have.length(0);
                });
            });

            it("is ignored when blank, rather than matching nothing", () => {
                listByText("   ").then((resp) => {
                    expect(resp.body.occurrences).to.have.length(4);
                });
            });

            it("combines with ?teamId= rather than replacing it", () => {
                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/ministries/occurrences?from=${seriesStart}&to=${seriesEnd}&teamId=${teamA1}&text=Linked`,
                ).then((resp) => {
                    expect(resp.body.occurrences).to.have.length(4);
                });

                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/ministries/occurrences?from=${seriesStart}&to=${seriesEnd}&teamId=${teamB1}&text=Linked`,
                ).then((resp) => {
                    expect(resp.body.occurrences, "another team owns no linked schedule").to.have.length(0);
                });
            });
        });

        it("honours the title filter", () => {
            // The same event type with a title no upcoming event has is refused
            // outright — the title, not just the type, is part of the binding
            // (§2.8, F7/F8), and a schedule follows events that exist (D31).
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/ministries/${ministryA}/schedules`,
                linkedScheduleBody({
                    name: `${FIXTURE_PREFIX} Linked Nothing`,
                    titleFilter: `${FIXTURE_PREFIX} NoSuchTitle`,
                    windowStart: seriesStart,
                }),
                400,
            );
        });

        it("cancels a single occurrence without touching the schedule", () => {
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}`,
            ).then((resp) => {
                const target = resp.body.occurrences[1];
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/occurrences/${target.id}/status`,
                    { status: "cancelled" },
                ).then((after) => {
                    expect(after.body.occurrence.status).to.eq("cancelled");
                });

                // Cancelling is not deleting: regeneration must not resurrect
                // it as a second row (§2.9 idempotency by unique key).
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${scheduleId}/generate`,
                    { through: seriesEnd },
                ).then((gen) => {
                    expect(gen.body.created).to.eq(0);
                });
            });
        });
    });

    // ── The generation range: window, horizon, cap ─────────────────────────

    describe("the generation range", () => {
        after(() => {
            cy.makePrivateAdminAPICall("POST", HORIZON_URL, { value: "8" }, 200);
        });

        it("defaults `through` to today + the scheduling horizon (D31)", () => {
            cy.makePrivateAdminAPICall("POST", HORIZON_URL, { value: "2" }, 200);
            createScheduleRun(
                ministryA,
                wednesdayScheduleBody({ name: `${FIXTURE_PREFIX} Default Through` }),
            ).then((created) => {
                expect(created.generated.through, "Save reaches the horizon").to.eq(isoDate(14));
                expect(created.generated.created).to.be.within(1, 2);
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${created.schedule.id}/generate`,
                    {},
                ).then((resp) => {
                    expect(resp.body.through).to.eq(isoDate(14));
                    expect(resp.body.created).to.eq(0);
                });
                api(ADMIN_KEY, "DELETE", `/api/ministries/schedules/${created.schedule.id}`);
            });
            cy.makePrivateAdminAPICall("POST", HORIZON_URL, { value: "8" }, 200);
        });

        it("never generates outside the schedule's own window", () => {
            createScheduleRun(
                ministryA,
                wednesdayScheduleBody({
                    name: `${FIXTURE_PREFIX} Windowed`,
                    windowStart: isoDate(0),
                    windowEnd: isoDate(10),
                }),
            ).then((created) => {
                // windowEnd clamps the run; at most two Wednesdays fit in 10 days.
                expect(created.generated.created).to.be.within(1, 2);
                expect(created.generated.through).to.eq(isoDate(10));
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${created.schedule.id}/generate`,
                    { through: isoDate(90) },
                ).then((resp) => {
                    expect(resp.body).to.include({ created: 0, through: isoDate(10) });
                });
                api(ADMIN_KEY, "DELETE", `/api/ministries/schedules/${created.schedule.id}`);
            });
        });

        it("rejects a generation run that would blow the occurrence cap", () => {
            const title = `${FIXTURE_PREFIX} Daily`;
            // 367 events, one more than MAX_GENERATED_OCCURRENCES, packed into the next 50
            // days so every one of them is inside the default 8-week horizon (D31).
            dbOk(
                `INSERT INTO events_event (event_type, event_title, event_desc, event_text, event_start, event_end, inactive)
                 SELECT ?, ?, '', '', CURDATE() + INTERVAL (n MOD 50) + 1 DAY + INTERVAL 18 HOUR, CURDATE() + INTERVAL (n MOD 50) + 1 DAY + INTERVAL 19 HOUR, 0
                   FROM (SELECT a.d + b.d * 10 + c.d * 100 AS n
                           FROM (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                                 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
                          CROSS JOIN (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                                 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
                          CROSS JOIN (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) c) seq
                  WHERE n BETWEEN 1 AND 367`,
                [CHURCH_SERVICE_TYPE, title],
            );
            // A new schedule generates on Save (D33), so the cap refuses the schedule itself,
            // and nothing is left behind.
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/ministries/${ministryA}/schedules`,
                linkedScheduleBody({ name: `${FIXTURE_PREFIX} Huge`, titleFilter: title }),
                400,
            ).then((resp) => {
                expect(resp.body.message).to.contain("Too many occurrences");
            });
            dbOk(`SELECT COUNT(*) AS n FROM volunteer_schedule_vsch WHERE vsch_Name = ?`, [`${FIXTURE_PREFIX} Huge`]).then(
                (rows) => expect(Number(rows[0].n)).to.eq(0),
            );

            // An existing schedule's Generate is refused the same way.
            createPausedThenActive(
                ministryA,
                linkedScheduleBody({ name: `${FIXTURE_PREFIX} Huge Paused`, titleFilter: title }),
            ).then((id) => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${id}/generate`,
                    { through: isoDate(400) },
                    400,
                );
                api(ADMIN_KEY, "DELETE", `/api/ministries/schedules/${id}`);
            });
        });

        it("rejects a malformed `through`", () => {
            createSchedule(ministryA, wednesdayScheduleBody({ name: `${FIXTURE_PREFIX} Malformed` })).then((id) => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${id}/generate`,
                    { through: "not-a-date" },
                    400,
                );
                api(ADMIN_KEY, "DELETE", `/api/ministries/schedules/${id}`);
            });
        });
    });

    // ── Generate with defaults (review, 2026-09-18) ────────────────────────

    describe("generation with default volunteers", () => {
        let scheduleId = 0;
        let through = "";

        function qualify(personId, positionId) {
            return dbOk(
                `INSERT INTO volunteer_qualification_vqal (vqal_per_ID, vqal_vpos_ID, vqal_Active, vqal_GrantedDate)
                 VALUES (?, ?, 1, NOW())`,
                [personId, positionId],
            );
        }

        before(() => {
            through = isoDate(21);
            // Two positions in the plan, one qualified person each. Neither is in a
            // pool: the fixture ministry has no Group, and the defaults carry the
            // same out-of-pool override the Assign dialog does.
            qualify(PERSON_COORDINATOR, positionOne);
            qualify(PERSON_PLAIN, positionTwo);
            // From tomorrow: an occurrence of TODAY whose start time has passed is
            // "already happened" and refuses assignments, which would show up here
            // as skipped defaults late in the day.
            createPausedThenActive(
                ministryA,
                wednesdayScheduleBody({
                    name: `${FIXTURE_PREFIX} Defaults`,
                    windowStart: isoDate(1),
                    requirements: [
                        { positionId: positionOne, minCount: 1, maxCount: 1 },
                        { positionId: positionTwo, minCount: 1, maxCount: 2 },
                    ],
                }),
            ).then((id) => {
                scheduleId = id;
            });
        });

        it("lists who may fill a position before any occurrence exists", () => {
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/schedules/${scheduleId}/eligible?positionId=${positionOne}`,
            ).then((resp) => {
                const ids = resp.body.people.map((p) => p.personId);
                expect(ids).to.include(PERSON_COORDINATOR);
                expect(ids).to.not.include(PERSON_PLAIN);
                const me = resp.body.people.find((p) => p.personId === PERSON_COORDINATOR);
                expect(me.lastServedDate).to.eq(null);
                expect(me.inPool).to.eq(false);
                expect(me.conflictPositionId).to.eq(null);
            });
            // A position of another team is not this schedule's to fill.
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/schedules/${scheduleId}/eligible?positionId=999999`,
                null,
                400,
            );
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/schedules/${scheduleId}/eligible`,
                null,
                400,
            );
        });

        it("refuses a default who is not qualified, and generates nothing", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/generate`,
                {
                    through,
                    requirements: [{ positionId: positionOne, defaults: [{ personId: PERSON_PLAIN, accepted: true }] }],
                },
                403,
            );
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/occurrences?from=${isoDate(0)}&to=${through}&scheduleId=${scheduleId}`,
            ).then((resp) => {
                expect(resp.body.occurrences, "nothing was generated").to.have.length(0);
            });
        });

        it("refuses a default on a position of another ministry (400)", () => {
            dbOk(
                `INSERT INTO volunteer_position_vpos (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Description, vpos_Active, vpos_Order)
                 VALUES (?, ?, ?, 'volunteer v2 schedule fixture', 1, 9)`,
                [ministryB, teamB1, `${FIXTURE_PREFIX} Foreign`],
            ).then((rows) => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${scheduleId}/generate`,
                    {
                        through,
                        requirements: [{ positionId: rows.insertId, defaults: [{ personId: PERSON_COORDINATOR }] }],
                    },
                    400,
                );
            });
        });

        it("assigns the defaults on every occurrence it creates, accepted or pending", () => {
            let created = 0;
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/generate`,
                {
                    through,
                    requirements: [
                        { positionId: positionOne, defaults: [{ personId: PERSON_COORDINATOR, accepted: true }] },
                        { positionId: positionTwo, defaults: [{ personId: PERSON_PLAIN, accepted: false }] },
                    ],
                },
            ).then((resp) => {
                created = resp.body.created;
                expect(created).to.be.greaterThan(0);
                expect(resp.body.assigned).to.eq(created * 2);
                expect(resp.body.skipped).to.eq(0);
            });

            cy.then(() => {
                dbOk(
                    `SELECT vasg.vasg_ID, vasg.vasg_per_ID, vasg.vasg_vpos_ID, vasg.vasg_Status, vasg.vasg_Source,
                            vasg.vasg_RespondedDate,
                            (SELECT COUNT(*) FROM volunteer_response_vrsp vrsp
                              WHERE vrsp.vrsp_vasg_ID = vasg.vasg_ID AND vrsp.vrsp_Channel = 'coordinator'
                                AND vrsp.vrsp_Response = 'accepted') AS responses,
                            (SELECT COUNT(*) FROM volunteer_notification_vntf vntf
                              WHERE vntf.vntf_vasg_ID = vasg.vasg_ID AND vntf.vntf_Type = 'assignment') AS asks
                       FROM volunteer_assignment_vasg vasg
                       JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
                      WHERE vocc.vocc_vsch_ID = ?`,
                    [scheduleId],
                ).then((rows) => {
                    expect(rows).to.have.length(created * 2);
                    const accepted = rows.filter((r) => Number(r.vasg_per_ID) === PERSON_COORDINATOR);
                    const pending = rows.filter((r) => Number(r.vasg_per_ID) === PERSON_PLAIN);
                    expect(accepted).to.have.length(created);
                    expect(pending).to.have.length(created);
                    for (const row of accepted) {
                        expect(row.vasg_Status).to.eq("accepted");
                        expect(row.vasg_Source).to.eq("coordinator");
                        expect(row.vasg_RespondedDate, "an accepted default carries its response date").to.not.eq(null);
                        // Recorded as the coordinator's entry, and nobody is asked to answer
                        // a question that has already been answered.
                        expect(Number(row.responses)).to.eq(1);
                        expect(Number(row.asks)).to.eq(0);
                    }
                    for (const row of pending) {
                        expect(row.vasg_Status).to.eq("pending");
                        expect(row.vasg_RespondedDate).to.eq(null);
                        expect(Number(row.responses)).to.eq(0);
                        expect(Number(row.asks)).to.eq(1);
                    }
                });
            });
        });

        it("touches nothing on a second run: the occurrences exist, so no default is assigned again", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}/generate`,
                {
                    through,
                    requirements: [{ positionId: positionOne, defaults: [{ personId: PERSON_COORDINATOR, accepted: true }] }],
                },
            ).then((resp) => {
                expect(resp.body.created).to.eq(0);
                expect(resp.body.assigned).to.eq(0);
            });
        });

        it("accepts a blank default and generates with no assignments", () => {
            createPausedThenActive(
                ministryA,
                wednesdayScheduleBody({
                    name: `${FIXTURE_PREFIX} Blank Defaults`,
                    windowStart: isoDate(1),
                    requirements: [{ positionId: positionOne, minCount: 1, maxCount: 1 }],
                }),
            ).then((id) => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${id}/generate`,
                    { through, requirements: [{ positionId: positionOne, defaults: [{ personId: "" }] }] },
                ).then((resp) => {
                    expect(resp.body.created).to.be.greaterThan(0);
                    expect(resp.body.assigned).to.eq(0);
                });
            });
        });
    });

    // ── §3.3.2 / M9 window validation on the occurrence list ───────────────

    describe("occurrence list window (M9)", () => {
        it("requires from and to", () => {
            api(ADMIN_KEY, "GET", "/api/ministries/occurrences", null, 400);
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/occurrences?from=${isoDate(0)}`,
                null,
                400,
            );
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/occurrences?to=${isoDate(30)}`,
                null,
                400,
            );
        });

        it("rejects a malformed or inverted window", () => {
            api(
                ADMIN_KEY,
                "GET",
                "/api/ministries/occurrences?from=yesterday&to=tomorrow",
                null,
                400,
            );
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/occurrences?from=${isoDate(30)}&to=${isoDate(0)}`,
                null,
                400,
            );
        });

        it("reports the hard cap alongside the rows", () => {
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/occurrences?from=${isoDate(0)}&to=${isoDate(30)}`,
            ).then((resp) => {
                expect(resp.body).to.have.property("limit", 500);
                expect(resp.body).to.have.property("capped", false);
            });
        });
    });

    // ── §4.4 / §4.6 scope enforcement ──────────────────────────────────────

    describe("scope enforcement (§4.4, §4.6)", () => {
        let scheduleInA = 0;
        let scheduleInB = 0;

        before(() => {
            createSchedule(
                ministryA,
                wednesdayScheduleBody({
                    name: `${FIXTURE_PREFIX} Scope A`,
                }),
            ).then((id) => {
                scheduleInA = id;
            });
            // wednesdayScheduleBody() defaults to ministry A's team, which is the
            // wrong ministry here — a schedule has to name a team of its OWN ministry.
            createSchedule(
                ministryB,
                wednesdayScheduleBody({
                    name: `${FIXTURE_PREFIX} Scope B`,
                    teamId: teamB1,
                }),
            ).then((id) => {
                scheduleInB = id;
            });
        });

        after(() => {
            api(ADMIN_KEY, "DELETE", `/api/ministries/schedules/${scheduleInA}`);
            api(ADMIN_KEY, "DELETE", `/api/ministries/schedules/${scheduleInB}`);
        });

        it("lets a ministry coordinator read and write their own ministry", () => {
            api(
                COORDINATOR_KEY,
                "GET",
                `/api/ministries/ministries/${ministryA}/schedules`,
            ).then((resp) => {
                expect(resp.body.schedules.map((s) => s.id)).to.include(
                    scheduleInA,
                );
            });
            api(
                COORDINATOR_KEY,
                "GET",
                `/api/ministries/schedules/${scheduleInA}`,
            );
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleInA}/generate`,
                { through: isoDate(14) },
            );
        });

        it("denies that coordinator every route into another ministry", () => {
            api(
                COORDINATOR_KEY,
                "GET",
                `/api/ministries/ministries/${ministryB}/schedules`,
                null,
                403,
            );
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/ministries/ministries/${ministryB}/schedules`,
                wednesdayScheduleBody(),
                403,
            );
            api(
                COORDINATOR_KEY,
                "GET",
                `/api/ministries/schedules/${scheduleInB}`,
                null,
                403,
            );
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleInB}/generate`,
                { through: isoDate(14) },
                403,
            );
            api(
                COORDINATOR_KEY,
                "DELETE",
                `/api/ministries/schedules/${scheduleInB}`,
                null,
                403,
            );
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleInB}/requirements`,
                { positionId: positionOne, minCount: 1 },
                403,
            );
        });

        it("scopes the occurrence list in the query, not in the client", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleInB}/generate`,
                { through: isoDate(14) },
            );
            cy.then(() => {
                api(
                    COORDINATOR_KEY,
                    "GET",
                    `/api/ministries/occurrences?from=${isoDate(0)}&to=${isoDate(14)}`,
                ).then((resp) => {
                    const ministries = resp.body.occurrences.map(
                        (o) => o.ministryId,
                    );
                    expect(ministries).to.not.include(ministryB);
                });
            });
        });

        it("denies a person with no volunteer authority at all (403)", () => {
            api(
                PLAINAUTH_KEY,
                "GET",
                `/api/ministries/ministries/${ministryA}/schedules`,
                null,
                403,
            );
            api(
                PLAINAUTH_KEY,
                "GET",
                `/api/ministries/schedules/${scheduleInA}`,
                null,
                403,
            );
            api(
                PLAINAUTH_KEY,
                "GET",
                `/api/ministries/occurrences?from=${isoDate(0)}&to=${isoDate(14)}`,
                null,
                403,
            );
        });
    });

    // ── rollout gate ───────────────────────────────────────────────────────

    describe("rollout gate (#9704)", () => {
        after(() => {
            setVersion("v2");
        });

        it("403s the whole surface when V2 is not enabled", () => {
            setVersion("v1");
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/ministries/${ministryA}/schedules`,
                null,
                403,
            );
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/occurrences?from=${isoDate(0)}&to=${isoDate(7)}`,
                null,
                403,
            );
        });
    });
});
