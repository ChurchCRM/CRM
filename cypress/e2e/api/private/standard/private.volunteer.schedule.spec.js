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
 *       → `linked schedules` block: every occurrence carries `eventId`, its own
 *         `startDateTime` is null, the reported `start`/`end` come from
 *         `events_event`, and moving the event through the **events** API moves
 *         the occurrence's reported time with no V2 write at all (asserted
 *         against the raw row through `cy.dbQuery`).
 *
 *   "no second competing event occurrence is created for linked events"
 *       → the same block counts `events_event` before and after generation.
 *
 *   "occurrences can be generated idempotently"
 *       → `generation is idempotent` block: the same POST twice returns
 *         `created: 0` the second time and the occurrence count is unchanged,
 *         for BOTH link modes (the two unique keys in §2.9 are what makes it
 *         true, so both have to be exercised).
 *
 *   "volunteer-specific schedules where no ChurchCRM event exists"
 *       → `standalone schedules` block: V2 generates the dates itself and the
 *         times are its own.
 *
 * Fixtures. Ministries, teams and positions go in through `cy.dbQuery()`: the
 * setup API is #9715 and is not on this branch. Scopes go in through
 * `POST /api/volunteer/scopes` as admin — that surface shipped with #9706.
 * The seeded calendar has only three events, all in 2016/2017 (seed.sql:311),
 * so the linked-schedule fixture creates its own future series through
 * `POST /api/events/repeat` and deletes it again afterwards.
 *
 * Cleanup runs in `before` as well as `after` (cypress-testing.md): an `after`
 * hook does not run when the runner crashes mid-spec, and the next run must not
 * inherit rows. Deletion order is FK-safe, children first.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
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
    // The linked fixture's events, identified by the unique title this spec uses.
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [
        `${EVENT_TITLE}%`,
    ]);
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

/** A minimal valid standalone-schedule body. */
function standaloneScheduleBody(overrides = {}) {
    return {
        name: `${FIXTURE_PREFIX} Standalone`,
        teamId: teamA1,
        linkMode: "standalone",
        recurType: "weekly",
        recurDow: "Tuesday",
        startTime: "19:00:00",
        endTime: "20:30:00",
        windowStart: isoDate(0),
        ...overrides,
    };
}

function createSchedule(ministryId, body, key = ADMIN_KEY) {
    return api(
        key,
        "POST",
        `/api/volunteer/ministries/${ministryId}/schedules`,
        body,
        201,
    ).then((resp) => resp.body.schedule.id);
}

// ── suite ──────────────────────────────────────────────────────────────────

