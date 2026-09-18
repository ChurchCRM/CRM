/// <reference types="cypress" />

/**
 * Volunteer v2 — Event and Calendar integration (#9713, epic #9701).
 *
 * Normative sections of `.agents/skills/churchcrm/volunteer-v2-design.md`:
 * §2.16 (`events_event.event_ministry_id` and its thirteen touch points),
 * §2.9 (occurrence ↔ event, `ON DELETE SET NULL`), §3.5 (where V2 plugs into
 * the event surfaces) and §4.6 (ministry-linked events — the one per-row event
 * authorization rule V2 adds).
 *
 * What D9 promises, and where each promise is proven here:
 *
 *   "a ministry coordinator can create and own events for their ministry
 *    without the global AddEvent right"
 *       → `coordinator without AddEvent` block. Person 3 (tony.wade) carries
 *         `bAddEvent = FALSE` in the seed, so nothing has to be revoked: he is
 *         the persona as shipped. He may create/edit/delete an event carrying
 *         ministry A's id and nothing else.
 *
 *   "events with a null ministry id behave exactly as today"
 *       → `regression — events with no ministry` block. A global AddEvent user
 *         with **no** volunteer scope (person 95, judith.matthews — her
 *         `bAddEvent` is flipped on for the spec and restored afterwards)
 *         creates, edits and deletes a null-ministry event exactly as before,
 *         and is refused a ministry id because she manages none (§2.16 item 9).
 *
 *   "the occurrence survives on vocc_OccurrenceDate" (§2.9)
 *       → `event deletion` block asserts the raw row through `cy.dbQuery`:
 *         `vocc_event_id` is NULL, the occurrence row is still there and its
 *         assignments are untouched. `Event::preDelete()` must not have grown a
 *         V2 cascade.
 *
 *   "extendedProps carry the staffing flags for events the caller may see"
 *       → `calendar feed` block reads `/api/calendars/{id}/fullcalendar`.
 *
 * Fixtures. Ministries, teams and positions go in through `cy.dbQuery()` —
 * faster than the setup API and this spec is not testing that surface. Scopes
 * go in through `POST /api/ministries/scopes`. The linked event series is
 * created through `POST /api/events/repeat` because the seeded calendar holds
 * only three events, all in 2016/2017.
 *
 * Cleanup runs in `before` as well as `after` (cypress-testing.md): an `after`
 * hook does not run when the runner crashes mid-spec.
 *
 * Migration idempotence. `7.8.0-volunteer-v2-event-ministry.sql` is a plain
 * `ALTER TABLE … ADD COLUMN` (Appendix A step 2) and MySQL has no portable
 * `ADD COLUMN IF NOT EXISTS`, so re-running it on a database that already has
 * the column is an error by design — the upgrade runner is version-gated and
 * never replays a script. There is therefore deliberately no "run it twice"
 * assertion here; what IS asserted is that the column, its index and its
 * `ON DELETE SET NULL` constraint exist with the shape §2.16 specifies.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";
const ADDEVENT_KEY = "nofinance.api.key";

const PERSON_COORDINATOR = 3; // tony.wade — no bAddEvent in the seed
const PERSON_ADDEVENT = 95; // judith.matthews — given bAddEvent for this spec

const CHURCH_SERVICE_TYPE = 1; // seed.sql — "Church Service"
const PUBLIC_CALENDAR = 1;

const FIXTURE_PREFIX = "EVTMIN9713";
const EVENT_TITLE = `${FIXTURE_PREFIX} Linked Service`;

let ministryA = 0;
let ministryB = 0;
/**
 * The ministries' own calendars (#9869, Member Portal design §5.3). A coordinator
 * without Add Events may pin to their ministry's calendar and to nothing else, so the
 * coordinator block below pins here rather than to "Public Calendar".
 */
let calendarA = 0;
let calendarB = 0;
/** Ministry A's team — positions and schedules both have to name one. */
let teamA = 0;
let positionOne = 0;
let scheduleId = 0;
let linkedOccurrenceId = 0;
let linkedEventId = 0;
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

/** `YYYY-MM-DD`, `offsetDays` from today, in the browser's own calendar. */
function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * A complete, valid event payload. `newEvent` requires Title, Type, Start, End
 * and PinnedCalendars; everything else is optional.
 */
