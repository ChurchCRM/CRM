/// <reference types="cypress" />

/**
 * Volunteer v2 D30 — a Generate run that finds no events says what it looked for, and "Staff these
 * events" adds new events to the team's schedule that already follows them instead of making a
 * second one. Design §0.8 D30, §2.8, §3.3.2.
 *
 * Personas (seed.sql): `admin.api.key` (administrator). The ministry comes from the real create
 * API with the Sunday School switch on, so its schedules may follow a class.
 *
 * Since D31 a schedule is only created while an upcoming event matches, so every empty run here
 * is one whose events lie beyond the dates it looks at — the scheduling horizon, a `through`, or
 * a window that has ended.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const POOL_MEMBER = 8;
const CHURCH_SERVICE_TYPE = 1;
const SUNDAY_SCHOOL_GROUP_TYPE = 4;
/** The default scheduling horizon, 8 weeks (D31): no run looks further ahead. */
const HORIZON_DAYS = 56;

const PREFIX = "VGEN30";
const URL = "/api/ministries";
const WEEKDAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

let originalVersion = "v1";
let ministryId = 0;
let ministryName = "";
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
    return cy.makePrivateAPICall(Cypress.env(ADMIN_KEY), method, url, body, expectedStatus);
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

function isoDate(offsetDays) {
    const d = localDate(offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
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

function createSchedule(overrides) {
    return api(
        "POST",
        `${URL}/ministries/${ministryId}/schedules`,
        { teamId: teamA, windowStart: isoDate(0), ...overrides, name: `${PREFIX} ${overrides.name}` },
        201,
    ).then((resp) => resp.body.schedule);
}

function generate(scheduleId, body = {}) {
    return api("POST", `${URL}/schedules/${scheduleId}/generate`, body).then((resp) => resp.body);
}

function createEvents(body) {
    return api("POST", `${URL}/ministries/${ministryId}/events`, body, 201).then((resp) => resp.body);
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

/** Every occurrence anchored to the events, whichever schedule made it. */
function occurrencesOn(eventIds) {
    return dbOk(
        `SELECT vocc.vocc_ID AS id, vocc.vocc_event_id AS eventId, vocc.vocc_vsch_ID AS scheduleId
           FROM volunteer_occurrence_vocc vocc WHERE vocc.vocc_event_id IN (${eventIds.map(() => "?").join(",")})`,
        eventIds,
    );
}

function scheduleRow(scheduleId) {
    return dbOk(
        `SELECT DATE_FORMAT(vsch_WindowStart, '%Y-%m-%d') AS windowStart, DATE_FORMAT(vsch_WindowEnd, '%Y-%m-%d') AS windowEnd,
                vsch_StartOffsetMinutes AS startOffset
           FROM volunteer_schedule_vsch WHERE vsch_ID = ?`,
        [scheduleId],
    ).then((rows) => rows[0]);
}

function schedulesFollowing(teamId, groupId) {
    return dbOk(`SELECT vsch_ID AS id FROM volunteer_schedule_vsch WHERE vsch_vtem_ID = ? AND vsch_grp_ID = ?`, [teamId, groupId]).then(
        (rows) => rows.map((r) => Number(r.id)),
    );
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
    for (const table of ["calendar_events", "event_audience"]) {
        dbOk(`DELETE t FROM ${table} t JOIN events_event e ON e.event_id = t.event_id WHERE e.event_title LIKE ?`, like);
    }
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

before(() => {
    cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
        originalVersion = resp.body.value ?? resp.body.data ?? "v1";
    });
    setVersion("v2");
    cleanupFixtures();

    api("POST", `${URL}/ministries`, { name: `${PREFIX} Children`, description: "D30 fixture", sundaySchool: true }, 201).then(
        (resp) => {
            ministryId = resp.body.ministry.id;
            ministryName = resp.body.ministry.name;
            api("GET", `${URL}/ministries/${ministryId}`).then((detail) => {
                teamA = detail.body.teams[0].id;
            });
        },
    );
    for (const key of ["Empty Class", "Meeting Class", "Faith City", "Other Class", "Resting Class"]) {
        makeClass(key);
    }

    cy.then(() => {
        api("POST", `${URL}/ministries/${ministryId}/teams`, { name: `${PREFIX} Team B` }, 201).then((resp) => {
            teamB = resp.body.team.id;
        });
        api("POST", `${URL}/ministries/${ministryId}/positions`, { name: `${PREFIX} Teacher`, teamId: teamA }, 201).then(
            (resp) => {
                position = resp.body.position.id;
                api("POST", `${URL}/positions/${position}/qualifications`, { personId: POOL_MEMBER }, [200, 201]);
            },
        );
    });
});

after(() => {
    cleanupFixtures();
    setVersion(originalVersion);
});

// ── Generate explains an empty run ─────────────────────────────────────────

describe("Volunteer v2 D30 — a Generate run that finds no events says what it looked for", () => {
    it("names the class, and the dates looked at, when its meetings lie beyond the horizon", () => {
        createEvents(oneEvent("Empty Class Later", HORIZON_DAYS + 14, { linkedGroupId: classes["Empty Class"] }));
        createSchedule({ name: "Empty Class", linkMode: "class", groupId: classes["Empty Class"] }).then((schedule) => {
            generate(schedule.id).then((result) => {
                expect(result).to.include({ created: 0, existing: 0, noEvents: true });
                expect(result.from).to.eq(isoDate(0));
                expect(result.through).to.eq(isoDate(HORIZON_DAYS));
                expect(result.searched).to.include({
                    linkMode: "class",
                    groupId: classes["Empty Class"],
                    groupName: `${PREFIX} Empty Class`,
                    ministryId,
                });
            });
        });
    });

    it("names the event type and title in event-type mode", () => {
        createEvents(oneEvent("Later Service", 40));
        createSchedule({
            name: "Nothing Titled",
            linkMode: "event_type",
            eventTypeId: CHURCH_SERVICE_TYPE,
            titleFilter: `${PREFIX} Later Service`,
        }).then((schedule) => {
            generate(schedule.id, { through: isoDate(30) }).then((result) => {
                expect(result).to.include({ created: 0, existing: 0, noEvents: true, through: isoDate(30) });
                expect(result.searched).to.include({
                    linkMode: "event_type",
                    eventTypeId: CHURCH_SERVICE_TYPE,
                    eventTypeName: "Church Service",
                    titleFilter: `${PREFIX} Later Service`,
                    groupId: null,
                });
            });
        });
    });

    it("names the ministry and title in ministry mode", () => {
        createEvents(oneEvent("VBS", HORIZON_DAYS + 14));
        createSchedule({ name: "No VBS", linkMode: "ministry", titleFilter: `${PREFIX} VBS` }).then((schedule) => {
            generate(schedule.id).then((result) => {
                expect(result.noEvents).to.eq(true);
                expect(result.searched).to.include({
                    linkMode: "ministry",
                    ministryId,
                    ministryName,
                    titleFilter: `${PREFIX} VBS`,
                });
            });
        });
    });

    it("reports a window that has already ended as nothing looked at", () => {
        createSchedule({
            name: "Ended",
            linkMode: "class",
            groupId: classes["Empty Class"],
            windowStart: isoDate(-30),
            windowEnd: isoDate(-5),
        }).then((schedule) => {
            generate(schedule.id).then((result) => {
                expect(result.noEvents).to.eq(true);
                expect(result.from).to.eq(isoDate(0));
                expect(result.through).to.eq(isoDate(-5));
            });
        });
    });

    it("is not empty when events were found, even when every occurrence already existed", () => {
        createEvents(weekly("Meeting", 1, 20, { linkedGroupId: classes["Meeting Class"] })).then((made) => {
            createSchedule({ name: "Meeting Class", linkMode: "class", groupId: classes["Meeting Class"] }).then((schedule) => {
                generate(schedule.id).then((first) => {
                    expect(first).to.include({ created: made.events.length, existing: 0, noEvents: false });
                });
                generate(schedule.id).then((again) => {
                    expect(again).to.include({ created: 0, existing: made.events.length, noEvents: false });
                    expect(again.searched.groupName).to.eq(`${PREFIX} Meeting Class`);
                });
            });
        });
    });
});

// ── Staff these events reuses the schedule that already follows them ───────

describe("Volunteer v2 D30 — Staff these events adds the events to the team's schedule that follows them", () => {
    let classSchedule = null;
    let earlierOccurrenceId = 0;

    before(() => {
        createEvents(oneEvent("Faith City Picnic", 12, { linkedGroupId: classes["Faith City"] }));
        createSchedule({
            name: "Faith City Teachers",
            linkMode: "class",
            groupId: classes["Faith City"],
            windowStart: isoDate(10),
            windowEnd: isoDate(20),
            startOffsetMinutes: -15,
            requirements: [{ positionId: position, minCount: 2, maxCount: 2 }],
        }).then((schedule) => {
            classSchedule = schedule;
            generate(schedule.id).then((result) => {
                expect(result.created).to.eq(1);
                dbOk(`SELECT vocc_ID AS id FROM volunteer_occurrence_vocc WHERE vocc_vsch_ID = ?`, [schedule.id]).then((rows) => {
                    earlierOccurrenceId = Number(rows[0].id);
                });
            });
        });
    });

    it("adds a class series to the team's class schedule: no second schedule, one occurrence per event", () => {
        createEvents(
            weekly("Faith City", 1, 45, {
                linkedGroupId: classes["Faith City"],
                staff: {
                    teamId: teamA,
                    requirements: [{ positionId: position, minCount: 1, maxCount: 1 }],
                    startOffsetMinutes: -60,
                    defaults: [{ positionId: position, personId: POOL_MEMBER, accepted: true }],
                },
            }),
        ).then((made) => {
            const eventIds = made.events.map((e) => e.id);
            expect(made.reusedSchedule).to.eq(true);
            expect(made.schedule.id).to.eq(classSchedule.id);
            expect(made.schedule.linkMode).to.eq("class");
            expect(made.occurrences.map((o) => o.eventId)).to.have.members(eventIds);

            schedulesFollowing(teamA, classes["Faith City"]).then((ids) => expect(ids).to.deep.eq([classSchedule.id]));
            occurrencesOn(eventIds).then((rows) => {
                expect(rows, "exactly one occurrence per new event").to.have.length(eventIds.length);
                expect(new Set(rows.map((r) => Number(r.eventId))).size).to.eq(eventIds.length);
                expect(rows.every((r) => Number(r.scheduleId) === classSchedule.id)).to.eq(true);
            });

            scheduleRow(classSchedule.id).then((row) => {
                expect(row.windowStart, "the first date moved back to the first new event").to.eq(isoDate(1));
                expect(row.windowEnd, "the last date moved on to the last new event").to.eq(isoDate(45));
                expect(Number(row.startOffset), "the schedule's own offsets are kept").to.eq(-15);
            });
            api("GET", `${URL}/schedules/${classSchedule.id}/requirements`).then((resp) => {
                expect(resp.body.requirements.map((r) => [r.positionId, r.minCount])).to.deep.eq([[position, 2]]);
            });

            expect(made.assigned).to.eq(eventIds.length);
            dbOk(
                `SELECT vasg.vasg_vocc_ID AS occurrenceId FROM volunteer_assignment_vasg vasg
                   JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
                  WHERE vocc.vocc_vsch_ID = ? AND vasg.vasg_per_ID = ?`,
                [classSchedule.id, POOL_MEMBER],
            ).then((rows) => {
                const assigned = rows.map((r) => Number(r.occurrenceId));
                expect(assigned).to.have.members(made.occurrences.map((o) => o.id));
                expect(assigned, "the default is not put on an occurrence an earlier run made").not.to.include(earlierOccurrenceId);
            });
        });
    });

    it("adds a single class event to the same schedule instead of Staff this event", () => {
        createEvents(oneEvent("Faith City Extra", 50, { linkedGroupId: classes["Faith City"], staff: { teamId: teamA } })).then(
            (made) => {
                const eventId = made.events[0].id;
                expect(made.reusedSchedule).to.eq(true);
                expect(made.schedule.id).to.eq(classSchedule.id);
                expect(made.occurrences.map((o) => o.eventId)).to.deep.eq([eventId]);
                occurrencesOn([eventId]).then((rows) => expect(rows).to.have.length(1));
                dbOk(`SELECT COUNT(*) AS n FROM volunteer_schedule_vsch WHERE vsch_event_id = ?`, [eventId]).then((rows) =>
                    expect(Number(rows[0].n), "no hidden one-event schedule").to.eq(0),
                );
                scheduleRow(classSchedule.id).then((row) => expect(row.windowEnd).to.eq(isoDate(50)));
            },
        );
    });

    it("adds a series with no class to the team's ministry schedule with exactly that title, leaving an open end open", () => {
        createEvents(oneEvent("Workday", 1));
        createSchedule({ name: "Workday Crew", linkMode: "ministry", titleFilter: `${PREFIX} Workday` }).then((schedule) => {
            createEvents(weekly("Workday", 2, 30, { staff: { teamId: teamA } })).then((made) => {
                expect(made.reusedSchedule).to.eq(true);
                expect(made.schedule.id).to.eq(schedule.id);
                occurrencesOn(made.events.map((e) => e.id)).then((rows) => {
                    expect(rows).to.have.length(made.events.length);
                    expect(rows.every((r) => Number(r.scheduleId) === schedule.id)).to.eq(true);
                });
                scheduleRow(schedule.id).then((row) => expect(row.windowEnd).to.eq(null));
            });
        });
    });

    it("makes a new schedule for another team", () => {
        createEvents(weekly("Faith City B", 3, 30, { linkedGroupId: classes["Faith City"], staff: { teamId: teamB } })).then(
            (made) => {
                expect(made.reusedSchedule).to.eq(false);
                expect(made.schedule.id).not.to.eq(classSchedule.id);
                expect(made.schedule).to.include({ teamId: teamB, linkMode: "class", groupId: classes["Faith City"] });
            },
        );
    });

    it("makes a new schedule for another class", () => {
        createEvents(weekly("Other Class", 3, 30, { linkedGroupId: classes["Other Class"], staff: { teamId: teamA } })).then(
            (made) => {
                expect(made.reusedSchedule).to.eq(false);
                expect(made.schedule).to.include({ linkMode: "class", groupId: classes["Other Class"] });
            },
        );
    });

    it("makes a new schedule for another title", () => {
        createEvents(weekly("Mowing", 3, 30, { staff: { teamId: teamA } })).then((made) => {
            expect(made.reusedSchedule).to.eq(false);
            expect(made.schedule).to.include({ linkMode: "ministry", titleFilter: `${PREFIX} Mowing` });
        });
    });

    it("never reuses an inactive schedule", () => {
        createEvents(oneEvent("Resting First", 1, { linkedGroupId: classes["Resting Class"] }));
        createSchedule({ name: "Resting", linkMode: "class", groupId: classes["Resting Class"], active: false }).then((resting) => {
            createEvents(weekly("Resting", 3, 30, { linkedGroupId: classes["Resting Class"], staff: { teamId: teamA } })).then(
                (made) => {
                    expect(made.reusedSchedule).to.eq(false);
                    expect(made.schedule.id).not.to.eq(resting.id);
                    expect(made.schedule.active).to.eq(true);
                },
            );
        });
    });

    it("refuses a single event that has already happened, as Staff this event does, and creates nothing", () => {
        api(
            "POST",
            `${URL}/ministries/${ministryId}/events`,
            oneEvent("Faith City Past", -2, { linkedGroupId: classes["Faith City"], staff: { teamId: teamA } }),
            400,
        );
        dbOk(`SELECT COUNT(*) AS n FROM events_event WHERE event_title = ?`, [`${PREFIX} Faith City Past`]).then((rows) =>
            expect(Number(rows[0].n)).to.eq(0),
        );
    });
});
