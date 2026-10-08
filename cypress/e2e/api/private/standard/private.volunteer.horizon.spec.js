/// <reference types="cypress" />

/**
 * Volunteer v2 D31 — schedules follow events that already exist, up to a church-wide scheduling
 * horizon that a daily timer job keeps every schedule filled to; the "Other" event type and the
 * default type of a ministry's new events. Design §0.8 D31, §2.8, §2.9, §3.6, Appendix B.
 *
 * Personas (seed.sql): `admin.api.key` (administrator). Ministries come from the real create API;
 * events from the ministry's own events endpoint, or straight into `events_event` where a test
 * needs one that is past or inactive. Settings are restored in `after`.
 */

const ADMIN_KEY = "admin.api.key";
const URL = "/api/ministries";
const configUrl = (name) => `/admin/api/system/config/${name}`;
const VERSION = "sVolunteerVersion";
const HORIZON = "iVolunteerSchedulingHorizonWeeks";
const DEFAULT_TYPE = "iVolunteerDefaultEventTypeId";
const RATE_LIMIT = "iTimerJobsMinIntervalMinutes";
const TOP_UP_DATE = "sLastVolunteerTopUpRunDate";
const TOP_UP_RESULT = "sLastVolunteerTopUpResult";
const UPGRADE_SCRIPT = "src/mysql/upgrade/7.8.0-volunteer-v2-schema.sql";
const INSTALL_SCRIPT = "src/mysql/install/Install.sql";

const CHURCH_SERVICE_TYPE = 1;
const OTHER_TYPE = 3;
const SUNDAY_SCHOOL_GROUP_TYPE = 4;
const POOL_MEMBER = 8;
const HORIZON_DAYS = 56;

const PREFIX = "VHOR31";
const WEEKDAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

const original = {};
let timezone = "UTC";
let ministryId = 0;
let closedMinistryId = 0;
let teamA = 0;
let teamB = 0;
let position = 0;
const classes = {};

// ── helpers ────────────────────────────────────────────────────────────────

function dbOk(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(`Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`);
        }
        return result.rows;
    });
}

function api(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAPICall(Cypress.testEnv(ADMIN_KEY), method, url, body, expectedStatus);
}

function setConfig(name, value) {
    cy.makePrivateAdminAPICall("POST", configUrl(name), { value }, 200);
}

function localDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    return d;
}