function eventBody(overrides = {}) {
    return {
        Title: `${FIXTURE_PREFIX} Ad-hoc`,
        Type: CHURCH_SERVICE_TYPE,
        Start: `${isoDate(30)}T09:00:00`,
        End: `${isoDate(30)}T10:30:00`,
        PinnedCalendars: [PUBLIC_CALENDAR],
        ...overrides,
    };
}

/**
 * The same payload, pinned to ministry A's OWN calendar.
 *
 * A coordinator without Add Events may pin an event to the calendar their ministry owns
 * and to no other (#9869, Member Portal design §5.3), so every coordinator write in this
 * spec uses this rather than `eventBody()`'s "Public Calendar" default. Reading
 * `calendarA` at call time, not at module load, is deliberate: the fixture assigns it in
 * `before`, and these helpers are evaluated inside the `it`s.
 */
function coordinatorEventBody(overrides = {}) {
    return eventBody({ PinnedCalendars: [calendarA], ...overrides });
}

/** Create an event as `key` and resolve with its id (read back by title). */
function createEventAs(key, body, expectedStatus = 200) {
    const title = body.Title;

    return api(key, "POST", "/api/events", body, expectedStatus).then(
        (resp) => {
            if (resp.status !== 200) {
                return 0;
            }
            return dbOk(
                `SELECT event_id FROM events_event WHERE event_title = ? ORDER BY event_id DESC LIMIT 1`,
                [title],
            ).then((rows) => {
                expect(rows.length, `event "${title}" was created`).to.eq(1);
                return rows[0].event_id;
            });
        },
    );
}

