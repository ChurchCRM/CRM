/// <reference types="cypress" />

/**
 * Volunteer v2 D32 — default volunteers belong to the schedule. A schedule's staffing needs keep
 * default people per position — several since D35, up to the need's Max, in order — and whether
 * each is set as accepted; the schedule dialog and the Generate dialog save them; every run that
 * creates occurrences — a new schedule's Save (D33), Generate, Staff them and the daily top-up —
 * assigns each saved default on the occurrences it creates while that person still holds an
 * active qualification, and counts the ones it skips. Plus the Ministry Settings half of D32: the
 * server refuses a blank for a number setting. Design §0.8 D32, D33, D35, §2.10, §3.3.2, §3.6,
 * Appendix B.
 *
 * Where a test is about Generate or the top-up, its schedule is saved inactive — a paused schedule's
 * Save makes nothing (D33) — and then switched on, which generates nothing either.
 *
 * Personas (seed.sql): `admin.api.key` (administrator, person 1). Ministries, positions,
 * qualifications and events come from the real APIs; an event that must already be over today is
 * written straight to `events_event`. Settings are restored in `after`.
 */

const ADMIN_KEY = "admin.api.key";
const URL = "/api/ministries";
const configUrl = (name) => `/admin/api/system/config/${name}`;
const VERSION = "sVolunteerVersion";
const HORIZON = "iVolunteerSchedulingHorizonWeeks";
const LEAD = "iVolunteerReminderLeadHours";
const DEFAULT_TYPE = "iVolunteerDefaultEventTypeId";
const RATE_LIMIT = "iTimerJobsMinIntervalMinutes";
const TOP_UP_DATE = "sLastVolunteerTopUpRunDate";
const TOP_UP_RESULT = "sLastVolunteerTopUpResult";

const ADMIN_PERSON = 1;
const LEADER_A = 8;
const READER = 9;
const LEADER_B = 10;
const USHER = 11;
const NOT_QUALIFIED = 12;
const CHURCH_SERVICE_TYPE = 1;
const HORIZON_DAYS = 56;

const PREFIX = "VDEF32";
const WEEKDAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

const original = {};
let timezone = "UTC";
let ministryId = 0;
let teamId = 0;
const position = {};

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

function setConfig(name, value, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall("POST", configUrl(name), { value }, expectedStatus);
}

function getConfig(name) {
    return cy.makePrivateAdminAPICall("GET", configUrl(name), null, 200).then((resp) => String(resp.body.value ?? ""));
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

/** Today in the install's own time zone, as the top-up and the "already happened" rule read it. */
function serverToday() {
    return new Intl.DateTimeFormat("en-CA", { timeZone: timezone, year: "numeric", month: "2-digit", day: "2-digit" }).format(
        new Date(),
    );
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
        startTime: "18:00",
        endTime: "19:00",
        ...overrides,
    };
}

function createEvents(body, expectedStatus = 201) {
    return api("POST", `${URL}/ministries/${ministryId}/events`, body, expectedStatus);
}

function createSchedule(name, title, overrides = {}, expectedStatus = 201) {
    return api(
        "POST",
        `${URL}/ministries/${ministryId}/schedules`,
        {
            name: `${PREFIX} ${name}`,
            teamId,
            linkMode: "ministry",
            titleFilter: `${PREFIX} ${title}`,
            windowStart: isoDate(0),
            ...overrides,
        },
        expectedStatus,
    );
}

function requirementsOf(scheduleId) {
    return api("GET", `${URL}/schedules/${scheduleId}/requirements`).then((resp) =>
        Object.fromEntries(resp.body.requirements.map((row) => [row.positionId, row])),
    );
}

/** The stored default rows of one need, in assignment order. */
function storedDefaults(scheduleId, positionId) {
    return dbOk(
        `SELECT vrdf.vrdf_per_ID AS person, vrdf.vrdf_Accepted AS accepted, vrdf.vrdf_SetBy_per_ID AS setBy, vrdf.vrdf_Sort AS sort
           FROM volunteer_requirement_default_vrdf vrdf
           JOIN volunteer_requirement_vreq vreq ON vreq.vreq_ID = vrdf.vrdf_vreq_ID
          WHERE vreq.vreq_vsch_ID = ? AND vreq.vreq_vpos_ID = ?
          ORDER BY vrdf.vrdf_Sort, vrdf.vrdf_ID`,
        [scheduleId, positionId],
    ).then((rows) =>
        rows.map((row) => ({
            person: Number(row.person),
            accepted: Number(row.accepted),
            setBy: row.setBy === null ? null : Number(row.setBy),
            sort: Number(row.sort),
        })),
    );
}

const people = (row) => row.defaults.map((d) => d.personId);

/** A schedule whose occurrences are left for Generate or the top-up to make: saved paused, then switched on. */
function createPausedThenActive(name, title, overrides = {}) {
    return createSchedule(name, title, { ...overrides, active: false }).then((resp) => {
        expect(resp.body.generated, "a paused schedule's Save makes nothing").to.eq(null);
        const scheduleId = resp.body.schedule.id;
        api("POST", `${URL}/schedules/${scheduleId}`, { active: true }).its("body.schedule.occurrenceCount").should("eq", 0);

        return cy.wrap(scheduleId);
    });
}