function isoDate(offsetDays) {
    const d = localDate(offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Today in the install's own time zone, as the top-up claims its day. */
function serverToday() {
    return new Intl.DateTimeFormat("en-CA", { timeZone: timezone, year: "numeric", month: "2-digit", day: "2-digit" }).format(
        new Date(),
    );
}

function createEvents(body, forMinistry = ministryId) {
    return api("POST", `${URL}/ministries/${forMinistry}/events`, body, 201).then((resp) => resp.body.events);
}

function weekly(title, from, to, overrides = {}) {
    return {
        title: `${PREFIX} ${title}`,
        eventTypeId: CHURCH_SERVICE_TYPE,
        recurrence: { type: "weekly", dow: WEEKDAYS[localDate(from).getDay()] },
        rangeStart: isoDate(from),
        rangeEnd: isoDate(to),
        startTime: "09:30",
        endTime: "10:30",
        ...overrides,
    };
}

function oneEvent(title, offset, overrides = {}) {
    return {
        title: `${PREFIX} ${title}`,
        eventTypeId: CHURCH_SERVICE_TYPE,
        date: isoDate(offset),
        startTime: "09:30",
        endTime: "10:30",
        ...overrides,
    };
}

/** An event written straight to the table, for one the events endpoint would not make as asked. */
function insertEvent(title, offset, { inactive = 0, typeId = CHURCH_SERVICE_TYPE } = {}) {
    return dbOk(
        `INSERT INTO events_event (event_type, event_title, event_desc, event_text, event_start, event_end, inactive, event_ministry_id)
         VALUES (?, ?, '', '', ?, ?, ?, ?)`,
        [typeId, `${PREFIX} ${title}`, `${isoDate(offset)} 09:30:00`, `${isoDate(offset)} 10:30:00`, inactive, ministryId],
    ).then((rows) => rows.insertId);
}

function throughDay(events, offset) {
    return events.filter((event) => event.start.slice(0, 10) <= isoDate(offset));
}

function scheduleBody(overrides) {
    return { teamId: teamA, windowStart: isoDate(0), ...overrides, name: `${PREFIX} ${overrides.name}` };
}

/** The new schedule, carrying the run its Save made (D33) as `generated`. */
function createSchedule(overrides, forMinistry = ministryId) {
    return api("POST", `${URL}/ministries/${forMinistry}/schedules`, scheduleBody(overrides), 201).then((resp) => ({
        ...resp.body.schedule,
        generated: resp.body.generated,
    }));
}

function refuseSchedule(overrides) {
    return api("POST", `${URL}/ministries/${ministryId}/schedules`, scheduleBody(overrides), 400).then((resp) => resp.body);
}

function generate(scheduleId, body = {}) {
    return api("POST", `${URL}/schedules/${scheduleId}/generate`, body).then((resp) => resp.body);
}

function occurrencesOf(scheduleId) {
    return dbOk(
        `SELECT vocc_ID AS id, vocc_event_id AS eventId, DATE_FORMAT(vocc_OccurrenceDate, '%Y-%m-%d') AS day
           FROM volunteer_occurrence_vocc WHERE vocc_vsch_ID = ? ORDER BY vocc_OccurrenceDate`,
        [scheduleId],
    ).then((rows) => rows.map((row) => ({ id: Number(row.id), eventId: Number(row.eventId), day: row.day })));
}

function defaultEventType() {
    return api("GET", `${URL}/event-types`).then((resp) => resp.body.defaultEventTypeId);
}

function runTimerJobs(force = false) {
    return api("POST", "/api/background/timerjobs", { force }).then((resp) => {
        expect(resp.body.ran, "the timer jobs ran").to.eq(true);
    });
}

function count(sql, params = []) {
    return dbOk(sql, params).then((rows) => Number(rows[0].n));
}

function makeClass(key) {
    return dbOk(
        `INSERT INTO group_grp (grp_Type, grp_RoleListID, grp_DefaultRole, grp_Name, grp_Description, grp_hasSpecialProps, grp_active, grp_include_email_export)
         VALUES (?, 0, 0, ?, '', 0, 1, 1)`,
        [SUNDAY_SCHOOL_GROUP_TYPE, `${PREFIX} ${key}`],
    ).then((rows) => {
        classes[key] = rows.insertId;
    });
}

function cleanupFixtures() {
    const like = [`${PREFIX}%`];
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
    for (const table of ["calendar_events", "event_audience"]) {
        dbOk(`DELETE t FROM ${table} t JOIN events_event e ON e.event_id = t.event_id WHERE e.event_title LIKE ?`, like);
    }
    dbOk("DELETE FROM events_event WHERE event_title LIKE ?", like);
    dbOk("DELETE FROM event_types WHERE type_name LIKE ?", like);
    dbOk("DELETE FROM group_grp WHERE grp_Name LIKE ?", like);
    dbOk("DELETE FROM calendars WHERE name LIKE ?", like);
    dbOk("DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?", like);
}

// ── fixture ────────────────────────────────────────────────────────────────

before(() => {
    cy.rememberTestEnv(["admin.api.key"]);
    cy.useChurchTimeZone();
});

after(() => {
    cy.useHostTimeZone();
});

describe("Volunteer v2 D31 — schedules follow events that already exist, up to a church-wide scheduling horizon that a daily timer job keeps every schedule filled to; the \"Other\" event type and the default type of a ministry's new events", () => {
    before(() => {
        for (const name of [VERSION, HORIZON, DEFAULT_TYPE, RATE_LIMIT]) {
            cy.makePrivateAdminAPICall("GET", configUrl(name), null, 200).then((resp) => {
                original[name] = String(resp.body.value ?? "");
            });
        }
        cy.makePrivateAdminAPICall("GET", configUrl("sTimeZone"), null, 200).then((resp) => {
            timezone = resp.body.value || "UTC";
        });
        setConfig(VERSION, "v2");
        setConfig(HORIZON, "8");
        setConfig(DEFAULT_TYPE, "");
        dbOk("UPDATE event_types SET type_name = 'Other', type_active = 1 WHERE type_id = ?", [OTHER_TYPE]);
        cleanupFixtures();

        api("POST", `${URL}/ministries`, { name: `${PREFIX} Children`, description: "D31 fixture", sundaySchool: true }, 201).then(
            (resp) => {
                ministryId = resp.body.ministry.id;
                api("GET", `${URL}/ministries/${ministryId}`).then((detail) => {
                    teamA = detail.body.teams[0].id;
                });
            },
        );
        api("POST", `${URL}/ministries`, { name: `${PREFIX} Closed`, description: "D31 fixture" }, 201).then((resp) => {
            closedMinistryId = resp.body.ministry.id;
        });
        for (const key of ["Faith City", "Empty Class"]) {
            makeClass(key);
        }
        cy.then(() => {
            api("POST", `${URL}/ministries/${ministryId}/teams`, { name: `${PREFIX} Team B` }, 201).then((resp) => {
                teamB = resp.body.team.id;
            });
            api("POST", `${URL}/ministries/${ministryId}/positions`, { name: `${PREFIX} Helper`, teamId: teamA }, 201).then(
                (resp) => {
                    position = resp.body.position.id;
                    api("POST", `${URL}/positions/${position}/qualifications`, { personId: POOL_MEMBER }, [200, 201]);
                },
            );
        });
    });

    after(() => {
        cleanupFixtures();
        dbOk("UPDATE event_types SET type_name = 'Other', type_active = 1 WHERE type_id = ?", [OTHER_TYPE]);
        for (const name of [HORIZON, DEFAULT_TYPE, RATE_LIMIT, VERSION]) {
            setConfig(name, original[name]);
        }
    });

    // ── The horizon ────────────────────────────────────────────────────────────

    describe("Volunteer v2 D31 — generation stops at the church-wide scheduling horizon", () => {
        let service = [];
        let schedule = null;

        before(() => {
            createEvents(weekly("Service", 1, 90)).then((events) => {
                service = events;
            });
            cy.then(() => {
                createSchedule({ name: "Weekly", linkMode: "ministry", titleFilter: `${PREFIX} Service` }).then((created) => {
                    schedule = created;
                });
            });
        });

        it("defaults to 8 weeks, and every schedule says how far a run reaches", () => {
            cy.makePrivateAdminAPICall("GET", configUrl(HORIZON), null, 200).its("body.value").should("eq", "8");
            expect(schedule).not.to.have.property("generateAheadDays");
            expect(schedule).to.include({ horizonWeeks: 8, generateThrough: isoDate(HORIZON_DAYS) });
        });

        it("makes occurrences on Save from today through today + the horizon, and no further", () => {
            const inside = throughDay(service, HORIZON_DAYS);
            expect(service.length, "the series runs past the horizon").to.be.greaterThan(inside.length);
            expect(schedule.generated).to.include({ from: isoDate(0), through: isoDate(HORIZON_DAYS), created: inside.length });
            occurrencesOf(schedule.id).then((rows) => {
                expect(rows.map((row) => row.eventId)).to.have.members(inside.map((event) => event.id));
            });
            generate(schedule.id).then((result) => {
                expect(result).to.include({ through: isoDate(HORIZON_DAYS), created: 0, existing: inside.length });
            });
        });

        it("never lets an explicit through pass the horizon", () => {
            generate(schedule.id, { through: isoDate(90) }).then((result) => {
                expect(result).to.include({ through: isoDate(HORIZON_DAYS), created: 0 });
            });
        });

        it("stops at the schedule's last date when that comes first", () => {
            createSchedule({ name: "Short", teamId: teamB, linkMode: "ministry", titleFilter: `${PREFIX} Service`, windowEnd: isoDate(20) }).then(
                (short) => {
                    expect(short.generateThrough).to.eq(isoDate(20));
                    expect(short.generated).to.include({ through: isoDate(20), created: throughDay(service, 20).length });
                },
            );
        });

        it("follows a new horizon at once, within 1 to 52 weeks, and 8 when it is blank", () => {
            setConfig(HORIZON, "2");
            api("GET", `${URL}/schedules/${schedule.id}`).its("body.schedule").should("include", {
                horizonWeeks: 2,
                generateThrough: isoDate(14),
            });
            generate(schedule.id).its("through").should("eq", isoDate(14));
            setConfig(HORIZON, "0");
            api("GET", `${URL}/schedules/${schedule.id}`).its("body.schedule.horizonWeeks").should("eq", 1);
            // The config API refuses a blank (D32); one stored another way reads as the default, not 1.
            cy.makePrivateAdminAPICall("POST", configUrl(HORIZON), { value: "" }, 400);
            dbOk("UPDATE config_cfg SET cfg_value = '' WHERE cfg_name = ?", [HORIZON]);
            api("GET", `${URL}/schedules/${schedule.id}`).its("body.schedule.horizonWeeks").should("eq", 8);
            setConfig(HORIZON, "99");
            api("GET", `${URL}/schedules/${schedule.id}`).its("body.schedule.horizonWeeks").should("eq", 52);
            setConfig(HORIZON, "8");
        });

        it("does not cap Staff this event: a hand-picked event beyond the horizon is staffed", () => {
            createEvents(oneEvent("Far Festival", HORIZON_DAYS + 24)).then((events) => {
                api("POST", `${URL}/ministries/${ministryId}/staffed-events`, { eventId: events[0].id, teamId: teamA }, 201).then((resp) => {
                    expect(resp.body.occurrence.eventId).to.eq(events[0].id);
                });
            });
        });

        it("refuses the removed generateAheadDays on create and update", () => {
            refuseSchedule({ name: "Ahead", linkMode: "ministry", titleFilter: `${PREFIX} Service`, generateAheadDays: 14 }).then((body) => {
                expect(body.message).to.contain("generateAheadDays");
            });
            api("POST", `${URL}/schedules/${schedule.id}`, { generateAheadDays: 14 }, 400);
        });
    });

    // ── The daily top-up ───────────────────────────────────────────────────────

    describe("Volunteer v2 D31 — every schedule is topped up to the horizon once a day", () => {
        const made = {};
        const schedules = {};
        let notificationsBefore = 0;

        before(() => {
            setConfig(RATE_LIMIT, "0");
            createEvents(weekly("Rehearsal", 1, 90)).then((events) => {
                made.rehearsal = events;
            });
            createEvents(weekly("Paused Rehearsal", 1, 30)).then((events) => {
                made.paused = events;
            });
            createEvents(oneEvent("Concert", 5)).then((events) => {
                made.concert = events;
            });
            createEvents(weekly("Closed Workday", 1, 30), closedMinistryId).then((events) => {
                made.closed = events;
            });
            cy.then(() => {
                api("GET", `${URL}/ministries/${closedMinistryId}`).then((detail) => {
                    createSchedule(
                        { name: "Closed Crew", teamId: detail.body.teams[0].id, linkMode: "ministry", titleFilter: `${PREFIX} Closed Workday` },
                        closedMinistryId,
                    ).then((created) => {
                        schedules.closed = created.id;
                    });
                });
                createSchedule({
                    name: "Rehearsal Crew",
                    linkMode: "ministry",
                    titleFilter: `${PREFIX} Rehearsal`,
                    requirements: [{ positionId: position, minCount: 1, maxCount: 1 }],
                }).then((created) => {
                    schedules.rehearsal = created.id;
                });
                createSchedule({
                    name: "Ends Soon",
                    teamId: teamB,
                    linkMode: "ministry",
                    titleFilter: `${PREFIX} Rehearsal`,
                    windowEnd: isoDate(10),
                }).then((created) => {
                    schedules.endsSoon = created.id;
                });
                createSchedule({ name: "Paused", linkMode: "ministry", titleFilter: `${PREFIX} Paused Rehearsal`, active: false }).then(
                    (created) => {
                        schedules.paused = created.id;
                    },
                );
                api("POST", `${URL}/ministries/${ministryId}/staffed-events`, { eventId: made.concert[0].id, teamId: teamA }, 201).then(
                    (resp) => {
                        schedules.oneOff = resp.body.schedule.id;
                        // A one-off schedule whose occurrence was deleted: the top-up must not bring it back.
                        dbOk("DELETE FROM volunteer_occurrence_vocc WHERE vocc_vsch_ID = ?", [schedules.oneOff]);
                    },
                );
            });
            cy.then(() => {
                // Their Save made occurrences (D33); cleared, so the top-up has the horizon to fill.
                dbOk("DELETE FROM volunteer_occurrence_vocc WHERE vocc_vsch_ID IN (?, ?, ?)", [
                    schedules.rehearsal,
                    schedules.endsSoon,
                    schedules.closed,
                ]);
                api("POST", `${URL}/ministries/${closedMinistryId}`, { active: false });
                dbOk("DELETE FROM config_cfg WHERE cfg_name IN (?, ?)", [TOP_UP_DATE, TOP_UP_RESULT]);
                count("SELECT COUNT(*) AS n FROM volunteer_notification_vntf").then((n) => {
                    notificationsBefore = n;
                });
            });
        });

        it("fills every active schedule of an active ministry to the horizon on the day's first run", () => {
            runTimerJobs();
            occurrencesOf(schedules.rehearsal).then((rows) => {
                expect(rows.map((row) => row.eventId)).to.have.members(throughDay(made.rehearsal, HORIZON_DAYS).map((e) => e.id));
            });
            occurrencesOf(schedules.endsSoon).then((rows) => {
                expect(rows.map((row) => row.eventId), "not past its last date").to.have.members(
                    throughDay(made.rehearsal, 10).map((e) => e.id),
                );
            });
            occurrencesOf(schedules.paused).should("have.length", 0);
            occurrencesOf(schedules.oneOff).should("have.length", 0);
            occurrencesOf(schedules.closed).should("have.length", 0);
        });

        it("refuses Generate for a paused schedule (409), as the daily run skips it", () => {
            api("POST", `${URL}/schedules/${schedules.paused}/generate`, {}, 409).its("body.message").should("contain", "inactive");
            occurrencesOf(schedules.paused).should("have.length", 0);
        });

        it("records the day and what it made, for Ministry Settings", () => {
            dbOk("SELECT cfg_name AS name, cfg_value AS value FROM config_cfg WHERE cfg_name IN (?, ?)", [TOP_UP_DATE, TOP_UP_RESULT]).then(
                (rows) => {
                    const byName = Object.fromEntries(rows.map((row) => [row.name, row.value]));
                    expect(byName[TOP_UP_DATE]).to.eq(serverToday());
                    const result = JSON.parse(byName[TOP_UP_RESULT]);
                    expect(result.ranAt.slice(0, 10)).to.eq(serverToday());
                    const expected = throughDay(made.rehearsal, HORIZON_DAYS).length + throughDay(made.rehearsal, 10).length;
                    expect(result.created).to.be.at.least(expected);
                },
            );
        });

        it("assigns nobody and sends nothing", () => {
            count(
                `SELECT COUNT(*) AS n FROM volunteer_assignment_vasg vasg
               JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
              WHERE vocc.vocc_vsch_ID IN (?, ?)`,
                [schedules.rehearsal, schedules.endsSoon],
            ).should("eq", 0);
            count("SELECT COUNT(*) AS n FROM volunteer_notification_vntf").then((n) => expect(n).to.eq(notificationsBefore));
        });

        it("runs once a day: a second run the same day adds nothing, even for a new event", () => {
            createEvents(oneEvent("Rehearsal", 3, { startTime: "18:00", endTime: "19:00" })).then((events) => {
                made.extra = events[0];
            });
            runTimerJobs();
            occurrencesOf(schedules.rehearsal).then((rows) => {
                expect(rows.map((row) => row.eventId)).not.to.include(made.extra.id);
            });
        });

        it("runs again when an administrator asks, making only what is missing", () => {
            occurrencesOf(schedules.rehearsal).then((before) => {
                runTimerJobs(true);
                occurrencesOf(schedules.rehearsal).then((rows) => {
                    expect(rows).to.have.length(before.length + 1);
                    expect(rows.map((row) => row.eventId)).to.include(made.extra.id);
                    expect(new Set(rows.map((row) => row.eventId)).size, "one occurrence per event").to.eq(rows.length);
                });
            });
        });

        it("does nothing while Volunteer v2 is off", () => {
            setConfig(VERSION, "v1");
            dbOk("DELETE FROM config_cfg WHERE cfg_name = ?", [TOP_UP_DATE]);
            runTimerJobs(true);
            count("SELECT COUNT(*) AS n FROM config_cfg WHERE cfg_name = ?", [TOP_UP_DATE]).should("eq", 0);
            setConfig(VERSION, "v2");
        });
    });

    // ── The "Other" event type and the default type ────────────────────────────

    describe("Volunteer v2 D31 — an \"Other\" event type and the default type of ministry events", () => {
        afterEach(() => {
            setConfig(DEFAULT_TYPE, "");
            dbOk("UPDATE event_types SET type_name = 'Other', type_active = 1 WHERE type_id = ?", [OTHER_TYPE]);
        });

        it("offers \"Other\" while no default is chosen, and the chosen type once one is", () => {
            api("GET", `${URL}/event-types`).then((resp) => {
                expect(resp.body.eventTypes).to.deep.include({ id: OTHER_TYPE, name: "Other" });
                expect(resp.body.defaultEventTypeId).to.eq(OTHER_TYPE);
            });
            setConfig(DEFAULT_TYPE, String(CHURCH_SERVICE_TYPE));
            defaultEventType().should("eq", CHURCH_SERVICE_TYPE);
        });

        it("falls back to \"Other\" when the chosen type is deleted or retired", () => {
            dbOk(
                `INSERT INTO event_types (type_name, type_defstarttime, type_defrecurtype, type_defrecurDOW, type_defrecurDOM, type_defrecurDOY, type_active)
             VALUES (?, '10:00:00', 'none', 'Sunday', '', '2016-01-01', 1)`,
                [`${PREFIX} Temporary`],
            ).then((rows) => {
                const typeId = rows.insertId;
                setConfig(DEFAULT_TYPE, String(typeId));
                defaultEventType().should("eq", typeId);
                dbOk("UPDATE event_types SET type_active = 0 WHERE type_id = ?", [typeId]);
                defaultEventType().should("eq", OTHER_TYPE);
                dbOk("DELETE FROM event_types WHERE type_id = ?", [typeId]);
                defaultEventType().should("eq", OTHER_TYPE);
            });
        });

        it("survives \"Other\" being renamed: no default unless one is chosen, and a chosen one stays", () => {
            dbOk("UPDATE event_types SET type_name = ? WHERE type_id = ?", [`${PREFIX} Misc`, OTHER_TYPE]);
            defaultEventType().should("eq", null);
            setConfig(DEFAULT_TYPE, String(OTHER_TYPE));
            defaultEventType().should("eq", OTHER_TYPE);
        });

        it("is not added by the 7.8.0 script or a fresh install (#10357)", () => {
            cy.readFile(UPGRADE_SCRIPT).should("not.contain", "INSERT INTO `event_types`");
            cy.readFile(INSTALL_SCRIPT).then((script) => {
                const start = script.indexOf("INSERT INTO `event_types`");
                expect(script.slice(start, script.indexOf(";", start))).not.to.contain("'Other'");
            });
        });
    });

    // ── Titles: required and exact ─────────────────────────────────────────────

    describe("Volunteer v2 D31 — a type or ministry schedule names one title, matched exactly", () => {
        const made = {};

        before(() => {
            createEvents(weekly("VBS", 2, 30)).then((events) => {
                made.vbs = events;
            });
            createEvents(oneEvent("VBS Day 2", 3)).then((events) => {
                made.vbsDay2 = events[0];
            });
            createEvents(oneEvent("vbs", 4, { startTime: "13:00", endTime: "14:00" })).then((events) => {
                made.vbsLower = events[0];
            });
            createEvents(weekly("Vespers", 2, 30)).then((events) => {
                made.vespers = events;
            });
            createEvents(oneEvent("Vespers Supper", 5)).then((events) => {
                made.vespersSupper = events[0];
            });
        });

        it("refuses a schedule with no title in either mode: there is no \"any event\"", () => {
            refuseSchedule({ name: "Any Service", linkMode: "event_type", eventTypeId: CHURCH_SERVICE_TYPE }).its("message").should(
                "eq",
                "Choose the event this schedule follows",
            );
            refuseSchedule({ name: "Blank Service", linkMode: "event_type", eventTypeId: CHURCH_SERVICE_TYPE, titleFilter: "  " });
            refuseSchedule({ name: "Any Event", linkMode: "ministry" }).its("message").should("eq", "Choose the event this schedule follows");
        });

        it("follows exactly the title in ministry mode, ignoring case: VBS, not VBS Day 2", () => {
            createSchedule({ name: "VBS Crew", linkMode: "ministry", titleFilter: `${PREFIX} vbs` }).then((schedule) => {
                made.vbsSchedule = schedule.id;
                generate(schedule.id);
                occurrencesOf(schedule.id).then((rows) => {
                    const ids = rows.map((row) => row.eventId);
                    expect(ids).to.have.members([...made.vbs.map((e) => e.id), made.vbsLower.id]);
                    expect(ids).not.to.include(made.vbsDay2.id);
                });
            });
        });

        it("follows exactly the title in event-type mode too", () => {
            createSchedule({
                name: "Vespers Crew",
                linkMode: "event_type",
                eventTypeId: CHURCH_SERVICE_TYPE,
                titleFilter: `${PREFIX} VESPERS`,
            }).then((schedule) => {
                generate(schedule.id);
                occurrencesOf(schedule.id).then((rows) => {
                    const ids = rows.map((row) => row.eventId);
                    expect(ids).to.have.members(made.vespers.map((e) => e.id));
                    expect(ids).not.to.include(made.vespersSupper.id);
                });
            });
        });

        it("lists one title per series in the picker, however it is capitalised", () => {
            api("GET", `${URL}/event-series?ministryId=${ministryId}`).then((resp) => {
                const vbs = resp.body.series.filter((row) => row.title.toLowerCase() === `${PREFIX} vbs`.toLowerCase());
                expect(vbs).to.have.length(1);
                expect(vbs[0].count).to.eq(made.vbs.length + 1);
            });
        });

        it("sends a new series only to the schedule with exactly its title, whatever the case (D33)", () => {
            createSchedule({ name: "Day Two Crew", teamId: teamB, linkMode: "ministry", titleFilter: `${PREFIX} VBS Day 2` }).then((dayTwo) => {
                createEvents(weekly("VBS", 31, 45)).then((events) => {
                    api("POST", `${URL}/ministries/${ministryId}/events/staff`, { eventIds: events.map((e) => e.id) }).then((resp) => {
                        const ids = resp.body.schedules.map((run) => run.schedule.id);
                        expect(ids, "the vbs schedule").to.deep.eq([made.vbsSchedule]);
                        expect(ids, "VBS is not VBS Day 2").not.to.include(dayTwo.id);
                    });
                });
            });
        });

        it("generates nothing for a schedule saved without a title before D31, and says why", () => {
            dbOk(
                `INSERT INTO volunteer_schedule_vsch (vsch_vmin_ID, vsch_vtem_ID, vsch_Name, vsch_LinkMode, vsch_WindowStart, vsch_Active)
             VALUES (?, ?, ?, 'ministry', ?, 1)`,
                [ministryId, teamA, `${PREFIX} Legacy`, isoDate(0)],
            ).then((rows) => {
                made.legacy = rows.insertId;
                generate(made.legacy).then((result) => {
                    expect(result).to.include({ created: 0, existing: 0, noEvents: true });
                    expect(result.searched).to.include({ linkMode: "ministry", titleFilter: null });
                });
            });
        });

        it("asks for a title when such a schedule is next edited", () => {
            api("POST", `${URL}/schedules/${made.legacy}`, { name: `${PREFIX} Legacy renamed` }, 400).its("body.message").should(
                "eq",
                "Choose the event this schedule follows",
            );
            api("POST", `${URL}/schedules/${made.legacy}`, { titleFilter: `${PREFIX} Vespers` }).its("body.schedule.titleFilter").should(
                "eq",
                `${PREFIX} Vespers`,
            );
        });
    });

    // ── A schedule follows events that already exist ───────────────────────────

    describe("Volunteer v2 D31 — a schedule is created, or re-pointed, only while an upcoming event matches", () => {
        let following = null;

        before(() => {
            insertEvent("Empty Class Past", -7).then((eventId) => {
                dbOk("INSERT INTO event_audience (event_id, group_id) VALUES (?, ?)", [eventId, classes["Empty Class"]]);
            });
            insertEvent("Gone Service", -3);
            insertEvent("Cancelled Service", 6, { inactive: 1 });
            createEvents(oneEvent("Faith City Meeting", 6, { linkedGroupId: classes["Faith City"] }));
            createEvents(oneEvent("Last Workday", 2));
            cy.then(() => {
                createSchedule({ name: "Last Workday Crew", linkMode: "ministry", titleFilter: `${PREFIX} Last Workday` }).then((schedule) => {
                    following = schedule;
                });
            });
        });

        it("refuses a class schedule for a class whose meetings are all past", () => {
            refuseSchedule({ name: "Empty Class Crew", linkMode: "class", groupId: classes["Empty Class"] }).its("message").should(
                "contain",
                `${PREFIX} Empty Class has no upcoming meetings on the calendar`,
            );
        });

        it("refuses a title with no upcoming active event: unknown, only past or only inactive", () => {
            for (const title of ["No Such Service", "Gone Service", "Cancelled Service"]) {
                refuseSchedule({
                    name: `${title} Crew`,
                    linkMode: "event_type",
                    eventTypeId: CHURCH_SERVICE_TYPE,
                    titleFilter: `${PREFIX} ${title}`,
                }).its("message").should("contain", `No upcoming Church Service events titled "${PREFIX} ${title}"`);
            }
            refuseSchedule({ name: "Nothing Crew", linkMode: "ministry", titleFilter: `${PREFIX} No Such Workday` }).its("message").should(
                "contain",
                "This ministry has no upcoming events titled",
            );
        });

        it("accepts a class with an upcoming meeting", () => {
            createSchedule({ name: "Faith City Crew", linkMode: "class", groupId: classes["Faith City"] }).its("groupId").should(
                "eq",
                classes["Faith City"],
            );
        });

        it("refuses changing what a schedule follows to events that do not exist", () => {
            const update = (body) => api("POST", `${URL}/schedules/${following.id}`, body, 400);
            update({ titleFilter: `${PREFIX} No Such Workday` });
            update({ linkMode: "class", groupId: classes["Empty Class"] });
            update({ linkMode: "event_type", eventTypeId: OTHER_TYPE, titleFilter: `${PREFIX} Last Workday` });
            api("GET", `${URL}/schedules/${following.id}`).its("body.schedule").should("include", {
                linkMode: "ministry",
                titleFilter: `${PREFIX} Last Workday`,
            });
        });

        it("never refuses any other edit once its events have run out", () => {
            dbOk(
                `UPDATE events_event SET event_start = ?, event_end = ? WHERE event_title = ?`,
                [`${isoDate(-3)} 09:30:00`, `${isoDate(-3)} 10:30:00`, `${PREFIX} Last Workday`],
            );
            const edit = (body) => api("POST", `${URL}/schedules/${following.id}`, body);
            edit({ name: `${PREFIX} Last Workday Crew (renamed)` });
            edit({ windowEnd: isoDate(60), startOffsetMinutes: -30, active: false });
            edit({ requirements: [{ positionId: position, minCount: 2, maxCount: 2 }] });
            edit({ titleFilter: `${PREFIX} LAST WORKDAY` }).its("body.schedule.titleFilter").should("eq", `${PREFIX} LAST WORKDAY`);
        });
    });
});