function cleanupFixtures() {
    // Children before parents; the FK cascades would cover most of it but the
    // explicit order survives a half-built fixture.
    dbOk(
        `DELETE vrsp FROM volunteer_response_vrsp vrsp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vrsp.vrsp_vasg_ID
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vasg FROM volunteer_assignment_vasg vasg
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
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
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [
        PERSON_COORDINATOR,
    ]);
    // Every event this spec makes is prefixed, so this also unpins them.
    dbOk(
        `DELETE ce FROM calendar_events ce
           JOIN events_event e ON e.event_id = ce.event_id
          WHERE e.event_title LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [
        `${FIXTURE_PREFIX}%`,
    ]);
    dbOk(
        `DELETE ce FROM calendar_events ce
           JOIN calendars c ON c.calendar_id = ce.calendar_id
          WHERE c.name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    // Before the ministries: the FK is ON DELETE SET NULL, so a calendar left behind
    // would survive as an unowned church calendar and leak into the next run.
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, [`${FIXTURE_PREFIX}%`]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [
        `${FIXTURE_PREFIX}%`,
    ]);
}

function createMinistry(suffix) {
    return dbOk(
        `INSERT INTO volunteer_ministry_vmin (vmin_Name, vmin_Description, vmin_Active, vmin_CreatedDate)
         VALUES (?, 'volunteer v2 event-ministry fixture', 1, NOW())`,
        [`${FIXTURE_PREFIX} ${suffix}`],
    ).then((rows) => rows.insertId);
}

/**
 * A team for the ministry.
 *
 * These fixtures insert their ministry with raw SQL, which bypasses
 * `VolunteerSetupService::createMinistry()` and therefore the team it would have
 * created — so the team is inserted here too. Positions and schedules are
 * `NOT NULL` on their team column, so one has to exist before either.
 */
/**
 * The ministry's own calendar. `VolunteerSetupService::createMinistry()` creates one
 * with every ministry; these fixtures insert their ministry with raw SQL and therefore
 * have to insert the calendar too, exactly as they already do for the team.
 */
function createMinistryCalendar(ministryId, suffix) {
    return dbOk(
        `INSERT INTO calendars (name, foregroundColor, backgroundColor, ministry_id)
         VALUES (?, 'FFFFFF', '2E7D32', ?)`,
        [`${FIXTURE_PREFIX} ${suffix}`, ministryId],
    ).then((rows) => rows.insertId);
}

function createTeam(ministryId, name) {
    return dbOk(
        `INSERT INTO volunteer_team_vtem (vtem_vmin_ID, vtem_Name, vtem_Description, vtem_Active)
         VALUES (?, ?, 'volunteer v2 event-ministry fixture', 1)`,
        [ministryId, `${FIXTURE_PREFIX} ${name}`],
    ).then((rows) => rows.insertId);
}

function createPosition(ministryId, teamId, name) {
    return dbOk(
        `INSERT INTO volunteer_position_vpos (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Description, vpos_Active, vpos_Order)
         VALUES (?, ?, ?, 'volunteer v2 event-ministry fixture', 1, 1)`,
        [ministryId, teamId, `${FIXTURE_PREFIX} ${name}`],
    ).then((rows) => rows.insertId);
}

/** Days from today to the next occurrence of `dow` (0 = Sunday). Never 0. */
function daysToNext(dow) {
    const today = new Date().getDay();
    return ((dow - today + 7) % 7) || 7;
}

// ── suite ──────────────────────────────────────────────────────────────────

describe("Volunteer v2 — event ministry ownership and calendar integration (#9713)", () => {
    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then(
            (resp) => {
                originalVersion = resp.body.value ?? resp.body.data ?? "v1";
            },
        );
        setVersion("v2");

        cleanupFixtures();

        // Person 95 is the "global AddEvent, no volunteer scope" persona. The
        // seed ships her ucfg row with permission FALSE; flip it on here and
        // put it back in `after` so the regression block has a caller who
        // exercises exactly today's behaviour.
        dbOk(
            `UPDATE userconfig_ucfg SET ucfg_permission = 'TRUE'
              WHERE ucfg_per_ID = ? AND ucfg_name = 'bAddEvent'`,
            [PERSON_ADDEVENT],
        );

        createMinistry("Ministry A").then((id) => {
            ministryA = id;
            createMinistryCalendar(ministryA, "Ministry A Calendar").then((c) => {
                calendarA = c;
            });
            createTeam(ministryA, "Ministry A Team").then((teamId) => {
                teamA = teamId;
                createPosition(ministryA, teamA, "Espresso").then((p) => {
                positionOne = p;
                // One qualified person so the deletion test can hang a real
                // assignment off the linked occurrence (I2 is enforced by
                // VolunteerAssignmentService and has no bypass flag).
                dbOk(
                    `INSERT INTO volunteer_qualification_vqal
                        (vqal_per_ID, vqal_vpos_ID, vqal_Active, vqal_GrantedDate)
                     VALUES (?, ?, 1, NOW())`,
                    [PERSON_COORDINATOR, positionOne],
                );
                });
            });
        });
        createMinistry("Ministry B").then((id) => {
            ministryB = id;
            createMinistryCalendar(ministryB, "Ministry B Calendar").then((c) => {
                calendarB = c;
            });
        });

        cy.then(() => {
            // Person 3 coordinates ministry A only.
            api(ADMIN_KEY, "POST", "/api/ministries/scopes", {
                personId: PERSON_COORDINATOR,
                scopeType: "ministry",
                scopeId: ministryA,
            }, [200, 201]);

            // A short weekly series to link a schedule to.
            const first = daysToNext(0);
            api(
                ADMIN_KEY,
                "POST",
                "/api/events/repeat",
                {
                    Title: EVENT_TITLE,
                    Type: CHURCH_SERVICE_TYPE,
                    StartTime: "10:30",
                    EndTime: "11:45",
                    RecurType: "weekly",
                    RecurDOW: "Sunday",
                    RangeStart: isoDate(first),
                    RangeEnd: isoDate(first + 14),
                    PinnedCalendars: [PUBLIC_CALENDAR],
                },
                200,
            );

            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/ministries/${ministryA}/schedules`,
                {
                    name: `${FIXTURE_PREFIX} Linked`,
                    // A schedule always names a team (D18).
                    teamId: teamA,
                    linkMode: "event_type",
                    eventTypeId: CHURCH_SERVICE_TYPE,
                    titleFilter: EVENT_TITLE,
                    windowStart: isoDate(0),
                },
                201,
            ).then((resp) => {
                scheduleId = resp.body.schedule.id;

                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${scheduleId}/requirements`,
                    { positionId: positionOne, minCount: 2 },
                    [200, 201],
                );

                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${scheduleId}/generate`,
                    { through: isoDate(first + 14) },
                    200,
                );

                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/ministries/occurrences?scheduleId=${scheduleId}&from=${isoDate(0)}&to=${isoDate(first + 21)}`,
                    null,
                    200,
                ).then((listResp) => {
                    const linked = listResp.body.occurrences.filter(
                        (o) => o.eventId,
                    );
                    expect(
                        linked.length,
                        "the linked schedule generated occurrences",
                    ).to.be.greaterThan(0);
                    linkedOccurrenceId = linked[0].id;
                    linkedEventId = linked[0].eventId;
                });
            });
        });
    });

    after(() => {
        cleanupFixtures();
        dbOk(
            `UPDATE userconfig_ucfg SET ucfg_permission = 'FALSE'
              WHERE ucfg_per_ID = ? AND ucfg_name = 'bAddEvent'`,
            [PERSON_ADDEVENT],
        );
        setVersion(originalVersion);
    });

    // ── §2.16 the column itself ─────────────────────────────────────────────

    describe("the column, its index and its FK (§2.16, Appendix A)", () => {
        it("events_event.event_ministry_id is a nullable INTEGER", () => {
            dbOk(
                `SELECT DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'events_event'
                    AND COLUMN_NAME = 'event_ministry_id'`,
            ).then((rows) => {
                expect(rows.length, "the column exists").to.eq(1);
                expect(rows[0].DATA_TYPE).to.eq("int");
                expect(rows[0].IS_NULLABLE).to.eq("YES");
                expect(rows[0].COLUMN_DEFAULT).to.be.oneOf([null, "NULL"]);
            });
        });

        it("carries an index and an ON DELETE SET NULL FK to volunteer_ministry_vmin", () => {
            dbOk(
                `SELECT k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
                   FROM information_schema.KEY_COLUMN_USAGE k
                   JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                     ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                    AND r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                  WHERE k.TABLE_SCHEMA = DATABASE()
                    AND k.TABLE_NAME = 'events_event'
                    AND k.COLUMN_NAME = 'event_ministry_id'
                    AND k.REFERENCED_TABLE_NAME IS NOT NULL`,
            ).then((rows) => {
                expect(rows.length, "the FK exists").to.eq(1);
                expect(rows[0].REFERENCED_TABLE_NAME).to.eq(
                    "volunteer_ministry_vmin",
                );
                expect(rows[0].REFERENCED_COLUMN_NAME).to.eq("vmin_ID");
                expect(rows[0].DELETE_RULE).to.eq("SET NULL");
            });

            dbOk(
                `SHOW INDEX FROM events_event WHERE Column_name = 'event_ministry_id'`,
            ).then((rows) => {
                expect(rows.length, "the column is indexed").to.be.greaterThan(
                    0,
                );
            });
        });

        it("deleting a ministry nulls the link instead of deleting the event", () => {
            let throwawayMinistry = 0;
            let throwawayEvent = 0;

            createMinistry("Throwaway").then((id) => {
                throwawayMinistry = id;

                createEventAs(
                    ADMIN_KEY,
                    eventBody({
                        Title: `${FIXTURE_PREFIX} Throwaway Event`,
                        MinistryId: id,
                    }),
                ).then((eventId) => {
                    throwawayEvent = eventId;

                    dbOk(
                        `DELETE FROM volunteer_ministry_vmin WHERE vmin_ID = ?`,
                        [throwawayMinistry],
                    );

                    dbOk(
                        `SELECT event_ministry_id FROM events_event WHERE event_id = ?`,
                        [throwawayEvent],
                    ).then((rows) => {
                        expect(rows.length, "the event survived").to.eq(1);
                        expect(rows[0].event_ministry_id).to.eq(null);
                    });
                });
            });
        });
    });

    // ── §2.16 items 7–9: the API read and write ─────────────────────────────

    describe("the event API read and write (§2.16 items 7–9)", () => {
        it("an admin creates an event with a ministry id and reads it back", () => {
            createEventAs(
                ADMIN_KEY,
                eventBody({
                    Title: `${FIXTURE_PREFIX} Admin With Ministry`,
                    MinistryId: ministryA,
                }),
            ).then((eventId) => {
                api(ADMIN_KEY, "GET", `/api/events/${eventId}`).then((resp) => {
                    expect(resp.body.MinistryId).to.eq(ministryA);
                    expect(resp.body.MinistryName).to.eq(
                        `${FIXTURE_PREFIX} Ministry A`,
                    );
                });
            });
        });

        it("an unknown ministry id is rejected", () => {
            api(
                ADMIN_KEY,
                "POST",
                "/api/events",
                eventBody({
                    Title: `${FIXTURE_PREFIX} Bad Ministry`,
                    MinistryId: 999999,
                }),
                400,
            );

            dbOk(`SELECT event_id FROM events_event WHERE event_title = ?`, [
                `${FIXTURE_PREFIX} Bad Ministry`,
            ]).then((rows) => {
                expect(rows.length, "nothing was created").to.eq(0);
            });
        });

        it("getEvent reports a null ministry as null, not 0", () => {
            createEventAs(
                ADMIN_KEY,
                eventBody({ Title: `${FIXTURE_PREFIX} No Ministry Read` }),
            ).then((eventId) => {
                api(ADMIN_KEY, "GET", `/api/events/${eventId}`).then((resp) => {
                    expect(resp.body.MinistryId).to.eq(null);
                    expect(resp.body.MinistryName).to.eq(null);
                });
            });
        });

        it("an admin clears a ministry back to null through updateEvent", () => {
            createEventAs(
                ADMIN_KEY,
                eventBody({
                    Title: `${FIXTURE_PREFIX} Clearable`,
                    MinistryId: ministryA,
                }),
            ).then((eventId) => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/events/${eventId}`,
                    eventBody({
                        Title: `${FIXTURE_PREFIX} Clearable`,
                        MinistryId: null,
                        PinnedCalendars: [PUBLIC_CALENDAR],
                    }),
                    200,
                );

                api(ADMIN_KEY, "GET", `/api/events/${eventId}`).then((resp) => {
                    expect(resp.body.MinistryId).to.eq(null);
                });
            });
        });
    });

    // ── §4.6 per-row event authorization ────────────────────────────────────

    describe("a coordinator without AddEvent (§4.6, D9)", () => {
        it("creates an event carrying THEIR ministry id", () => {
            createEventAs(
                COORDINATOR_KEY,
                coordinatorEventBody({
                    Title: `${FIXTURE_PREFIX} Coordinator Event`,
                    MinistryId: ministryA,
                }),
            ).then((eventId) => {
                expect(eventId).to.be.greaterThan(0);
                api(ADMIN_KEY, "GET", `/api/events/${eventId}`).then((resp) => {
                    expect(resp.body.MinistryId).to.eq(ministryA);
                });
            });
        });

        it("is refused an event carrying someone else's ministry id", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events",
                coordinatorEventBody({
                    Title: `${FIXTURE_PREFIX} Coordinator Foreign`,
                    MinistryId: ministryB,
                }),
                403,
            );

            dbOk(`SELECT event_id FROM events_event WHERE event_title = ?`, [
                `${FIXTURE_PREFIX} Coordinator Foreign`,
            ]).then((rows) => {
                expect(rows.length, "nothing was created").to.eq(0);
            });
        });

        it("is refused an event with no ministry id at all", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events",
                coordinatorEventBody({ Title: `${FIXTURE_PREFIX} Coordinator Unowned` }),
                403,
            );

            dbOk(`SELECT event_id FROM events_event WHERE event_title = ?`, [
                `${FIXTURE_PREFIX} Coordinator Unowned`,
            ]).then((rows) => {
                expect(rows.length, "nothing was created").to.eq(0);
            });
        });

        it("edits, deactivates, re-times and deletes their own ministry's event", () => {
            createEventAs(
                COORDINATOR_KEY,
                coordinatorEventBody({
                    Title: `${FIXTURE_PREFIX} Coordinator Editable`,
                    MinistryId: ministryA,
                }),
            ).then((eventId) => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}`,
                    coordinatorEventBody({
                        Title: `${FIXTURE_PREFIX} Coordinator Editable`,
                        Desc: "renamed by the coordinator",
                        MinistryId: ministryA,
                    }),
                    200,
                );

                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}/time`,
                    {
                        startTime: `${isoDate(31)} 09:00:00`,
                        endTime: `${isoDate(31)} 10:00:00`,
                    },
                    200,
                );

                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}/status`,
                    { active: false },
                    200,
                );

                api(
                    COORDINATOR_KEY,
                    "DELETE",
                    `/api/events/${eventId}`,
                    null,
                    200,
                );

                dbOk(
                    `SELECT event_id FROM events_event WHERE event_id = ?`,
                    [eventId],
                ).then((rows) => {
                    expect(rows.length, "the event is gone").to.eq(0);
                });
            });
        });

        it("is refused every write on an event outside their scope", () => {
            createEventAs(
                ADMIN_KEY,
                eventBody({
                    Title: `${FIXTURE_PREFIX} Foreign Ministry Event`,
                    MinistryId: ministryB,
                    PinnedCalendars: [calendarB],
                }),
            ).then((eventId) => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}`,
                    coordinatorEventBody({
                        Title: `${FIXTURE_PREFIX} Foreign Ministry Event`,
                        MinistryId: ministryB,
                    }),
                    403,
                );
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}/status`,
                    { active: false },
                    403,
                );
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}/time`,
                    {
                        startTime: `${isoDate(31)} 09:00:00`,
                        endTime: `${isoDate(31)} 10:00:00`,
                    },
                    403,
                );
                api(
                    COORDINATOR_KEY,
                    "DELETE",
                    `/api/events/${eventId}`,
                    null,
                    403,
                );
            });
        });

        it("is refused an event that carries no ministry at all", () => {
            createEventAs(
                ADMIN_KEY,
                coordinatorEventBody({ Title: `${FIXTURE_PREFIX} Unowned Event` }),
            ).then((eventId) => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}`,
                    coordinatorEventBody({ Title: `${FIXTURE_PREFIX} Unowned Event` }),
                    403,
                );
                api(
                    COORDINATOR_KEY,
                    "DELETE",
                    `/api/events/${eventId}`,
                    null,
                    403,
                );
            });
        });

        it("may not hand their own event to a ministry they do not manage", () => {
            createEventAs(
                COORDINATOR_KEY,
                coordinatorEventBody({
                    Title: `${FIXTURE_PREFIX} Coordinator Handover`,
                    MinistryId: ministryA,
                }),
            ).then((eventId) => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}`,
                    coordinatorEventBody({
                        Title: `${FIXTURE_PREFIX} Coordinator Handover`,
                        MinistryId: ministryB,
                    }),
                    403,
                );

                dbOk(
                    `SELECT event_ministry_id FROM events_event WHERE event_id = ?`,
                    [eventId],
                ).then((rows) => {
                    expect(rows[0].event_ministry_id).to.eq(ministryA);
                });
            });
        });

        it("may not orphan their own event by clearing the ministry", () => {
            // Clearing would put the event beyond their reach for good, and
            // §4.6 requires authority over the value being *set* as well as the
            // one being replaced — a coordinator has none over "no ministry".
            createEventAs(
                COORDINATOR_KEY,
                coordinatorEventBody({
                    Title: `${FIXTURE_PREFIX} Coordinator Orphan`,
                    MinistryId: ministryA,
                }),
            ).then((eventId) => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}`,
                    coordinatorEventBody({
                        Title: `${FIXTURE_PREFIX} Coordinator Orphan`,
                        MinistryId: null,
                    }),
                    403,
                );

                dbOk(
                    `SELECT event_ministry_id FROM events_event WHERE event_id = ?`,
                    [eventId],
                ).then((rows) => {
                    expect(rows[0].event_ministry_id).to.eq(ministryA);
                });
            });
        });

        // #9869: the pin is a second authorization question, asked per calendar.
        it("is refused a pin to a church calendar they do not own", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events",
                coordinatorEventBody({
                    Title: `${FIXTURE_PREFIX} Coordinator Church Pin`,
                    MinistryId: ministryA,
                    PinnedCalendars: [PUBLIC_CALENDAR],
                }),
                403,
            );

            dbOk(`SELECT event_id FROM events_event WHERE event_title = ?`, [
                `${FIXTURE_PREFIX} Coordinator Church Pin`,
            ]).then((rows) => {
                expect(rows.length, "nothing was created").to.eq(0);
            });
        });

        it("is refused a pin to ANOTHER ministry's calendar", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events",
                coordinatorEventBody({
                    Title: `${FIXTURE_PREFIX} Coordinator Foreign Pin`,
                    MinistryId: ministryA,
                    PinnedCalendars: [calendarB],
                }),
                403,
            );

            dbOk(`SELECT event_id FROM events_event WHERE event_title = ?`, [
                `${FIXTURE_PREFIX} Coordinator Foreign Pin`,
            ]).then((rows) => {
                expect(rows.length, "nothing was created").to.eq(0);
            });
        });

        it("still cannot reach the event routes V2 does not widen", () => {
            // §4.6 widens exactly five handlers. Check-in, quick-create and the
            // repeat generator stay behind the untouched global AddEvent gate.
            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events/quick-create",
                { eventTypeId: CHURCH_SERVICE_TYPE, date: isoDate(3) },
                403,
            );
            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events/repeat",
                {
                    Title: `${FIXTURE_PREFIX} Should Not Exist`,
                    Type: CHURCH_SERVICE_TYPE,
                    StartTime: "10:00",
                    EndTime: "11:00",
                    RecurType: "weekly",
                    RecurDOW: "Sunday",
                    RangeStart: isoDate(1),
                    RangeEnd: isoDate(8),
                },
                403,
            );
        });

        it("loses the widened gate when the rollout flag goes back to v1", () => {
            setVersion("v1");
            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events",
                eventBody({
                    Title: `${FIXTURE_PREFIX} Rolled Back`,
                    MinistryId: ministryA,
                }),
                403,
            );
            setVersion("v2");
        });
    });

    // ── regression: events with no ministry behave exactly as today ─────────

    describe("regression — a global AddEvent user with no volunteer scope", () => {
        it("creates, edits and deletes a null-ministry event exactly as before", () => {
            createEventAs(
                ADDEVENT_KEY,
                eventBody({ Title: `${FIXTURE_PREFIX} Plain AddEvent` }),
            ).then((eventId) => {
                expect(eventId).to.be.greaterThan(0);

                api(ADDEVENT_KEY, "GET", `/api/events/${eventId}`).then(
                    (resp) => {
                        expect(resp.body.MinistryId).to.eq(null);
                    },
                );

                api(
                    ADDEVENT_KEY,
                    "POST",
                    `/api/events/${eventId}`,
                    eventBody({
                        Title: `${FIXTURE_PREFIX} Plain AddEvent`,
                        Desc: "edited",
                    }),
                    200,
                );

                api(
                    ADDEVENT_KEY,
                    "POST",
                    `/api/events/${eventId}/status`,
                    { active: false },
                    200,
                );

                api(
                    ADDEVENT_KEY,
                    "DELETE",
                    `/api/events/${eventId}`,
                    null,
                    200,
                );
            });
        });

        it("is refused a ministry id she manages none of (§2.16 item 9)", () => {
            api(
                ADDEVENT_KEY,
                "POST",
                "/api/events",
                eventBody({
                    Title: `${FIXTURE_PREFIX} AddEvent Ministry`,
                    MinistryId: ministryA,
                }),
                403,
            );

            dbOk(`SELECT event_id FROM events_event WHERE event_title = ?`, [
                `${FIXTURE_PREFIX} AddEvent Ministry`,
            ]).then((rows) => {
                expect(rows.length, "nothing was created").to.eq(0);
            });
        });

        it("may still edit an event that carries a ministry — the global right is unchanged", () => {
            createEventAs(
                ADMIN_KEY,
                eventBody({
                    Title: `${FIXTURE_PREFIX} AddEvent Edits Owned`,
                    MinistryId: ministryA,
                }),
            ).then((eventId) => {
                api(
                    ADDEVENT_KEY,
                    "POST",
                    `/api/events/${eventId}`,
                    eventBody({
                        Title: `${FIXTURE_PREFIX} AddEvent Edits Owned`,
                        Desc: "edited by a global AddEvent user",
                        MinistryId: ministryA,
                    }),
                    200,
                );
            });
        });

        it("a user with neither right is still refused, as today", () => {
            api(
                "plainauth.api.key",
                "POST",
                "/api/events",
                eventBody({ Title: `${FIXTURE_PREFIX} Plain Auth` }),
                403,
            );
        });
    });

    // ── §3.5 the calendar feed ──────────────────────────────────────────────

    describe("calendar feed extendedProps (§3.5, E13)", () => {
        it("carries volunteerGapCount and volunteerStaffed for a linked event", () => {
            const from = isoDate(-1);
            const to = isoDate(30);

            api(
                ADMIN_KEY,
                "GET",
                `/api/calendars/${PUBLIC_CALENDAR}/fullcalendar?start=${from}&end=${to}`,
            ).then((resp) => {
                const row = resp.body.find(
                    (e) => Number(e.id) === Number(linkedEventId),
                );
                expect(row, "the linked event is in the feed").to.not.be
                    .undefined;
                // minCount 2, nobody assigned → two open gaps, not staffed.
                expect(row.extendedProps.volunteerGapCount).to.eq(2);
                expect(row.extendedProps.volunteerStaffed).to.eq(false);
            });
        });

        it("leaves an unlinked event's extendedProps alone", () => {
            const from = isoDate(-1);
            const to = isoDate(40);

            createEventAs(
                ADMIN_KEY,
                eventBody({ Title: `${FIXTURE_PREFIX} Feed Unlinked` }),
            ).then((eventId) => {
                api(
                    ADMIN_KEY,
                    "GET",
                    `/api/calendars/${PUBLIC_CALENDAR}/fullcalendar?start=${from}&end=${to}`,
                ).then((resp) => {
                    const row = resp.body.find(
                        (e) => Number(e.id) === Number(eventId),
                    );
                    expect(row, "the event is in the feed").to.not.be.undefined;
                    const props = row.extendedProps || {};
                    expect(props).to.not.have.property("volunteerGapCount");
                    expect(props).to.not.have.property("volunteerStaffed");
                });
            });
        });

        it("says nothing to a caller with no volunteer scope", () => {
            const from = isoDate(-1);
            const to = isoDate(30);

            api(
                "plainauth.api.key",
                "GET",
                `/api/calendars/${PUBLIC_CALENDAR}/fullcalendar?start=${from}&end=${to}`,
            ).then((resp) => {
                const row = resp.body.find(
                    (e) => Number(e.id) === Number(linkedEventId),
                );
                expect(row, "the linked event is still in the feed").to.not.be
                    .undefined;
                const props = row.extendedProps || {};
                expect(props).to.not.have.property("volunteerGapCount");
            });
        });
    });

    // ── §2.9 event deletion ─────────────────────────────────────────────────

    describe("event deletion nulls the occurrence link (§2.9, E12)", () => {
        it("leaves the occurrence and its assignments intact", () => {
            // Put a real assignment on the linked occurrence so "history
            // survives" is asserted against a row, not an empty set.
            let assignmentId = 0;

            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/occurrences/${linkedOccurrenceId}/assignments`,
                {
                    personId: PERSON_COORDINATOR,
                    positionId: positionOne,
                    allowOutsidePool: true,
                },
                [201, 409],
            ).then((resp) => {
                if (resp.status === 201) {
                    assignmentId = resp.body.assignment.id;
                }
            });

            cy.then(() => {
                // The occurrence's own assignment rows, whatever route put them
                // there, must survive the event delete.
                dbOk(
                    `SELECT COUNT(*) AS c FROM volunteer_assignment_vasg WHERE vasg_vocc_ID = ?`,
                    [linkedOccurrenceId],
                ).then((before) => {
                    const assignmentsBefore = Number(before[0].c);

                    api(
                        ADMIN_KEY,
                        "DELETE",
                        `/api/events/${linkedEventId}`,
                        null,
                        200,
                    );

                    dbOk(
                        `SELECT vocc_ID, vocc_event_id, vocc_OccurrenceDate
                           FROM volunteer_occurrence_vocc WHERE vocc_ID = ?`,
                        [linkedOccurrenceId],
                    ).then((rows) => {
                        expect(
                            rows.length,
                            "the occurrence survived the event delete",
                        ).to.eq(1);
                        expect(rows[0].vocc_event_id).to.eq(null);
                        expect(
                            rows[0].vocc_OccurrenceDate,
                            "it survives on its occurrence date",
                        ).to.not.eq(null);
                    });

                    dbOk(
                        `SELECT COUNT(*) AS c FROM volunteer_assignment_vasg WHERE vasg_vocc_ID = ?`,
                        [linkedOccurrenceId],
                    ).then((after) => {
                        expect(Number(after[0].c)).to.eq(assignmentsBefore);
                    });

                    if (assignmentId > 0) {
                        api(
                            ADMIN_KEY,
                            "GET",
                            `/api/ministries/assignments/${assignmentId}`,
                            null,
                            200,
                        );
                    }
                });
            });
        });
    });
});