function generate(scheduleId, body = {}, expectedStatus = 200) {
    return api("POST", `${URL}/schedules/${scheduleId}/generate`, body, expectedStatus).then((resp) => resp.body);
}

function runTimerJobs(force) {
    return api("POST", "/api/background/timerjobs", { force }).then((resp) => {
        expect(resp.body.ran, "the timer jobs ran").to.eq(true);
    });
}

function lastTopUp() {
    return dbOk("SELECT cfg_value AS value FROM config_cfg WHERE cfg_name = ?", [TOP_UP_RESULT]).then((rows) =>
        JSON.parse(rows[0].value),
    );
}

/** Every occurrence of a schedule with its assignments, oldest first. */
function staffingOf(scheduleId) {
    return dbOk(
        `SELECT vocc.vocc_ID AS occurrenceId, vocc.vocc_event_id AS eventId,
                DATE_FORMAT(vocc.vocc_OccurrenceDate, '%Y-%m-%d') AS day,
                vasg.vasg_ID AS assignmentId, vasg.vasg_vpos_ID AS positionId, vasg.vasg_per_ID AS personId,
                vasg.vasg_Status AS status, vasg.vasg_Source AS source, vasg.vasg_AssignedBy_per_ID AS assignedBy,
                (SELECT COUNT(*) FROM volunteer_response_vrsp vrsp
                  WHERE vrsp.vrsp_vasg_ID = vasg.vasg_ID AND vrsp.vrsp_Channel = 'coordinator'
                    AND vrsp.vrsp_Response = 'accepted' AND vrsp.vrsp_per_ID = ?) AS coordinatorAccepts,
                (SELECT COUNT(*) FROM volunteer_notification_vntf vntf
                  WHERE vntf.vntf_vasg_ID = vasg.vasg_ID AND vntf.vntf_Type = 'assignment') AS asks
           FROM volunteer_occurrence_vocc vocc
           LEFT JOIN volunteer_assignment_vasg vasg ON vasg.vasg_vocc_ID = vocc.vocc_ID
          WHERE vocc.vocc_vsch_ID = ?
          ORDER BY vocc.vocc_OccurrenceDate, vocc.vocc_ID, vasg.vasg_vpos_ID`,
        [ADMIN_PERSON, scheduleId],
    ).then((rows) => {
        const byOccurrence = new Map();
        for (const row of rows) {
            const id = Number(row.occurrenceId);
            if (!byOccurrence.has(id)) {
                byOccurrence.set(id, { id, eventId: Number(row.eventId), day: row.day, assignments: [] });
            }
            if (row.assignmentId !== null) {
                byOccurrence.get(id).assignments.push({
                    positionId: Number(row.positionId),
                    personId: Number(row.personId),
                    status: row.status,
                    source: row.source,
                    assignedBy: row.assignedBy === null ? null : Number(row.assignedBy),
                    coordinatorAccepts: Number(row.coordinatorAccepts),
                    asks: Number(row.asks),
                });
            }
        }
        return [...byOccurrence.values()];
    });
}

function qualify(positionKey, personId) {
    api("POST", `${URL}/positions/${position[positionKey]}/qualifications`, { personId }, [200, 201]);
}

