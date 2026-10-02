/// <reference types="cypress" />

/**
 * Volunteer v2 D24 — a ministry's events created through core from the ministry page, pinned by the
 * D25 rule; and the Calendar tab's list with staffing and headcount (D26). Design §0.8, §2.16,
 * §3.3.2, §4.6. Creating events never staffs them (D33): the staffing the list shows is made here
 * with a schedule and with Staff this event, and "Staff them" is
 * `private.volunteer.staff-after-create.spec.js`.
 *
 * Personas (seed.sql): `admin.api.key` (administrator, Add Events); `user.api.key` = person 3,
 * tony.wade, no Add Events, made coordinator of ministry A here; `selfedit.api.key` = person 99,
 * EditSelf-only. Ministries come from the real create API, so each has its own calendar.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";
const SELFEDIT_KEY = "selfedit.api.key";

const PERSON_COORDINATOR = 3;
const PERSON_SELFEDIT = 99;
const POOL_MEMBER = 8;
const CHURCH_SERVICE_TYPE = 1;
const PUBLIC_CALENDAR = 1;
const SUNDAY_SCHOOL_GROUP_TYPE = 4;
const MAX_SERIES = 366;

const PREFIX = "VMEVT24";
const URL = "/api/ministries";
const WEEKDAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

let originalVersion = "v1";
const ministry = {};
const ownCalendar = {};
const firstTeam = {};
let grantedCalendar = 0;
let adultClass = 0;
let faithCity = 0;
let position = 0;

// ── helpers ────────────────────────────────────────────────────────────────

function dbOk(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(`Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`);
        }
        return result.rows;
    });
}

function api(key, method, url, body, expectedStatus = 200) {
    return cy.makePrivateAPICall(Cypress.env(key), method, url, body, expectedStatus);
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

function localDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    return d;
}

function ymd(d) {
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function isoDate(offsetDays) {
    return ymd(localDate(offsetDays));
}

function eventsUrl(ministryKey) {
    return `${URL}/ministries/${ministry[ministryKey]}/events`;
}

function createEvents(body, key = ADMIN_KEY, expectedStatus = 201, ministryKey = "A") {
    return api(key, "POST", eventsUrl(ministryKey), body, expectedStatus);
}

function oneEvent(title, overrides = {}) {
    return {
        title: `${PREFIX} ${title}`,
        eventTypeId: CHURCH_SERVICE_TYPE,
        date: isoDate(5),
        startTime: "09:00",
        endTime: "12:00",
        ...overrides,
    };
}

function weekly(title, dow, from, to, overrides = {}) {
    return {
        title: `${PREFIX} ${title}`,
        eventTypeId: CHURCH_SERVICE_TYPE,
        recurrence: { type: "weekly", dow },
        rangeStart: isoDate(from),
        rangeEnd: isoDate(to),
        startTime: "09:30",
        endTime: "10:30",
        ...overrides,
    };
}

function rowsTitled(title) {
    return dbOk(
        `SELECT event_id, event_start, event_end, event_ministry_id, event_desc, event_type
           FROM events_event WHERE event_title = ? ORDER BY event_start`,
        [`${PREFIX} ${title}`],
    );
}

function pinsOf(eventId) {
    return dbOk(`SELECT calendar_id FROM calendar_events WHERE event_id = ? ORDER BY calendar_id`, [eventId]).then(
        (rows) => rows.map((r) => Number(r.calendar_id)),
    );
}

function audienceOf(eventId) {
    return dbOk(`SELECT group_id FROM event_audience WHERE event_id = ?`, [eventId]).then((rows) =>
        rows.map((r) => Number(r.group_id)),
    );
}

function grantScope(personId, scopeType, scopeId) {
    return api(ADMIN_KEY, "POST", `${URL}/scopes`, { personId, scopeType, scopeId }, [200, 201]);
}

function makeGroup(name, type) {
    return dbOk(
        `INSERT INTO group_grp (grp_Type, grp_RoleListID, grp_DefaultRole, grp_Name, grp_Description, grp_hasSpecialProps, grp_active, grp_include_email_export)
         VALUES (?, 0, 0, ?, '', 0, 1, 1)`,
        [type, `${PREFIX} ${name}`],
    ).then((rows) => rows.insertId);
}

/** Every date in [from, to] on which `dow` falls — what the repeat engine must produce. */
function weekdaysBetween(dow, from, to) {
    const dates = [];
    for (let offset = from; offset <= to; offset++) {
        const d = localDate(offset);
        if (WEEKDAYS[d.getDay()] === dow) {
            dates.push(ymd(d));
        }
    }
    return dates;
}