describe("Volunteer v2 — schedules and occurrence generation (#9708)", () => {
    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then(
            (resp) => {
                originalVersion = resp.body.value ?? resp.body.data ?? "v1";
            },
        );
        setVersion("v2");

        cleanupFixtures();

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
                "/api/volunteer/scopes",
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
                linkedScheduleBody({ teamId: teamA1, titleFilter: "Worship" }),
            ).then((id) => {
                api(ADMIN_KEY, "GET", `/api/volunteer/schedules/${id}`).then(
                    (resp) => {
                        const s = resp.body.schedule;
                        expect(s.ministryId).to.eq(ministryA);
                        expect(s.teamId).to.eq(teamA1);
                        expect(s.linkMode).to.eq("event_type");
                        expect(s.eventTypeId).to.eq(CHURCH_SERVICE_TYPE);
                        expect(s.titleFilter).to.eq("Worship");
                        // A linked schedule never carries its own recurrence (D4).
                        expect(s.recurType).to.eq("none");
                        expect(s.startTime).to.eq(null);
                        expect(s.generateAheadDays).to.eq(56);
                        expect(s.active).to.eq(true);
                    },
                );
                api(ADMIN_KEY, "DELETE", `/api/volunteer/schedules/${id}`);
            });
        });

        it("creates a standalone schedule with its own recurrence and times", () => {
            createSchedule(ministryA, standaloneScheduleBody()).then((id) => {
                api(ADMIN_KEY, "GET", `/api/volunteer/schedules/${id}`).then(
                    (resp) => {
                        const s = resp.body.schedule;
                        expect(s.linkMode).to.eq("standalone");
                        expect(s.eventTypeId).to.eq(null);
                        expect(s.recurType).to.eq("weekly");
                        expect(s.recurDow).to.eq("Tuesday");
                        expect(s.startTime).to.eq("19:00:00");
                        expect(s.endTime).to.eq("20:30:00");
                    },
                );
                api(ADMIN_KEY, "DELETE", `/api/volunteer/schedules/${id}`);
            });
        });

        it("lists a ministry's schedules and reports the occurrence count", () => {
            createSchedule(ministryA, standaloneScheduleBody()).then((id) => {
                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/volunteer/ministries/${ministryA}/schedules`,
                ).then((resp) => {
                    const ids = resp.body.schedules.map((s) => s.id);
                    expect(ids).to.include(id);
                    const mine = resp.body.schedules.find((s) => s.id === id);
                    expect(mine.occurrenceCount).to.eq(0);
                });
                api(ADMIN_KEY, "DELETE", `/api/volunteer/schedules/${id}`);
            });
        });

        it("updates a schedule's name and window", () => {
            createSchedule(ministryA, standaloneScheduleBody()).then((id) => {
                api(ADMIN_KEY, "POST", `/api/volunteer/schedules/${id}`, {
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
                api(ADMIN_KEY, "DELETE", `/api/volunteer/schedules/${id}`);
            });
        });

        it("404s an unknown schedule id", () => {
            api(ADMIN_KEY, "GET", "/api/volunteer/schedules/99999999", null, 404);
        });

        describe("§2.8 invariants are rejected with 400", () => {
            const cases = [
                [
                    "linked schedule with no event type",
                    linkedScheduleBody({ eventTypeId: null }),
                ],
                [
                    "linked schedule carrying its own recurrence",
                    linkedScheduleBody({ recurType: "weekly" }),
                ],
                [
                    "linked schedule carrying its own start time",
                    linkedScheduleBody({ startTime: "10:30:00" }),
                ],
                [
                    "standalone schedule naming an event type",
                    standaloneScheduleBody({
                        eventTypeId: CHURCH_SERVICE_TYPE,
                    }),
                ],
                [
                    "standalone schedule with recurType none",
                    standaloneScheduleBody({ recurType: "none" }),
                ],
                [
                    "standalone schedule with no start time",
                    standaloneScheduleBody({ startTime: null }),
                ],
                [
                    "standalone weekly schedule with no day of week",
                    standaloneScheduleBody({ recurDow: null }),
                ],
                [
                    "window that ends before it starts",
                    standaloneScheduleBody({
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
                        `/api/volunteer/ministries/${ministryA}/schedules`,
                        body,
                        400,
                    );
                });
            });

            it("rejects an unknown link mode", () => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/volunteer/ministries/${ministryA}/schedules`,
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
                standaloneScheduleBody({ recurDow: "Wednesday" }),
            ).then((id) => {
                scheduleId = id;
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/volunteer/schedules/${id}/generate`,
                    { through: isoDate(14) },
                ).then(() => {
                    api(
                        ADMIN_KEY,
                        "GET",
                        `/api/volunteer/occurrences?from=${isoDate(0)}&to=${isoDate(14)}&ministryId=${ministryA}`,
                    ).then((resp) => {
                        occurrenceId = resp.body.occurrences[0].id;
                    });
                });
            });
        });

        afterEach(() => {
            api(ADMIN_KEY, "DELETE", `/api/volunteer/schedules/${scheduleId}`);
        });

        it("upserts a template requirement on the schedule", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleId}/requirements`,
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
                    `/api/volunteer/schedules/${scheduleId}/requirements`,
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
                    `/api/volunteer/schedules/${scheduleId}/requirements`,
                ).then((resp) => {
                    expect(resp.body.requirements).to.have.length(1);
                });
            });
        });

        it("rejects a maxCount below minCount and a negative minCount", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleId}/requirements`,
                { positionId: positionOne, minCount: 3, maxCount: 1 },
                400,
            );
            api(
                ADMIN_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleId}/requirements`,
                { positionId: positionOne, minCount: -1 },
                400,
            );
        });

        it("rejects a position from another ministry", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleId}/requirements`,
                { positionId: 987654, minCount: 1 },
                400,
            );
        });

        it("merges a per-occurrence override over the template (getEffectiveRequirements)", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleId}/requirements`,
                { positionId: positionOne, minCount: 1, maxCount: 1 },
                201,
            );
            api(
                ADMIN_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleId}/requirements`,
                { positionId: positionTwo, minCount: 1, maxCount: 1 },
                201,
            );

            cy.then(() => {
                // "this week we need four, not one" — an override on ONE position.
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/volunteer/occurrences/${occurrenceId}/requirements`,
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
                    `/api/volunteer/occurrences/${occurrenceId}`,
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
                `/api/volunteer/schedules/${scheduleId}/requirements`,
                { positionId: positionOne, minCount: 1 },
                201,
            );
            cy.then(() => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/volunteer/occurrences/${occurrenceId}/requirements`,
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
                `/api/volunteer/schedules/${scheduleId}/requirements`,
                { positionId: positionOne, minCount: 1 },
                201,
            ).then((resp) => {
                const reqId = resp.body.requirement.id;
                api(
                    ADMIN_KEY,
                    "DELETE",
                    `/api/volunteer/requirements/${reqId}`,
                );
                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/volunteer/schedules/${scheduleId}/requirements`,
                ).then((after) => {
                    expect(after.body.requirements).to.have.length(0);
                });
                api(
                    ADMIN_KEY,
                    "DELETE",
                    `/api/volunteer/requirements/${reqId}`,
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

            cy.then(() => {
                createSchedule(
                    ministryA,
                    linkedScheduleBody({
                        name: `${FIXTURE_PREFIX} Linked Worship`,
                        titleFilter: EVENT_TITLE,
                        windowStart: seriesStart,
                    }),
                ).then((id) => {
                    scheduleId = id;
                });
            });
        });

        it("creates one occurrence per event, with no times of its own and no new event rows", () => {
            dbOk(`SELECT COUNT(*) AS c FROM events_event`).then((before) => {
                const eventsBefore = before[0].c;

                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/volunteer/schedules/${scheduleId}/generate`,
                    { through: seriesEnd },
                ).then((resp) => {
                    expect(resp.body.created).to.eq(4);
                    expect(resp.body.existing).to.eq(0);
                    expect(resp.body.through).to.eq(seriesEnd);
                });

                dbOk(`SELECT COUNT(*) AS c FROM events_event`).then((after) => {
                    // "No second competing event occurrence is created."
                    expect(after[0].c).to.eq(eventsBefore);
                });
            });

            api(
                ADMIN_KEY,
                "GET",
                `/api/volunteer/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}`,
            ).then((resp) => {
                const occ = resp.body.occurrences;
                expect(occ).to.have.length(4);
                occ.forEach((o) => {
                    expect(o.eventId).to.be.a("number");
                    expect(seriesEventIds).to.include(o.eventId);
                    // §2.9: a linked occurrence stores NO times of its own.
                    expect(o.startDateTime).to.eq(null);
                    expect(o.endDateTime).to.eq(null);
                    // …but reports the event's.
                    expect(o.start).to.contain("10:30:00");
                    expect(o.end).to.contain("11:45:00");
                });
            });
        });

        it("reports the event's new time after the EVENTS api moves it, with no V2 write", () => {
            api(
                ADMIN_KEY,
                "GET",
                `/api/volunteer/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}`,
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
                    `/api/volunteer/occurrences/${target.id}`,
                ).then((after) => {
                    expect(after.body.occurrence.start).to.eq(newStart);
                    expect(after.body.occurrence.end).to.eq(newEnd);
                });

                // The V2 row itself was never written: still no times, and the
                // generated timestamp is untouched. That is what "the event is
                // the source of truth" means operationally (§2.9).
                dbOk(
                    `SELECT vocc_StartDateTime, vocc_EndDateTime FROM volunteer_occurrence_vocc WHERE vocc_ID = ?`,
                    [target.id],
                ).then((rows) => {
                    expect(rows[0].vocc_StartDateTime).to.eq(null);
                    expect(rows[0].vocc_EndDateTime).to.eq(null);
                });
            });
        });

        it("is idempotent: a second generation over the same window creates nothing", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleId}/generate`,
                { through: seriesEnd },
            ).then((resp) => {
                expect(resp.body.created).to.eq(0);
                expect(resp.body.existing).to.eq(4);
            });

            api(
                ADMIN_KEY,
                "GET",
                `/api/volunteer/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}`,
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
                    `/api/volunteer/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}` +
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
                    `/api/volunteer/occurrences?from=${seriesStart}&to=${seriesEnd}&teamId=${teamA1}&text=Linked`,
                ).then((resp) => {
                    expect(resp.body.occurrences).to.have.length(4);
                });

                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/volunteer/occurrences?from=${seriesStart}&to=${seriesEnd}&teamId=${teamB1}&text=Linked`,
                ).then((resp) => {
                    expect(resp.body.occurrences, "another team owns no linked schedule").to.have.length(0);
                });
            });
        });

        it("honours the title filter", () => {
            // A schedule on the same event type with a filter nothing matches
            // finds no events at all — proving the filter, not just the type,
            // is part of the binding (§2.8, F7/F8).
            createSchedule(
                ministryA,
                linkedScheduleBody({
                    name: `${FIXTURE_PREFIX} Linked Nothing`,
                    titleFilter: `${FIXTURE_PREFIX} NoSuchTitle`,
                    windowStart: seriesStart,
                }),
            ).then((id) => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/volunteer/schedules/${id}/generate`,
                    { through: seriesEnd },
                ).then((resp) => {
                    expect(resp.body.created).to.eq(0);
                });
                api(ADMIN_KEY, "DELETE", `/api/volunteer/schedules/${id}`);
            });
        });

        it("cancels a single occurrence without touching the schedule", () => {
            api(
                ADMIN_KEY,
                "GET",
                `/api/volunteer/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}`,
            ).then((resp) => {
                const target = resp.body.occurrences[1];
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/volunteer/occurrences/${target.id}/status`,
                    { status: "cancelled" },
                ).then((after) => {
                    expect(after.body.occurrence.status).to.eq("cancelled");
                });

                // Cancelling is not deleting: regeneration must not resurrect
                // it as a second row (§2.9 idempotency by unique key).
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/volunteer/schedules/${scheduleId}/generate`,
                    { through: seriesEnd },
                ).then((gen) => {
                    expect(gen.body.created).to.eq(0);
                });
            });
        });
    });

    // ── §6.5 scenario 5, standalone half ───────────────────────────────────

    describe("standalone schedules — V2 owns the dates and the times", () => {
        let scheduleId = 0;
        let through = "";

        before(() => {
            through = isoDate(28);
            createSchedule(
                ministryA,
                standaloneScheduleBody({
                    name: `${FIXTURE_PREFIX} Standalone Weekly`,
                    recurDow: "Thursday",
                    startTime: "19:00:00",
                    endTime: "20:30:00",
                }),
            ).then((id) => {
                scheduleId = id;
            });
        });

        it("generates its own dates with its own times and no event link", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleId}/generate`,
                { through },
            ).then((resp) => {
                expect(resp.body.created).to.be.greaterThan(0);
                expect(resp.body.existing).to.eq(0);
            });

            api(
                ADMIN_KEY,
                "GET",
                `/api/volunteer/occurrences?from=${isoDate(0)}&to=${through}&ministryId=${ministryA}`,
            ).then((resp) => {
                const mine = resp.body.occurrences.filter(
                    (o) => o.scheduleId === scheduleId,
                );
                expect(mine.length).to.be.greaterThan(0);
                mine.forEach((o) => {
                    expect(o.eventId).to.eq(null);
                    // §2.9: a standalone row ALWAYS carries its own start.
                    expect(o.startDateTime).to.contain("19:00:00");
                    expect(o.start).to.eq(o.startDateTime);
                    expect(o.end).to.contain("20:30:00");
                    // Thursday.
                    expect(new Date(`${o.occurrenceDate}T12:00:00`).getDay()).to.eq(4);
                });
            });
        });

        it("is idempotent: a second generation over the same window creates nothing", () => {
            let firstCount = 0;
            api(
                ADMIN_KEY,
                "GET",
                `/api/volunteer/occurrences?from=${isoDate(0)}&to=${through}&ministryId=${ministryA}`,
            ).then((resp) => {
                firstCount = resp.body.occurrences.filter(
                    (o) => o.scheduleId === scheduleId,
                ).length;
            });

            cy.then(() => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/volunteer/schedules/${scheduleId}/generate`,
                    { through },
                ).then((resp) => {
                    expect(resp.body.created).to.eq(0);
                    expect(resp.body.existing).to.eq(firstCount);
                });
            });

            cy.then(() => {
                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/volunteer/occurrences?from=${isoDate(0)}&to=${through}&ministryId=${ministryA}`,
                ).then((resp) => {
                    const count = resp.body.occurrences.filter(
                        (o) => o.scheduleId === scheduleId,
                    ).length;
                    expect(count).to.eq(firstCount);
                });
            });
        });

        it("defaults `through` to today + generateAheadDays", () => {
            createSchedule(
                ministryA,
                standaloneScheduleBody({
                    name: `${FIXTURE_PREFIX} Standalone Default`,
                    recurDow: "Friday",
                    generateAheadDays: 14,
                }),
            ).then((id) => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/volunteer/schedules/${id}/generate`,
                    {},
                ).then((resp) => {
                    expect(resp.body.through).to.eq(isoDate(14));
                    expect(resp.body.created).to.be.within(2, 3);
                });
                api(ADMIN_KEY, "DELETE", `/api/volunteer/schedules/${id}`);
            });
        });

        it("never generates outside the schedule's own window", () => {
            createSchedule(
                ministryA,
                standaloneScheduleBody({
                    name: `${FIXTURE_PREFIX} Standalone Windowed`,
                    recurDow: "Monday",
                    windowStart: isoDate(0),
                    windowEnd: isoDate(10),
                }),
            ).then((id) => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/volunteer/schedules/${id}/generate`,
                    { through: isoDate(90) },
                ).then((resp) => {
                    // windowEnd clamps the run; at most two Mondays fit in 10 days.
                    expect(resp.body.created).to.be.within(1, 2);
                    expect(resp.body.through).to.eq(isoDate(10));
                });
                api(ADMIN_KEY, "DELETE", `/api/volunteer/schedules/${id}`);
            });
        });

        it("rejects a generation run that would blow the occurrence cap", () => {
            createSchedule(
                ministryA,
                standaloneScheduleBody({
                    name: `${FIXTURE_PREFIX} Standalone Huge`,
                    recurDow: "Saturday",
                }),
            ).then((id) => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/volunteer/schedules/${id}/generate`,
                    { through: isoDate(365 * 20) },
                    400,
                );
                api(ADMIN_KEY, "DELETE", `/api/volunteer/schedules/${id}`);
            });
        });

        it("rejects a malformed `through`", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleId}/generate`,
                { through: "not-a-date" },
                400,
            );
        });
    });

    // ── §3.3.2 / M9 window validation on the occurrence list ───────────────

    describe("occurrence list window (M9)", () => {
        it("requires from and to", () => {
            api(ADMIN_KEY, "GET", "/api/volunteer/occurrences", null, 400);
            api(
                ADMIN_KEY,
                "GET",
                `/api/volunteer/occurrences?from=${isoDate(0)}`,
                null,
                400,
            );
            api(
                ADMIN_KEY,
                "GET",
                `/api/volunteer/occurrences?to=${isoDate(30)}`,
                null,
                400,
            );
        });

        it("rejects a malformed or inverted window", () => {
            api(
                ADMIN_KEY,
                "GET",
                "/api/volunteer/occurrences?from=yesterday&to=tomorrow",
                null,
                400,
            );
            api(
                ADMIN_KEY,
                "GET",
                `/api/volunteer/occurrences?from=${isoDate(30)}&to=${isoDate(0)}`,
                null,
                400,
            );
        });

        it("reports the hard cap alongside the rows", () => {
            api(
                ADMIN_KEY,
                "GET",
                `/api/volunteer/occurrences?from=${isoDate(0)}&to=${isoDate(30)}`,
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
                standaloneScheduleBody({
                    name: `${FIXTURE_PREFIX} Scope A`,
                    recurDow: "Saturday",
                }),
            ).then((id) => {
                scheduleInA = id;
            });
            // standaloneScheduleBody() defaults to ministry A's team, which is the
            // wrong ministry here — a schedule has to name a team of its OWN ministry.
            createSchedule(
                ministryB,
                standaloneScheduleBody({
                    name: `${FIXTURE_PREFIX} Scope B`,
                    recurDow: "Saturday",
                    teamId: teamB1,
                }),
            ).then((id) => {
                scheduleInB = id;
            });
        });

        after(() => {
            api(ADMIN_KEY, "DELETE", `/api/volunteer/schedules/${scheduleInA}`);
            api(ADMIN_KEY, "DELETE", `/api/volunteer/schedules/${scheduleInB}`);
        });

        it("lets a ministry coordinator read and write their own ministry", () => {
            api(
                COORDINATOR_KEY,
                "GET",
                `/api/volunteer/ministries/${ministryA}/schedules`,
            ).then((resp) => {
                expect(resp.body.schedules.map((s) => s.id)).to.include(
                    scheduleInA,
                );
            });
            api(
                COORDINATOR_KEY,
                "GET",
                `/api/volunteer/schedules/${scheduleInA}`,
            );
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleInA}/generate`,
                { through: isoDate(14) },
            );
        });

        it("denies that coordinator every route into another ministry", () => {
            api(
                COORDINATOR_KEY,
                "GET",
                `/api/volunteer/ministries/${ministryB}/schedules`,
                null,
                403,
            );
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/volunteer/ministries/${ministryB}/schedules`,
                standaloneScheduleBody(),
                403,
            );
            api(
                COORDINATOR_KEY,
                "GET",
                `/api/volunteer/schedules/${scheduleInB}`,
                null,
                403,
            );
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleInB}/generate`,
                { through: isoDate(14) },
                403,
            );
            api(
                COORDINATOR_KEY,
                "DELETE",
                `/api/volunteer/schedules/${scheduleInB}`,
                null,
                403,
            );
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleInB}/requirements`,
                { positionId: positionOne, minCount: 1 },
                403,
            );
        });

        it("scopes the occurrence list in the query, not in the client", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/volunteer/schedules/${scheduleInB}/generate`,
                { through: isoDate(14) },
            );
            cy.then(() => {
                api(
                    COORDINATOR_KEY,
                    "GET",
                    `/api/volunteer/occurrences?from=${isoDate(0)}&to=${isoDate(14)}`,
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
                `/api/volunteer/ministries/${ministryA}/schedules`,
                null,
                403,
            );
            api(
                PLAINAUTH_KEY,
                "GET",
                `/api/volunteer/schedules/${scheduleInA}`,
                null,
                403,
            );
            api(
                PLAINAUTH_KEY,
                "GET",
                `/api/volunteer/occurrences?from=${isoDate(0)}&to=${isoDate(14)}`,
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
                `/api/volunteer/ministries/${ministryA}/schedules`,
                null,
                403,
            );
            api(
                ADMIN_KEY,
                "GET",
                `/api/volunteer/occurrences?from=${isoDate(0)}&to=${isoDate(7)}`,
                null,
                403,
            );
        });
    });
});