function cleanupFixtures() {
    const like = [`${PREFIX}%`];
    dbOk(
        `DELETE vasg FROM volunteer_assignment_vasg vasg
           JOIN volunteer_position_vpos vpos ON vpos.vpos_ID = vasg.vasg_vpos_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
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
    for (const table of ["calendar_events", "event_audience"]) {
        dbOk(`DELETE t FROM ${table} t JOIN events_event e ON e.event_id = t.event_id WHERE e.event_title LIKE ?`, like);
    }
    dbOk("DELETE FROM events_event WHERE event_title LIKE ?", like);
    dbOk("DELETE FROM calendars WHERE name LIKE ?", like);
    dbOk("DELETE FROM group_grp WHERE grp_Name LIKE ?", like);
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

describe("Volunteer v2 D32 — default volunteers belong to the schedule", () => {
    before(() => {
        for (const name of [VERSION, HORIZON, LEAD, DEFAULT_TYPE, RATE_LIMIT]) {
            getConfig(name).then((value) => {
                original[name] = value;
            });
        }
        getConfig("sTimeZone").then((value) => {
            timezone = value || "UTC";
        });
        setConfig(VERSION, "v2");
        setConfig(HORIZON, "8");
        cleanupFixtures();

        api("POST", `${URL}/ministries`, { name: `${PREFIX} Worship`, description: "D32 fixture" }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
            api("GET", `${URL}/ministries/${ministryId}`).then((detail) => {
                teamId = detail.body.teams[0].id;
            });
        });
        cy.then(() => {
            for (const [key, name] of [
                ["lead", "Song Leader"],
                ["reader", "Reader"],
                ["usher", "Usher"],
            ]) {
                api("POST", `${URL}/ministries/${ministryId}/positions`, { name: `${PREFIX} ${name}`, teamId }, 201).then((resp) => {
                    position[key] = resp.body.position.id;
                });
            }
        });
        cy.then(() => {
            qualify("lead", LEADER_A);
            qualify("lead", LEADER_B);
            qualify("reader", READER);
            qualify("usher", USHER);
            createEvents(weekly("Service", 1, 90));
        });
    });

    after(() => {
        cleanupFixtures();
        for (const name of [HORIZON, LEAD, DEFAULT_TYPE, RATE_LIMIT, VERSION]) {
            setConfig(name, original[name], [200, 400]);
        }
    });

    // ── the schedule dialog ────────────────────────────────────────────────────

    describe("Volunteer v2 D32 — a schedule keeps its default volunteers per position", () => {
        let scheduleId = 0;

        const needs = (overrides = {}) => [
            { positionId: position.lead, minCount: 1, maxCount: 2, ...(overrides.lead ?? {}) },
            { positionId: position.reader, minCount: 1, maxCount: 1, ...(overrides.reader ?? {}) },
            { positionId: position.usher, minCount: 0, maxCount: 1, ...(overrides.usher ?? {}) },
        ];

        before(() => {
            createSchedule("Dialog", "Service", {
                requirements: needs({
                    lead: {
                        defaults: [
                            { personId: LEADER_A, accepted: true },
                            { personId: LEADER_B, accepted: false },
                        ],
                    },
                    reader: { defaults: [{ personId: READER }] },
                }),
            }).then((resp) => {
                scheduleId = resp.body.schedule.id;
            });
        });

        it("saves two defaults on a Max-2 need, in order, and lists each back", () => {
            requirementsOf(scheduleId).then((rows) => {
                expect(rows[position.lead].defaults).to.have.length(2);
                expect(rows[position.lead].defaults[0]).to.include({
                    personId: LEADER_A,
                    accepted: true,
                    qualified: true,
                    setBy: ADMIN_PERSON,
                });
                expect(rows[position.lead].defaults[0].name).to.be.a("string").and.not.be.empty;
                expect(rows[position.lead].defaults[0].setByName).to.be.a("string").and.not.be.empty;
                expect(rows[position.lead].defaults[1]).to.include({ personId: LEADER_B, accepted: false, qualified: true });
                expect(rows[position.reader].defaults).to.have.length(1);
                expect(rows[position.reader].defaults[0]).to.include({ personId: READER, accepted: false, qualified: true });
                expect(rows[position.usher].defaults).to.deep.eq([]);
            });
            storedDefaults(scheduleId, position.lead).then((rows) => {
                expect(rows).to.deep.eq([
                    { person: LEADER_A, accepted: 1, setBy: ADMIN_PERSON, sort: 0 },
                    { person: LEADER_B, accepted: 0, setBy: ADMIN_PERSON, sort: 1 },
                ]);
            });
        });

        it("keeps the defaults an edit leaves out, reorders and changes the ones it names, and clears an empty list", () => {
            api("POST", `${URL}/schedules/${scheduleId}`, { requirements: needs() });
            requirementsOf(scheduleId).then((rows) => {
                expect(people(rows[position.lead])).to.deep.eq([LEADER_A, LEADER_B]);
                expect(people(rows[position.reader])).to.deep.eq([READER]);
            });

            api("POST", `${URL}/schedules/${scheduleId}`, {
                requirements: needs({
                    lead: { defaults: [{ personId: LEADER_B, accepted: true }, { personId: LEADER_A }] },
                    reader: { defaults: [] },
                }),
            });
            requirementsOf(scheduleId).then((rows) => {
                expect(rows[position.lead].defaults.map((d) => [d.personId, d.accepted])).to.deep.eq([
                    [LEADER_B, true],
                    // Named again without `accepted`: the stored flag is kept.
                    [LEADER_A, true],
                ]);
                expect(rows[position.reader].defaults).to.deep.eq([]);
            });

            // An unchecked position takes its defaults with it.
            api("POST", `${URL}/schedules/${scheduleId}`, { requirements: needs().slice(1) });
            requirementsOf(scheduleId).then((rows) => {
                expect(rows[position.lead]).to.eq(undefined);
            });
            dbOk(
                `SELECT COUNT(*) AS n FROM volunteer_requirement_default_vrdf vrdf
                   LEFT JOIN volunteer_requirement_vreq vreq ON vreq.vreq_ID = vrdf.vrdf_vreq_ID
                  WHERE vreq.vreq_ID IS NULL`,
            ).then((rows) => expect(Number(rows[0].n), "no orphaned default rows").to.eq(0));
        });

        it("refuses more defaults than Max, a person twice and the removed single-default fields, and changes nothing", () => {
            api("POST", `${URL}/schedules/${scheduleId}`, {
                requirements: needs({ lead: { defaults: [{ personId: LEADER_A }, { personId: LEADER_B }] } }),
            });

            api(
                "POST",
                `${URL}/schedules/${scheduleId}`,
                { requirements: needs({ lead: { maxCount: 1, defaults: [{ personId: LEADER_A }, { personId: LEADER_B }] } }) },
                400,
            ).then((resp) => {
                expect(resp.body.message).to.contain("Max is 1 but 2 default volunteers are chosen. Remove one or raise Max.");
            });
            // Lowering Max below the stored defaults without naming them is refused too.
            api("POST", `${URL}/schedules/${scheduleId}`, { requirements: needs({ lead: { maxCount: 1 } }) }, 400);
            api("POST", `${URL}/schedules/${scheduleId}/requirements`, { positionId: position.lead, minCount: 1, maxCount: 1 }, 400);
            // A blank Max is Min's room (§2.10).
            api(
                "POST",
                `${URL}/schedules/${scheduleId}`,
                { requirements: needs({ lead: { maxCount: null, defaults: [{ personId: LEADER_A }, { personId: LEADER_B }] } }) },
                400,
            );
            api(
                "POST",
                `${URL}/schedules/${scheduleId}`,
                { requirements: needs({ lead: { defaults: [{ personId: LEADER_A }, { personId: LEADER_A }] } }) },
                400,
            ).then((resp) => {
                expect(resp.body.message).to.contain("the same person is chosen twice");
            });
            api("POST", `${URL}/schedules/${scheduleId}`, { requirements: needs({ reader: { defaultPersonId: READER } }) }, 400);
            api("POST", `${URL}/schedules/${scheduleId}`, { requirements: needs({ lead: { defaults: { personId: LEADER_A } } }) }, 400);

            requirementsOf(scheduleId).then((rows) => {
                expect(rows[position.lead]).to.include({ maxCount: 2 });
                expect(people(rows[position.lead])).to.deep.eq([LEADER_A, LEADER_B]);
            });

            createSchedule(
                "Too Many",
                "Service",
                {
                    requirements: [
                        {
                            positionId: position.lead,
                            minCount: 1,
                            maxCount: 1,
                            defaults: [{ personId: LEADER_A }, { personId: LEADER_B }],
                        },
                    ],
                },
                400,
            );
            dbOk("SELECT COUNT(*) AS n FROM volunteer_schedule_vsch WHERE vsch_Name = ?", [`${PREFIX} Too Many`]).then((rows) => {
                expect(Number(rows[0].n), "no half-made schedule").to.eq(0);
            });
        });

        it("refuses a new default who is not qualified or does not exist, and changes nothing", () => {
            api("POST", `${URL}/schedules/${scheduleId}`, { requirements: needs({ lead: { defaults: [{ personId: LEADER_A }] } }) });
            api(
                "POST",
                `${URL}/schedules/${scheduleId}`,
                {
                    name: `${PREFIX} Renamed`,
                    requirements: needs({ lead: { defaults: [{ personId: LEADER_A }, { personId: NOT_QUALIFIED }] } }),
                },
                403,
            );
            api("POST", `${URL}/schedules/${scheduleId}`, { requirements: needs({ lead: { defaults: [{ personId: 999999 }] } }) }, 404);
            requirementsOf(scheduleId).then((rows) => {
                expect(people(rows[position.lead])).to.deep.eq([LEADER_A]);
            });
            api("GET", `${URL}/schedules/${scheduleId}`).its("body.schedule.name").should("eq", `${PREFIX} Dialog`);

            createSchedule("Refused", "Service", { requirements: needs({ reader: { defaults: [{ personId: LEADER_A }] } }) }, 403);
            dbOk("SELECT COUNT(*) AS n FROM volunteer_schedule_vsch WHERE vsch_Name = ?", [`${PREFIX} Refused`]).then((rows) => {
                expect(Number(rows[0].n), "no half-made schedule").to.eq(0);
            });
        });

        it("keeps a stored default whose qualification was revoked, and says it no longer qualifies", () => {
            dbOk("UPDATE volunteer_qualification_vqal SET vqal_Active = 0 WHERE vqal_per_ID = ? AND vqal_vpos_ID = ?", [
                LEADER_A,
                position.lead,
            ]);
            // Saving the unchanged default again is not a new choice, so it is not re-checked.
            api("POST", `${URL}/schedules/${scheduleId}`, {
                requirements: needs({ lead: { defaults: [{ personId: LEADER_A, accepted: true }, { personId: LEADER_B }] } }),
            });
            requirementsOf(scheduleId).then((rows) => {
                expect(rows[position.lead].defaults[0]).to.include({ personId: LEADER_A, accepted: true, qualified: false });
                expect(rows[position.lead].defaults[1]).to.include({ personId: LEADER_B, qualified: true });
            });
            dbOk("UPDATE volunteer_qualification_vqal SET vqal_Active = 1 WHERE vqal_per_ID = ? AND vqal_vpos_ID = ?", [
                LEADER_A,
                position.lead,
            ]);
        });

        it("refuses a default on one occurrence's own needs: defaults belong to the schedule", () => {
            dbOk("SELECT vocc_ID AS id FROM volunteer_occurrence_vocc WHERE vocc_vsch_ID = ? LIMIT 1", [scheduleId]).then((rows) => {
                api(
                    "POST",
                    `${URL}/occurrences/${rows[0].id}/requirements/replace`,
                    { requirements: [{ positionId: position.lead, minCount: 2, maxCount: 2, defaults: [{ personId: LEADER_A }] }] },
                    400,
                );
            });
        });
    });

    // ── the Generate dialog ────────────────────────────────────────────────────

    describe("Volunteer v2 D32 — Generate saves the dialog's defaults and assigns the saved ones", () => {
        let scheduleId = 0;
        let plainId = 0;

        before(() => {
            createPausedThenActive("Generate", "Service", {
                windowStart: isoDate(1),
                windowEnd: isoDate(20),
                requirements: [
                    { positionId: position.lead, minCount: 1, maxCount: 2 },
                    { positionId: position.reader, minCount: 1, maxCount: 1, defaults: [{ personId: READER }] },
                ],
            }).then((id) => {
                scheduleId = id;
            });
            createPausedThenActive("Saved Only", "Service", {
                windowStart: isoDate(1),
                windowEnd: isoDate(20),
                requirements: [{ positionId: position.lead, minCount: 1, maxCount: 1, defaults: [{ personId: LEADER_B }] }],
            }).then((id) => {
                plainId = id;
            });
        });

        it("refuses a bad set of defaults before generating anything", () => {
            generate(scheduleId, { requirements: [{ positionId: position.usher, defaults: [{ personId: USHER }] }] }, 400);
            generate(
                scheduleId,
                { requirements: [{ positionId: position.reader, defaults: [{ personId: READER }, { personId: LEADER_A }] }] },
                400,
            );
            generate(
                scheduleId,
                { requirements: [{ positionId: position.lead, defaults: [{ personId: LEADER_A }, { personId: LEADER_A }] }] },
                400,
            );
            generate(scheduleId, { requirements: [{ positionId: position.lead, defaults: [{ personId: NOT_QUALIFIED }] }] }, 403);
            // The single-default shape before D35 is refused, never half-read.
            generate(scheduleId, { defaults: [{ positionId: position.lead, personId: LEADER_A }] }, 400);
            dbOk("SELECT COUNT(*) AS n FROM volunteer_occurrence_vocc WHERE vocc_vsch_ID = ?", [scheduleId]).then((rows) => {
                expect(Number(rows[0].n)).to.eq(0);
            });
            requirementsOf(scheduleId).then((rows) => {
                expect(people(rows[position.reader]), "the saved defaults are untouched").to.deep.eq([READER]);
                expect(rows[position.lead].defaults).to.deep.eq([]);
            });
        });

        it("saves the dialog's choices on the schedule — an empty list as none — and staffs the new occurrences with both", () => {
            generate(scheduleId, {
                requirements: [
                    {
                        positionId: position.lead,
                        defaults: [
                            { personId: LEADER_A, accepted: true },
                            { personId: LEADER_B, accepted: false },
                        ],
                    },
                    { positionId: position.reader, defaults: [] },
                ],
            }).then((result) => {
                expect(result.created).to.be.greaterThan(0);
                expect(result.assigned).to.eq(result.created * 2);
                expect(result.skipped).to.eq(0);
                expect(result.unqualified).to.eq(0);
            });
            requirementsOf(scheduleId).then((rows) => {
                expect(rows[position.lead].defaults.map((d) => [d.personId, d.accepted])).to.deep.eq([
                    [LEADER_A, true],
                    [LEADER_B, false],
                ]);
                expect(rows[position.reader].defaults).to.deep.eq([]);
            });
            storedDefaults(scheduleId, position.lead).then((rows) => {
                expect(rows.map((row) => row.setBy)).to.deep.eq([ADMIN_PERSON, ADMIN_PERSON]);
            });
            staffingOf(scheduleId).then((occurrences) => {
                for (const occurrence of occurrences) {
                    const byPerson = Object.fromEntries(occurrence.assignments.map((a) => [a.personId, a]));
                    expect(occurrence.assignments, occurrence.day).to.have.length(2);
                    expect(byPerson[LEADER_A], occurrence.day).to.include({
                        positionId: position.lead,
                        status: "accepted",
                        coordinatorAccepts: 1,
                        asks: 0,
                    });
                    expect(byPerson[LEADER_B], occurrence.day).to.include({
                        positionId: position.lead,
                        status: "pending",
                        coordinatorAccepts: 0,
                        asks: 1,
                    });
                }
            });
        });

        it("assigns the schedule's saved defaults on a run that names none, asking a pending one to respond", () => {
            generate(plainId).then((result) => {
                expect(result.created).to.be.greaterThan(0);
                expect(result.assigned).to.eq(result.created);
            });
            staffingOf(plainId).then((occurrences) => {
                for (const occurrence of occurrences) {
                    expect(occurrence.assignments, occurrence.day).to.have.length(1);
                    expect(occurrence.assignments[0]).to.include({
                        personId: LEADER_B,
                        status: "pending",
                        source: "coordinator",
                        assignedBy: ADMIN_PERSON,
                        coordinatorAccepts: 0,
                        asks: 1,
                    });
                }
            });
        });
    });

    // ── the daily top-up ───────────────────────────────────────────────────────

    describe("Volunteer v2 D32 — the daily top-up assigns each position's saved default", () => {
        let scheduleId = 0;
        let rehearsals = [];
        let pastEventId = 0;

        before(() => {
            setConfig(RATE_LIMIT, "0");
            createEvents(weekly("Rehearsal", 1, 90)).then((resp) => {
                rehearsals = resp.body.events;
            });
            cy.then(() => {
                // Over already, today: generation makes its occurrence, the default is refused.
                dbOk(
                    `INSERT INTO events_event (event_type, event_title, event_desc, event_text, event_start, event_end, inactive, event_ministry_id)
                 VALUES (?, ?, '', '', ?, ?, 0, ?)`,
                    [CHURCH_SERVICE_TYPE, `${PREFIX} Rehearsal`, `${serverToday()} 00:00:00`, `${serverToday()} 00:01:00`, ministryId],
                ).then((rows) => {
                    pastEventId = rows.insertId;
                });
                createPausedThenActive("Rehearsal Crew", "Rehearsal", {
                    requirements: [
                        { positionId: position.lead, minCount: 1, maxCount: 1, defaults: [{ personId: LEADER_A, accepted: true }] },
                        { positionId: position.reader, minCount: 1, maxCount: 1, defaults: [{ personId: READER, accepted: false }] },
                        { positionId: position.usher, minCount: 0, maxCount: 0 },
                    ],
                }).then((id) => {
                    scheduleId = id;
                });
            });
            cy.then(() => {
                dbOk("DELETE FROM config_cfg WHERE cfg_name IN (?, ?)", [TOP_UP_DATE, TOP_UP_RESULT]);
            });
        });

        it("assigns a still-qualified default on every occurrence it creates: accepted without asking, pending with the please-respond email", () => {
            runTimerJobs(false);
            staffingOf(scheduleId).then((occurrences) => {
                const upcoming = rehearsals.filter((event) => event.start.slice(0, 10) <= isoDate(HORIZON_DAYS));
                expect(occurrences.map((o) => o.eventId)).to.have.members([pastEventId, ...upcoming.map((e) => e.id)]);

                const past = occurrences.find((o) => o.eventId === pastEventId);
                expect(past.assignments, "an occurrence that is over takes nobody").to.have.length(0);

                for (const occurrence of occurrences.filter((o) => o.eventId !== pastEventId)) {
                    const byPosition = Object.fromEntries(occurrence.assignments.map((a) => [a.positionId, a]));
                    expect(byPosition[position.lead], occurrence.day).to.include({
                        personId: LEADER_A,
                        status: "accepted",
                        source: "coordinator",
                        assignedBy: ADMIN_PERSON,
                        coordinatorAccepts: 1,
                        asks: 0,
                    });
                    expect(byPosition[position.reader], occurrence.day).to.include({
                        personId: READER,
                        status: "pending",
                        assignedBy: ADMIN_PERSON,
                        coordinatorAccepts: 0,
                        asks: 1,
                    });
                    expect(byPosition[position.usher]).to.eq(undefined);
                }
            });
            lastTopUp().then((result) => {
                const upcoming = rehearsals.filter((event) => event.start.slice(0, 10) <= isoDate(HORIZON_DAYS)).length;
                expect(result.assigned).to.be.at.least(upcoming * 2);
                // Today's (over) occurrence refuses both.
                expect(result.skipped).to.be.at.least(2);
                expect(result.unqualified).to.be.a("number");
            });
        });

        it("skips a default whose qualification was revoked, leaves the gap, counts it and keeps the default", () => {
            let newEventId = 0;
            dbOk("SELECT vqal_ID AS id FROM volunteer_qualification_vqal WHERE vqal_per_ID = ? AND vqal_vpos_ID = ?", [
                READER,
                position.reader,
            ]).then((rows) => {
                api("DELETE", `${URL}/qualifications/${rows[0].id}`);
            });
            createEvents(oneEvent("Rehearsal", 2)).then((resp) => {
                newEventId = resp.body.events[0].id;
            });
            cy.then(() => {
                runTimerJobs(true);
            });
            staffingOf(scheduleId).then((occurrences) => {
                const added = occurrences.find((o) => o.eventId === newEventId);
                expect(added, "the new event got its occurrence").to.not.eq(undefined);
                expect(added.assignments.map((a) => a.positionId), "the Reader is left open").to.deep.eq([position.lead]);
                expect(added.assignments[0]).to.include({ personId: LEADER_A, status: "accepted" });
                // Existing assignments of the revoked reader stay as they were (§2.7).
                const earlier = occurrences.filter((o) => o.eventId !== newEventId && o.eventId !== pastEventId);
                expect(earlier.every((o) => o.assignments.some((a) => a.personId === READER))).to.eq(true);
            });
            lastTopUp().then((result) => {
                expect(result.unqualified).to.be.at.least(1);
            });
            requirementsOf(scheduleId).then((rows) => {
                expect(rows[position.reader].defaults[0]).to.include({ personId: READER, qualified: false });
            });
        });

        it("never reassigns existing occurrences when a default changes: only occurrences made from then on get the new one", () => {
            let laterEventId = 0;
            api("POST", `${URL}/schedules/${scheduleId}`, {
                requirements: [
                    { positionId: position.lead, minCount: 1, maxCount: 1, defaults: [{ personId: LEADER_B, accepted: false }] },
                    { positionId: position.reader, minCount: 1, maxCount: 1 },
                    { positionId: position.usher, minCount: 0, maxCount: 0 },
                ],
            });
            createEvents(oneEvent("Rehearsal", 3)).then((resp) => {
                laterEventId = resp.body.events[0].id;
            });
            cy.then(() => {
                runTimerJobs(true);
            });
            staffingOf(scheduleId).then((occurrences) => {
                const later = occurrences.find((o) => o.eventId === laterEventId);
                const leads = later.assignments.filter((a) => a.positionId === position.lead);
                expect(leads).to.have.length(1);
                expect(leads[0]).to.include({ personId: LEADER_B, status: "pending", asks: 1 });

                for (const occurrence of occurrences.filter((o) => o.eventId !== laterEventId && o.eventId !== pastEventId)) {
                    const lead = occurrence.assignments.filter((a) => a.positionId === position.lead);
                    expect(lead.map((a) => a.personId), occurrence.day).to.deep.eq([LEADER_A]);
                }
            });
        });
    });

    // ── Save and Staff them (D33) ──────────────────────────────────────────────

    describe("Volunteer v2 D32 — a new schedule's Save and Staff them assign the saved defaults (D33)", () => {
        let scheduleId = 0;

        it("assigns the defaults a new schedule is saved with on the occurrences its Save makes, up to the horizon", () => {
            createEvents(weekly("Workday", 1, 90)).then((made) => {
                const within = made.body.events.filter((event) => event.start.slice(0, 10) <= isoDate(HORIZON_DAYS));
                createSchedule("Workday Crew", "Workday", {
                    requirements: [{ positionId: position.lead, minCount: 1, maxCount: 1, defaults: [{ personId: LEADER_A, accepted: true }] }],
                }).then((resp) => {
                    scheduleId = resp.body.schedule.id;
                    expect(resp.body.generated).to.include({ created: within.length, assigned: within.length, skipped: 0, unqualified: 0 });
                    staffingOf(scheduleId).then((occurrences) => {
                        expect(occurrences.map((o) => o.eventId)).to.have.members(within.map((e) => e.id));
                        for (const occurrence of occurrences) {
                            expect(occurrence.assignments[0], occurrence.day).to.include({
                                personId: LEADER_A,
                                status: "accepted",
                                coordinatorAccepts: 1,
                                asks: 0,
                            });
                        }
                    });
                });
            });
        });

        it("assigns the saved defaults of the schedule a new series goes to, on its new occurrences", () => {
            api("POST", `${URL}/schedules/${scheduleId}`, {
                requirements: [{ positionId: position.lead, minCount: 1, maxCount: 1, defaults: [{ personId: LEADER_B, accepted: false }] }],
            });
            createEvents(weekly("Workday", 3, 24, { startTime: "13:00", endTime: "15:00" })).then((made) => {
                const eventIds = made.body.events.map((event) => event.id);
                api("POST", `${URL}/ministries/${ministryId}/events/staff`, { eventIds }).then((resp) => {
                    expect(resp.body.schedules.map((run) => run.schedule.id)).to.deep.eq([scheduleId]);
                    expect(resp.body.schedules[0]).to.include({ created: eventIds.length, assigned: eventIds.length });
                });
                staffingOf(scheduleId).then((occurrences) => {
                    for (const occurrence of occurrences.filter((o) => eventIds.includes(o.eventId))) {
                        expect(occurrence.assignments[0], occurrence.day).to.include({ personId: LEADER_B, status: "pending", asks: 1 });
                    }
                    for (const occurrence of occurrences.filter((o) => !eventIds.includes(o.eventId))) {
                        expect(occurrence.assignments[0].personId, "older occurrences keep their volunteer").to.eq(LEADER_A);
                    }
                });
            });
        });
    });

    // ── Remove Volunteer ───────────────────────────────────────────────────────

    describe("Volunteer v2 D32 — Remove Volunteer clears the person's defaults on the ministry's schedules", () => {
        let removalId = 0;
        let pausedId = 0;
        const other = {};

        function defaultCount(id, personId) {
            return dbOk(
                `SELECT COUNT(*) AS n FROM volunteer_requirement_default_vrdf vrdf
                   JOIN volunteer_requirement_vreq vreq ON vreq.vreq_ID = vrdf.vrdf_vreq_ID
                   JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
                  WHERE vsch.vsch_vmin_ID = ? AND vrdf.vrdf_per_ID = ?`,
                [id, personId],
            ).then((rows) => Number(rows[0].n));
        }

        before(() => {
            createSchedule("Removal", "Service", {
                requirements: [
                    {
                        positionId: position.lead,
                        minCount: 1,
                        maxCount: 2,
                        defaults: [
                            { personId: LEADER_B, accepted: true },
                            { personId: LEADER_A, accepted: false },
                        ],
                    },
                    { positionId: position.usher, minCount: 0, maxCount: 1, defaults: [{ personId: USHER }] },
                ],
            }).then((resp) => {
                removalId = resp.body.schedule.id;
            });
            createSchedule("Removal Paused", "Service", {
                active: false,
                requirements: [{ positionId: position.lead, minCount: 1, maxCount: 1, defaults: [{ personId: LEADER_B }] }],
            }).then((resp) => {
                pausedId = resp.body.schedule.id;
            });

            api("POST", `${URL}/ministries`, { name: `${PREFIX} Hospitality`, description: "D32 removal fixture" }, 201).then((resp) => {
                other.ministryId = resp.body.ministry.id;
                api("GET", `${URL}/ministries/${other.ministryId}`).then((detail) => {
                    other.teamId = detail.body.teams[0].id;
                });
            });
            cy.then(() => {
                api("POST", `${URL}/ministries/${other.ministryId}/positions`, { name: `${PREFIX} Greeter`, teamId: other.teamId }, 201).then(
                    (resp) => {
                        other.positionId = resp.body.position.id;
                    },
                );
            });
            cy.then(() => {
                api("POST", `${URL}/positions/${other.positionId}/qualifications`, { personId: LEADER_B }, [200, 201]);
                api("POST", `${URL}/ministries/${other.ministryId}/events`, weekly("Greeting", 1, 30), 201);
            });
            cy.then(() => {
                api(
                    "POST",
                    `${URL}/ministries/${other.ministryId}/schedules`,
                    {
                        name: `${PREFIX} Greeters`,
                        teamId: other.teamId,
                        linkMode: "ministry",
                        titleFilter: `${PREFIX} Greeting`,
                        windowStart: isoDate(0),
                        requirements: [
                            { positionId: other.positionId, minCount: 1, maxCount: 1, defaults: [{ personId: LEADER_B, accepted: true }] },
                        ],
                    },
                    201,
                ).then((resp) => {
                    other.scheduleId = resp.body.schedule.id;
                });
            });
        });

        it("keeps the default when only one qualification is revoked", () => {
            dbOk("SELECT vqal_ID AS id FROM volunteer_qualification_vqal WHERE vqal_per_ID = ? AND vqal_vpos_ID = ?", [
                LEADER_B,
                position.lead,
            ]).then((rows) => {
                api("DELETE", `${URL}/qualifications/${rows[0].id}`);
            });
            requirementsOf(removalId).then((rows) => {
                expect(rows[position.lead].defaults[0]).to.include({ personId: LEADER_B, accepted: true, qualified: false });
            });
            qualify("lead", LEADER_B);
        });

        it("deletes their defaults on every schedule of the ministry, keeps the needs and the other defaults, and leaves other ministries alone", () => {
            let stored = 0;
            let reported;
            defaultCount(ministryId, LEADER_B).then((n) => {
                stored = n;
                expect(stored, "the two fixture schedules at least").to.be.at.least(2);
            });
            cy.then(() => {
                api("DELETE", `${URL}/ministries/${ministryId}/volunteers/${LEADER_B}`).then((resp) => {
                    reported = resp.body.defaults;
                });
            });
            defaultCount(ministryId, LEADER_B).then((left) => expect(left, "their defaults left in this ministry").to.eq(0));

            dbOk(
                `SELECT vreq_vsch_ID AS scheduleId, vreq_MinCount AS minCount
                   FROM volunteer_requirement_vreq WHERE vreq_vsch_ID IN (?, ?) AND vreq_vpos_ID = ?`,
                [removalId, pausedId, position.lead],
            ).then((rows) => {
                expect(rows.map((row) => Number(row.scheduleId)), "the staffing need stays, inactive schedule too").to.have.members([
                    removalId,
                    pausedId,
                ]);
                expect(rows.every((row) => Number(row.minCount) === 1)).to.eq(true);
            });

            requirementsOf(removalId).then((rows) => {
                expect(people(rows[position.lead]), "the other default on the same need").to.deep.eq([LEADER_A]);
                expect(people(rows[position.usher]), "someone else's default").to.deep.eq([USHER]);
            });
            requirementsOf(pausedId).then((rows) => {
                expect(rows[position.lead].defaults).to.deep.eq([]);
            });
            requirementsOf(other.scheduleId).then((rows) => {
                expect(rows[other.positionId].defaults[0], "another ministry's schedule").to.include({
                    personId: LEADER_B,
                    accepted: true,
                    qualified: true,
                });
            });
            staffingOf(other.scheduleId).then((occurrences) => {
                const assignments = occurrences.flatMap((occurrence) => occurrence.assignments);
                expect(assignments).to.not.be.empty;
                expect(assignments.every((a) => a.personId === LEADER_B && a.status === "accepted")).to.eq(true);
            });
            cy.then(() => expect(reported, "the response counts the cleared defaults").to.eq(stored));
        });

        it("reports no defaults on a second removal", () => {
            api("DELETE", `${URL}/ministries/${ministryId}/volunteers/${LEADER_B}`).then((resp) => {
                expect(resp.body.defaults).to.eq(0);
            });
        });
    });

    // ── Ministry Settings ──────────────────────────────────────────────────────

    describe("Volunteer v2 D32 — the server refuses a blank number setting", () => {
        it("refuses a blank or non-numeric reminder lead time and keeps the stored value; 0 still means no reminders", () => {
            setConfig(LEAD, "24");
            setConfig(LEAD, "", 400);
            setConfig(LEAD, "  ", 400);
            setConfig(LEAD, "soon", 400);
            getConfig(LEAD).should("eq", "24");
            setConfig(LEAD, "0");
            getConfig(LEAD).should("eq", "0");
        });

        it("refuses a blank scheduling horizon, which keeps its value; a blank default event type still means none", () => {
            setConfig(HORIZON, "6");
            setConfig(HORIZON, "", 400);
            getConfig(HORIZON).should("eq", "6");
            setConfig(DEFAULT_TYPE, "");
            getConfig(DEFAULT_TYPE).should("eq", "");
            setConfig(HORIZON, "8");
        });
    });
});