function cleanupFixtures() {
    const like = [`${PREFIX}%`];
    dbOk(
        `DELETE vasg FROM volunteer_assignment_vasg vasg
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
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
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID IN (?, ?)`, [PERSON_COORDINATOR, PERSON_SELFEDIT]);
    for (const table of ["calendar_events", "event_audience", "event_attend"]) {
        dbOk(`DELETE t FROM ${table} t JOIN events_event e ON e.event_id = t.event_id WHERE e.event_title LIKE ?`, like);
    }
    dbOk(
        `DELETE c FROM eventcounts_evtcnt c JOIN events_event e ON e.event_id = c.evtcnt_eventid WHERE e.event_title LIKE ?`,
        like,
    );
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, like);
    dbOk(
        `DELETE p2g2r FROM person2group2role_p2g2r p2g2r
           JOIN group_grp g ON g.grp_ID = p2g2r.p2g2r_grp_ID
          WHERE g.grp_Name LIKE ?`,
        like,
    );
    dbOk(`DELETE FROM group_grp WHERE grp_Name LIKE ?`, like);
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, like);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, like);
}

// ── fixture ────────────────────────────────────────────────────────────────

describe("Volunteer v2 D24 — a ministry's events created through core from the ministry page, pinned by the D25 rule; and the Calendar tab's list with staffing and headcount (D26)", () => {
    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
            originalVersion = resp.body.value ?? resp.body.data ?? "v1";
        });
        setVersion("v2");
        cleanupFixtures();

        for (const key of ["A", "B", "C"]) {
            api(ADMIN_KEY, "POST", `${URL}/ministries`, { name: `${PREFIX} Ministry ${key}`, description: "D24 fixture", sundaySchool: true }, 201).then(
                (resp) => {
                    ministry[key] = resp.body.ministry.id;
                    ownCalendar[key] = resp.body.calendarId;
                    api(ADMIN_KEY, "GET", `${URL}/ministries/${ministry[key]}`).then((detail) => {
                        firstTeam[key] = detail.body.teams[0].id;
                    });
                },
            );
        }

        api(ADMIN_KEY, "POST", "/api/calendars", {
            Name: `${PREFIX} Bible Classes`,
            ForegroundColor: "#FFFFFF",
            BackgroundColor: "#1565C0",
        }).then((resp) => {
            grantedCalendar = resp.body.Id;
        });

        makeGroup("Adult Class", SUNDAY_SCHOOL_GROUP_TYPE).then((id) => {
            adultClass = id;
        });
        makeGroup("Faith City", SUNDAY_SCHOOL_GROUP_TYPE).then((id) => {
            faithCity = id;
        });

        cy.then(() => {
            api(ADMIN_KEY, "PUT", `/api/calendars/${grantedCalendar}/ministries`, { ministryIds: [ministry.A] });
            api(ADMIN_KEY, "POST", `${URL}/ministries/${ministry.A}/positions`, { name: `${PREFIX} Crew Lead`, teamId: firstTeam.A }, 201).then(
                (resp) => {
                    position = resp.body.position.id;
                    api(ADMIN_KEY, "POST", `${URL}/positions/${position}/qualifications`, { personId: POOL_MEMBER }, [200, 201]);
                },
            );
            grantScope(PERSON_COORDINATOR, "ministry", ministry.A);
            grantScope(PERSON_COORDINATOR, "team", firstTeam.B);
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion(originalVersion);
    });

    // ── one event ──────────────────────────────────────────────────────────────

    describe("Volunteer v2 D24 — one ministry event through core", () => {
        it("creates it as a coordinator without Add Events, owned by the ministry, on its own calendar, with the class linked", () => {
            createEvents(
                oneEvent("Workday", { description: "Bring gloves", linkedGroupId: adultClass }),
                COORDINATOR_KEY,
            ).then((resp) => {
                expect(resp.body.events).to.have.length(1);
                expect(resp.body.events[0].start).to.eq(`${isoDate(5)} 09:00:00`);
                expect(resp.body.events[0].end).to.eq(`${isoDate(5)} 12:00:00`);
                expect(resp.body, "creating events never staffs them (D33)").to.have.all.keys("events");

                const eventId = resp.body.events[0].id;
                rowsTitled("Workday").then((rows) => {
                    expect(rows).to.have.length(1);
                    expect(Number(rows[0].event_ministry_id)).to.eq(ministry.A);
                    expect(rows[0].event_desc).to.eq("Bring gloves");
                    expect(Number(rows[0].event_type)).to.eq(CHURCH_SERVICE_TYPE);
                });
                pinsOf(eventId).then((pins) => expect(pins, "the ministry's own calendar by default").to.deep.eq([ownCalendar.A]));
                audienceOf(eventId).then((groups) => expect(groups, "the Linked Group").to.deep.eq([adultClass]));
            });
        });

        it("pins to a church calendar opened to the ministry", () => {
            createEvents(oneEvent("Granted", { calendarIds: [grantedCalendar, ownCalendar.A] }), COORDINATOR_KEY).then((resp) => {
                pinsOf(resp.body.events[0].id).then((pins) =>
                    expect(pins).to.deep.eq([ownCalendar.A, grantedCalendar].sort((a, b) => a - b)),
                );
            });
        });

        it("refuses a church calendar not opened to the ministry, naming it, and creates nothing", () => {
            createEvents(oneEvent("Public refused", { calendarIds: [ownCalendar.A, PUBLIC_CALENDAR] }), COORDINATOR_KEY, 403).then(
                (resp) => {
                    expect(resp.body.message).to.contain("Public Calendar");
                },
            );
            rowsTitled("Public refused").should("have.length", 0);
        });

        it("refuses another ministry's own calendar", () => {
            createEvents(oneEvent("Other own refused", { calendarIds: [ownCalendar.B] }), COORDINATOR_KEY, 403);
            rowsTitled("Other own refused").should("have.length", 0);
        });

        it("lets Add Events pin anywhere", () => {
            createEvents(oneEvent("Admin public", { calendarIds: [PUBLIC_CALENDAR] })).then((resp) => {
                pinsOf(resp.body.events[0].id).then((pins) => expect(pins).to.deep.eq([PUBLIC_CALENDAR]));
            });
        });
    });

    // ── a series ───────────────────────────────────────────────────────────────

    describe("Volunteer v2 D24 — a series through the repeat engine", () => {
        it("creates one event per matching weekday inside the range, every one owned by the ministry", () => {
            const dow = WEEKDAYS[localDate(3).getDay()];
            const expected = weekdaysBetween(dow, 1, 60);

            createEvents(weekly("Weekly Class", dow, 1, 60), COORDINATOR_KEY).then((resp) => {
                expect(resp.body.events.map((e) => e.start.slice(0, 10))).to.deep.eq(expected);
                for (const event of resp.body.events) {
                    expect(event.start.slice(11)).to.eq("09:30:00");
                    expect(event.end.slice(11)).to.eq("10:30:00");
                }
            });
            rowsTitled("Weekly Class").then((rows) => {
                expect(rows).to.have.length(expected.length);
                for (const row of rows) {
                    expect(Number(row.event_ministry_id)).to.eq(ministry.A);
                }
                pinsOf(rows[0].event_id).then((pins) => expect(pins).to.deep.eq([ownCalendar.A]));
            });
        });

        it("puts a monthly day the month lacks on its last day", () => {
            createEvents(
                {
                    ...weekly("Monthly", "Sunday", 1, 150),
                    recurrence: { type: "monthly", dom: 31 },
                },
                COORDINATOR_KEY,
            ).then((resp) => {
                expect(resp.body.events.length).to.be.within(4, 6);
                for (const event of resp.body.events) {
                    const d = new Date(`${event.start.slice(0, 10)}T12:00:00`);
                    const lastDay = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
                    expect(d.getDate(), event.start).to.eq(lastDay);
                }
            });
        });

        it("refuses a series over the repeat engine's cap and creates nothing", () => {
            createEvents(weekly("Too Many", "Sunday", 1, 8 * 365), COORDINATOR_KEY, 400).then((resp) => {
                expect(resp.body.message).to.contain(String(MAX_SERIES));
            });
            rowsTitled("Too Many").should("have.length", 0);
        });

        it("refuses a recurrence that finds no date in the range", () => {
            const outside = isoDate(40).slice(5);
            createEvents(
                { ...weekly("No Dates", "Sunday", 1, 10), recurrence: { type: "yearly", doy: outside } },
                COORDINATOR_KEY,
                400,
            );
            rowsTitled("No Dates").should("have.length", 0);
        });
    });

    // ── authorization ──────────────────────────────────────────────────────────

    describe("Volunteer v2 D24 — who may create a ministry's events", () => {
        it("refuses the coordinator of another ministry", () => {
            createEvents(oneEvent("Other Ministry"), COORDINATOR_KEY, 403, "C");
            rowsTitled("Other Ministry").should("have.length", 0);
        });

        it("refuses a team leader of the ministry", () => {
            createEvents(oneEvent("Team Leader", { calendarIds: [ownCalendar.B] }), COORDINATOR_KEY, 403, "B");
            rowsTitled("Team Leader").should("have.length", 0);
        });

        it("refuses a self-service login even with a ministry scope", () => {
            grantScope(PERSON_SELFEDIT, "team", firstTeam.A);
            createEvents(oneEvent("Self Service"), SELFEDIT_KEY, 403);
            grantScope(PERSON_SELFEDIT, "ministry", ministry.A);
            createEvents(oneEvent("Self Service"), SELFEDIT_KEY, 403).then((resp) => {
                expect(resp.body.message).to.eq("Only a coordinator of this ministry may create its events");
            });
            rowsTitled("Self Service").should("have.length", 0);
            dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [PERSON_SELFEDIT]);
        });

        it("is closed while the rollout is v1", () => {
            setVersion("v1");
            createEvents(oneEvent("Rollout V1"), ADMIN_KEY, 403);
            setVersion("v2");
            rowsTitled("Rollout V1").should("have.length", 0);
        });

        it("needs a login", () => {
            cy.request({ method: "POST", url: eventsUrl("A"), body: oneEvent("No Login"), failOnStatusCode: false }).then(
                (resp) => expect(resp.status).to.eq(401),
            );
        });

        it("answers 404 for an unknown ministry", () => {
            api(ADMIN_KEY, "POST", `${URL}/ministries/999999/events`, oneEvent("Unknown"), 404);
        });
    });

    // ── validation ─────────────────────────────────────────────────────────────

    describe("Volunteer v2 D24 — validation", () => {
        const cases = [
            ["no title", { title: "" }],
            ["no event type", { eventTypeId: undefined }],
            ["an unknown event type", { eventTypeId: 999999 }],
            ["a malformed time", { startTime: "25:00" }],
            ["an end before the start", { startTime: "12:00", endTime: "09:00" }],
            ["no date", { date: undefined }],
            ["a malformed date", { date: "2026-13-01" }],
            ["calendarIds that are not a list", { calendarIds: "1" }],
            ["an unknown calendar", { calendarIds: [999999] }],
            ["an unknown class", { linkedGroupId: 999999 }],
            ["a staff key: staffing is set up after the events are created (D33)", { staff: { teamId: 1 } }],
            ["an unknown recurrence", { date: undefined, recurrence: { type: "daily" }, rangeStart: isoDate(1), rangeEnd: isoDate(9) }],
            ["weekly without a day", { date: undefined, recurrence: { type: "weekly" }, rangeStart: isoDate(1), rangeEnd: isoDate(9) }],
            ["monthly on day 32", { date: undefined, recurrence: { type: "monthly", dom: 32 }, rangeStart: isoDate(1), rangeEnd: isoDate(90) }],
            ["yearly on a day that never exists", { date: undefined, recurrence: { type: "yearly", doy: "02-30" }, rangeStart: isoDate(1), rangeEnd: isoDate(400) }],
            ["a range that ends before it starts", { date: undefined, recurrence: { type: "weekly", dow: "Sunday" }, rangeStart: isoDate(9), rangeEnd: isoDate(1) }],
            ["a series with no range", { date: undefined, recurrence: { type: "weekly", dow: "Sunday" } }],
            ["a series with no last date", { date: undefined, recurrence: { type: "weekly", dow: "Sunday" }, rangeStart: isoDate(1) }],
        ];

        for (const [label, overrides] of cases) {
            it(`answers 400 for ${label}`, () => {
                const body = { ...oneEvent("Invalid"), ...overrides };
                for (const key of Object.keys(body)) {
                    if (body[key] === undefined) {
                        delete body[key];
                    }
                }
                createEvents(body, COORDINATOR_KEY, 400).then((resp) => {
                    expect(resp.body.success).to.eq(false);
                    expect(resp.body.message).to.be.a("string").and.not.be.empty;
                });
            });
        }

        it("created none of them", () => {
            rowsTitled("Invalid").should("have.length", 0);
        });
    });

    // ── the Calendar tab's list ────────────────────────────────────────────────

    describe("Volunteer v2 D24 — GET the ministry's events", () => {
        let pastEventId = 0;
        let gapEventId = 0;
        let unplannedEventId = 0;

        const scheduleFor = (fields) =>
            api(
                ADMIN_KEY,
                "POST",
                `${URL}/ministries/${ministry.A}/schedules`,
                { teamId: firstTeam.A, windowStart: isoDate(0), ...fields },
                201,
            );
        const staffOne = (eventId, requirements) =>
            api(ADMIN_KEY, "POST", `${URL}/ministries/${ministry.A}/staffed-events`, { eventId, teamId: firstTeam.A, requirements }, 201);

        before(() => {
            createEvents(weekly("Faith City", "Sunday", 1, 45, { linkedGroupId: faithCity })).then(() => {
                scheduleFor({
                    name: `${PREFIX} Faith City Teachers`,
                    linkMode: "class",
                    groupId: faithCity,
                    requirements: [{ positionId: position, minCount: 1, maxCount: 1, defaults: [{ personId: POOL_MEMBER, accepted: true }] }],
                });
            });
            createEvents(weekly("Mowing", "Saturday", 1, 30)).then(() => {
                scheduleFor({
                    name: `${PREFIX} Mowing Crew`,
                    linkMode: "ministry",
                    titleFilter: `${PREFIX} Mowing`,
                    requirements: [{ positionId: position, minCount: 1, maxCount: 1, defaults: [{ personId: POOL_MEMBER, accepted: false }] }],
                });
            });
            createEvents(oneEvent("Gap", { date: isoDate(8) })).then((resp) => {
                gapEventId = resp.body.events[0].id;
                staffOne(gapEventId, [{ positionId: position, minCount: 2, maxCount: 2 }]);
            });
            createEvents(oneEvent("Unplanned", { date: isoDate(9) })).then((resp) => {
                unplannedEventId = resp.body.events[0].id;
                staffOne(unplannedEventId, []);
            });
            createEvents(oneEvent("Past", { date: isoDate(-3), linkedGroupId: adultClass })).then((resp) => {
                pastEventId = resp.body.events[0].id;
                dbOk(
                    `INSERT INTO eventcounts_evtcnt (evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes)
                 VALUES (?, 1, 'Total', 30, ''), (?, 2, 'Members', 12, '')`,
                    [pastEventId, pastEventId],
                );
            });
        });

        it("lists the upcoming events the ministry owns, soonest first, with pins, class and per-team staffing", () => {
            api(COORDINATOR_KEY, "GET", eventsUrl("A")).then((resp) => {
                const events = resp.body.events;
                expect(resp.body.past).to.eq(false);
                expect(resp.body.from).to.eq(isoDate(0));
                const starts = events.map((e) => e.start);
                expect(starts).to.deep.eq([...starts].sort());
                expect(events.some((e) => e.id === pastEventId), "the past is not listed").to.eq(false);

                const titled = (title) => events.filter((e) => e.title === `${PREFIX} ${title}`);

                const workday = titled("Workday")[0];
                expect(workday.calendars).to.deep.eq([{ id: ownCalendar.A, name: `${PREFIX} Ministry A` }]);
                expect(workday.linkedGroups).to.deep.eq([{ id: adultClass, name: `${PREFIX} Adult Class` }]);
                expect(workday.staffing).to.deep.eq([]);
                expect(workday.eventTypeName).to.eq("Church Service");

                for (const event of titled("Faith City")) {
                    expect(event.staffing).to.have.length(1);
                    expect(event.staffing[0]).to.include({
                        teamId: firstTeam.A,
                        status: "filled",
                        needed: 1,
                        filled: 1,
                        gap: 0,
                        openCount: 0,
                        capacity: 1,
                    });
                    expect(event.staffing[0].occurrenceIds).to.have.length(1);
                }
                for (const event of titled("Mowing")) {
                    expect(event.staffing[0]).to.include({ status: "pending", filled: 1, pending: 1 });
                }

                const gap = events.find((e) => e.id === gapEventId);
                expect(gap.staffing[0]).to.include({ status: "gap", needed: 2, filled: 0, gap: 2, openCount: 2, capacity: 2 });
                const unplanned = events.find((e) => e.id === unplannedEventId);
                expect(unplanned.staffing[0]).to.include({ status: "unplanned", requirementCount: 0, openCount: 0, capacity: 0 });

                expect(events.every((e) => e.title.startsWith(`${PREFIX}`)), "only this ministry's events").to.eq(true);
            });
        });

        it("lists past events newest first with their headcount total", () => {
            api(COORDINATOR_KEY, "GET", `${eventsUrl("A")}?past=1`).then((resp) => {
                expect(resp.body.past).to.eq(true);
                expect(resp.body.to).to.eq(isoDate(-1));
                const past = resp.body.events.find((e) => e.id === pastEventId);
                expect(past.headcount).to.deep.eq({ recorded: true, total: 42 });
                const starts = resp.body.events.map((e) => e.start);
                expect(starts).to.deep.eq([...starts].sort().reverse());
            });
            api(COORDINATOR_KEY, "GET", eventsUrl("A")).then((resp) => {
                const workday = resp.body.events.find((e) => e.title === `${PREFIX} Workday`);
                expect(workday.headcount).to.deep.eq({ recorded: false, total: 0 });
            });
        });

        it("refuses a malformed or inverted window", () => {
            api(COORDINATOR_KEY, "GET", `${eventsUrl("A")}?from=soon`, null, 400);
            api(COORDINATOR_KEY, "GET", `${eventsUrl("A")}?from=${isoDate(9)}&to=${isoDate(1)}`, null, 400);
        });

        it("is the ministry coordinator's: another ministry's coordinator and a team leader get 403", () => {
            api(COORDINATOR_KEY, "GET", eventsUrl("C"), null, 403);
            api(COORDINATOR_KEY, "GET", eventsUrl("B"), null, 403);
            api(ADMIN_KEY, "GET", `${URL}/ministries/999999/events`, null, 404);
        });
    });

    // ── the dialog's Fill by default with ──────────────────────────────────────

    describe("Volunteer v2 D24 — who may fill a position of a schedule not made yet", () => {
        it("lists the position's qualified people, annotated with the pool", () => {
            api(COORDINATOR_KEY, "GET", `${URL}/positions/${position}/eligible`).then((resp) => {
                const member = resp.body.people.find((p) => p.personId === POOL_MEMBER);
                expect(member, "the qualified member").to.exist;
                expect(member.inPool).to.eq(true);
            });
            api(ADMIN_KEY, "GET", `${URL}/positions/999999/eligible`, null, 404);
        });
    });
});
