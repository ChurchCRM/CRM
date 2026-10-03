/// <reference types="cypress" />

/**
 * Volunteer v2 D33 — events, schedules and occurrences are created separately. Creating a
 * ministry's events never staffs them; "Staff them" sends a new series to every schedule of the
 * ministry that already follows it; a new schedule generates its occurrences on Save. Design
 * §0.8 D33, §2.8, §3.3.2.
 *
 * Personas (seed.sql): `admin.api.key` (administrator); `user.api.key` = person 3, tony.wade,
 * made coordinator of ministry A and team leader in ministry B here; `selfedit.api.key` =
 * person 99, EditSelf-only. Both ministries come from the real create API with the Sunday School
 * switch on, so their events may have a class.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";
const SELFEDIT_KEY = "selfedit.api.key";

const PERSON_COORDINATOR = 3;
const PERSON_SELFEDIT = 99;
const POOL_MEMBER = 8;
const CHURCH_SERVICE_TYPE = 1;
const OTHER_TYPE = 3;
const SUNDAY_SCHOOL_GROUP_TYPE = 4;
/** The default scheduling horizon, 8 weeks (D31). */
const HORIZON_DAYS = 56;

const PREFIX = "VSTAFF33";
const URL = "/api/ministries";
const WEEKDAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

let originalVersion = "v1";
const ministry = {};
const team = {};
const classes = {};
let teacher = 0;
let helper = 0;

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

function createEvents(body, ministryKey = "A", key = ADMIN_KEY, expectedStatus = 201) {
    return api(key, "POST", `${URL}/ministries/${ministry[ministryKey]}/events`, body, expectedStatus).then((resp) => resp.body);
}

function staffThem(eventIds, ministryKey = "A", key = COORDINATOR_KEY, expectedStatus = 200) {
    return api(key, "POST", `${URL}/ministries/${ministry[ministryKey]}/events/staff`, { eventIds }, expectedStatus).then(
        (resp) => resp.body,
    );
}

function createSchedule(fields, ministryKey = "A") {
    return api(
        ADMIN_KEY,
        "POST",
        `${URL}/ministries/${ministry[ministryKey]}/schedules`,
        { teamId: team.A1, windowStart: isoDate(0), ...fields, name: `${PREFIX} ${fields.name}` },
        201,
    ).then((resp) => resp.body);
}

function occurrencesOn(eventIds) {
    return dbOk(
        `SELECT vocc_ID AS id, vocc_event_id AS eventId, vocc_vsch_ID AS scheduleId
           FROM volunteer_occurrence_vocc WHERE vocc_event_id IN (${eventIds.map(() => "?").join(",")})`,
        eventIds,
    );
}

function scheduleRow(scheduleId) {
    return dbOk(
        `SELECT DATE_FORMAT(vsch_WindowStart, '%Y-%m-%d') AS windowStart, DATE_FORMAT(vsch_WindowEnd, '%Y-%m-%d') AS windowEnd
           FROM volunteer_schedule_vsch WHERE vsch_ID = ?`,
        [scheduleId],
    ).then((rows) => rows[0]);
}

function grantScope(personId, scopeType, scopeId) {
    return api(ADMIN_KEY, "POST", `${URL}/scopes`, { personId, scopeType, scopeId }, [200, 201]);
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

describe("Volunteer v2 D33 — events, schedules and occurrences are created separately", () => {
    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
            originalVersion = resp.body.value ?? resp.body.data ?? "v1";
        });
        setVersion("v2");
        cleanupFixtures();

        for (const key of ["A", "B"]) {
            api(ADMIN_KEY, "POST", `${URL}/ministries`, { name: `${PREFIX} Ministry ${key}`, description: "D33 fixture", sundaySchool: true }, 201).then(
                (resp) => {
                    ministry[key] = resp.body.ministry.id;
                    api(ADMIN_KEY, "GET", `${URL}/ministries/${ministry[key]}`).then((detail) => {
                        team[`${key}1`] = detail.body.teams[0].id;
                    });
                },
            );
        }
        for (const key of ["Faith City", "Resting Class", "Generated Class", "Far Class", "Edit Class"]) {
            makeClass(key);
        }

        cy.then(() => {
            api(ADMIN_KEY, "POST", `${URL}/ministries/${ministry.A}/teams`, { name: `${PREFIX} Team A2` }, 201).then((resp) => {
                team.A2 = resp.body.team.id;
                api(ADMIN_KEY, "POST", `${URL}/ministries/${ministry.A}/positions`, { name: `${PREFIX} Helper`, teamId: team.A2 }, 201).then(
                    (created) => {
                        helper = created.body.position.id;
                    },
                );
            });
            api(ADMIN_KEY, "POST", `${URL}/ministries/${ministry.A}/positions`, { name: `${PREFIX} Teacher`, teamId: team.A1 }, 201).then(
                (resp) => {
                    teacher = resp.body.position.id;
                    api(ADMIN_KEY, "POST", `${URL}/positions/${teacher}/qualifications`, { personId: POOL_MEMBER }, [200, 201]);
                },
            );
            grantScope(PERSON_COORDINATOR, "ministry", ministry.A);
            grantScope(PERSON_COORDINATOR, "team", team.B1);
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion(originalVersion);
    });

    // ── creating events never staffs them ──────────────────────────────────────

    describe("Volunteer v2 D33 — creating a ministry's events never staffs them", () => {
        it("refuses a staff key, even an empty one, and creates nothing", () => {
            createEvents(weekly("Staffed Series", 1, 30, { staff: { teamId: team.A1 } }), "A", COORDINATOR_KEY, 400).then((body) => {
                expect(body.message).to.eq("Staffing is set up after the events are created");
            });
            createEvents(oneEvent("Staffed One", 3, { staff: null }), "A", COORDINATOR_KEY, 400);
            dbOk(`SELECT COUNT(*) AS n FROM events_event WHERE event_title LIKE ?`, [`${PREFIX} Staffed%`]).then((rows) =>
                expect(Number(rows[0].n)).to.eq(0),
            );
            dbOk(
                `SELECT COUNT(*) AS n FROM volunteer_schedule_vsch WHERE vsch_vmin_ID = ?`,
                [ministry.A],
            ).then((rows) => expect(Number(rows[0].n), "no schedule either").to.eq(0));
        });

        it("needs a last date for a series", () => {
            const { rangeEnd, ...open } = weekly("Open Ended", 1, 30);
            expect(rangeEnd).to.be.a("string");
            createEvents(open, "A", COORDINATOR_KEY, 400).then((body) => expect(body.message).to.eq("Choose the last date"));
            createEvents({ ...open, rangeEnd: "" }, "A", COORDINATOR_KEY, 400);
            dbOk(`SELECT COUNT(*) AS n FROM events_event WHERE event_title = ?`, [`${PREFIX} Open Ended`]).then((rows) =>
                expect(Number(rows[0].n)).to.eq(0),
            );
        });

        it("makes a series reaching past the scheduling horizon whole: only the repeat engine's cap applies", () => {
            createEvents(weekly("Long Series", 1, 300), "A", COORDINATOR_KEY).then((body) => {
                expect(body).to.have.all.keys("events");
                expect(body.events.length).to.be.within(42, 44);
                expect(body.events[body.events.length - 1].start.slice(0, 10) > isoDate(HORIZON_DAYS)).to.eq(true);
            });
        });
    });

    // ── a new schedule generates on Save ───────────────────────────────────────

    describe("Volunteer v2 D33 — a new schedule generates its occurrences on Save", () => {
        it("generates up to the horizon and assigns the defaults its staffing needs carry", () => {
            createEvents(weekly("Generated", 2, 120, { linkedGroupId: classes["Generated Class"] })).then((made) => {
                const withinHorizon = made.events.filter((e) => e.start.slice(0, 10) <= isoDate(HORIZON_DAYS));
                createSchedule({
                    name: "Generated Teachers",
                    linkMode: "class",
                    groupId: classes["Generated Class"],
                    requirements: [{ positionId: teacher, minCount: 1, maxCount: 1, defaults: [{ personId: POOL_MEMBER, accepted: true }] }],
                }).then((body) => {
                    expect(body.generated).to.include({
                        created: withinHorizon.length,
                        existing: 0,
                        assigned: withinHorizon.length,
                        skipped: 0,
                        unqualified: 0,
                        noEvents: false,
                        from: isoDate(0),
                        through: isoDate(HORIZON_DAYS),
                    });
                    expect(body.generated.searched).to.include({ linkMode: "class", groupId: classes["Generated Class"] });
                    expect(body.schedule.occurrenceCount).to.eq(withinHorizon.length);

                    occurrencesOn(made.events.map((e) => e.id)).then((rows) => {
                        expect(rows.map((r) => Number(r.eventId))).to.have.members(withinHorizon.map((e) => e.id));
                    });
                    dbOk(
                        `SELECT vasg.vasg_Status AS status FROM volunteer_assignment_vasg vasg
                           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
                          WHERE vocc.vocc_vsch_ID = ? AND vasg.vasg_per_ID = ?`,
                        [body.schedule.id, POOL_MEMBER],
                    ).then((rows) => {
                        expect(rows).to.have.length(withinHorizon.length);
                        expect(rows.every((r) => r.status === "accepted")).to.eq(true);
                    });
                });
            });
        });

        it("says what it looked for when none of its events is within reach yet", () => {
            createEvents(oneEvent("Far Meeting", HORIZON_DAYS + 20, { linkedGroupId: classes["Far Class"] }));
            createSchedule({ name: "Far Teachers", linkMode: "class", groupId: classes["Far Class"] }).then((body) => {
                expect(body.generated).to.include({ created: 0, existing: 0, noEvents: true, through: isoDate(HORIZON_DAYS) });
                expect(body.generated.searched).to.include({ linkMode: "class", groupName: `${PREFIX} Far Class` });
                expect(body.schedule.occurrenceCount).to.eq(0);
            });
        });

        it("does not generate when a schedule is edited", () => {
            createEvents(oneEvent("Edit Meeting", 4, { linkedGroupId: classes["Edit Class"] }));
            createSchedule({ name: "Edit Teachers", linkMode: "class", groupId: classes["Edit Class"] }).then((body) => {
                expect(body.generated.created).to.eq(1);
                createEvents(oneEvent("Edit Meeting", 11, { linkedGroupId: classes["Edit Class"] }));
                api(ADMIN_KEY, "POST", `${URL}/schedules/${body.schedule.id}`, { name: `${PREFIX} Edit Teachers Renamed` }).then((resp) => {
                    expect(resp.body.schedule.occurrenceCount, "the second meeting waits for Generate").to.eq(1);
                });
                api(ADMIN_KEY, "POST", `${URL}/schedules/${body.schedule.id}/generate`, {}).then((resp) => {
                    expect(resp.body).to.include({ created: 1, existing: 1 });
                });
            });
        });
    });

    // ── Staff them ─────────────────────────────────────────────────────────────

    describe("Volunteer v2 D33 — Staff them sends a new series to every schedule that follows it", () => {
        const schedule = {};
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
                requirements: [{ positionId: teacher, minCount: 2, maxCount: 2, defaults: [{ personId: POOL_MEMBER, accepted: true }] }],
            }).then((body) => {
                schedule.classA1 = body.schedule.id;
                expect(body.generated.created).to.eq(1);
                dbOk(`SELECT vocc_ID AS id FROM volunteer_occurrence_vocc WHERE vocc_vsch_ID = ?`, [body.schedule.id]).then((rows) => {
                    earlierOccurrenceId = Number(rows[0].id);
                });
            });
            cy.then(() =>
                createSchedule({
                    name: "Faith City Helpers",
                    teamId: team.A2,
                    linkMode: "class",
                    groupId: classes["Faith City"],
                    requirements: [{ positionId: helper, minCount: 1, maxCount: 1 }],
                }).then((body) => {
                    schedule.classA2 = body.schedule.id;
                }),
            );

            createEvents(oneEvent("Workday", 1));
            cy.then(() =>
                createSchedule({ name: "Workday Crew", linkMode: "ministry", titleFilter: `${PREFIX} Workday` }).then((body) => {
                    schedule.ministry = body.schedule.id;
                }),
            );

            createEvents(oneEvent("Evening Service", 2));
            cy.then(() => {
                createSchedule({
                    name: "Evening Ushers",
                    linkMode: "event_type",
                    eventTypeId: CHURCH_SERVICE_TYPE,
                    titleFilter: `${PREFIX} Evening Service`,
                }).then((body) => {
                    schedule.eventType = body.schedule.id;
                });
                createSchedule(
                    {
                        name: "Other Ministry Ushers",
                        teamId: team.B1,
                        linkMode: "event_type",
                        eventTypeId: CHURCH_SERVICE_TYPE,
                        titleFilter: `${PREFIX} Evening Service`,
                    },
                    "B",
                ).then((body) => {
                    schedule.otherMinistry = body.schedule.id;
                });
            });

            createEvents(oneEvent("Resting Meeting", 1, { linkedGroupId: classes["Resting Class"] }));
            cy.then(() =>
                createSchedule({ name: "Resting", linkMode: "class", groupId: classes["Resting Class"], active: false }).then((body) => {
                    schedule.inactive = body.schedule.id;
                }),
            );

            createEvents(oneEvent("Picnic", 3)).then((made) => {
                api(ADMIN_KEY, "POST", `${URL}/ministries/${ministry.A}/staffed-events`, { eventId: made.events[0].id, teamId: team.A1 }, 201);
            });
        });

        it("sends a class series to every schedule on its class, widens their dates, and assigns the saved defaults on the new occurrences only", () => {
            createEvents(weekly("Faith City", 1, 45, { linkedGroupId: classes["Faith City"] })).then((made) => {
                const eventIds = made.events.map((e) => e.id);
                const first = made.events[0].start.slice(0, 10);
                const last = made.events[made.events.length - 1].start.slice(0, 10);

                staffThem(eventIds).then((body) => {
                    expect(body.schedules.map((run) => run.schedule.id)).to.deep.eq([schedule.classA1, schedule.classA2]);
                    const [teachers, helpers] = body.schedules;
                    expect(teachers, "the picnic's occurrence was there already").to.include({
                        widened: true,
                        created: eventIds.length,
                        existing: 1,
                        assigned: eventIds.length,
                    });
                    expect(teachers.schedule).to.include({ windowStart: first, windowEnd: last, startOffsetMinutes: -15 });
                    expect(helpers).to.include({ widened: false, created: eventIds.length, assigned: 0 });
                    expect(helpers.schedule.windowEnd, "an open end stays open").to.eq(null);

                    occurrencesOn(eventIds).then((rows) => {
                        expect(rows, "one occurrence per event per schedule").to.have.length(2 * eventIds.length);
                        expect(new Set(rows.map((r) => Number(r.scheduleId)))).to.deep.eq(new Set([schedule.classA1, schedule.classA2]));
                    });
                    dbOk(
                        `SELECT vasg.vasg_vocc_ID AS occurrenceId FROM volunteer_assignment_vasg vasg
                           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
                          WHERE vocc.vocc_vsch_ID = ? AND vasg.vasg_per_ID = ? AND vocc.vocc_event_id IN (${eventIds.map(() => "?").join(",")})`,
                        [schedule.classA1, POOL_MEMBER, ...eventIds],
                    ).then((rows) => {
                        expect(rows).to.have.length(eventIds.length);
                        expect(rows.map((r) => Number(r.occurrenceId))).not.to.include(earlierOccurrenceId);
                    });
                    api(ADMIN_KEY, "GET", `${URL}/schedules/${schedule.classA1}/requirements`).then((resp) => {
                        expect(resp.body.requirements.map((r) => [r.positionId, r.minCount]), "its own needs").to.deep.eq([[teacher, 2]]);
                    });
                });
            });
        });

        it("sends a series with no class to the ministry schedule with exactly its title, ignoring case", () => {
            createEvents(weekly("WORKDAY", 2, 30)).then((made) => {
                staffThem(made.events.map((e) => e.id)).then((body) => {
                    expect(body.schedules.map((run) => run.schedule.id)).to.deep.eq([schedule.ministry]);
                    expect(body.schedules[0]).to.include({ widened: false, created: made.events.length });
                });
                scheduleRow(schedule.ministry).then((row) => expect(row.windowEnd).to.eq(null));
            });
        });

        it("sends a series to the event-type schedule with its type and exactly its title, never to another ministry's", () => {
            createEvents(weekly("Evening Service", 3, 30)).then((made) => {
                const eventIds = made.events.map((e) => e.id);
                staffThem(eventIds).then((body) => {
                    expect(body.schedules.map((run) => run.schedule.id)).to.deep.eq([schedule.eventType]);
                    expect(body.schedules[0].created).to.eq(eventIds.length);
                });
                occurrencesOn(eventIds).then((rows) => {
                    expect(rows.every((r) => Number(r.scheduleId) === schedule.eventType), "nothing for ministry B").to.eq(true);
                });
            });
        });

        it("leaves an event-type schedule out for a series of another type", () => {
            createEvents(weekly("Evening Service", 4, 30, { eventTypeId: OTHER_TYPE })).then((made) => {
                staffThem(made.events.map((e) => e.id)).then((body) => expect(body.schedules).to.deep.eq([]));
            });
        });

        it("ignores an inactive schedule and a Staff this event one, answering an empty list and making nothing", () => {
            createEvents(weekly("Resting", 3, 30, { linkedGroupId: classes["Resting Class"] })).then((made) => {
                staffThem(made.events.map((e) => e.id)).then((body) => expect(body.schedules).to.deep.eq([]));
                occurrencesOn(made.events.map((e) => e.id)).then((rows) => expect(rows).to.have.length(0));
            });
            createEvents(weekly("Picnic", 5, 30)).then((made) => {
                staffThem(made.events.map((e) => e.id)).then((body) => expect(body.schedules).to.deep.eq([]));
                occurrencesOn(made.events.map((e) => e.id)).then((rows) => expect(rows).to.have.length(0));
            });
        });

        it("widens the dates of a series beyond the horizon and leaves its occurrences to the daily top-up", () => {
            createEvents(weekly("Faith City Autumn", HORIZON_DAYS + 10, HORIZON_DAYS + 40, { linkedGroupId: classes["Faith City"] })).then(
                (made) => {
                    const last = made.events[made.events.length - 1].start.slice(0, 10);
                    staffThem(made.events.map((e) => e.id)).then((body) => {
                        expect(body.schedules.map((run) => [run.schedule.id, run.created])).to.deep.eq([
                            [schedule.classA1, 0],
                            [schedule.classA2, 0],
                        ]);
                    });
                    scheduleRow(schedule.classA1).then((row) => expect(row.windowEnd).to.eq(last));
                },
            );
        });

        it("refuses what is not one series of this ministry's events", () => {
            const url = `${URL}/ministries/${ministry.A}/events/staff`;
            api(COORDINATOR_KEY, "POST", url, { eventIds: [] }, 400);
            api(COORDINATOR_KEY, "POST", url, { eventIds: "1" }, 400);
            api(COORDINATOR_KEY, "POST", url, { eventIds: [0] }, 400);
            api(COORDINATOR_KEY, "POST", url, {}, 400);
            api(COORDINATOR_KEY, "POST", url, { eventIds: [999999999] }, 404);

            createEvents(oneEvent("Ministry B Event", 6), "B").then((other) => {
                api(COORDINATOR_KEY, "POST", url, { eventIds: [other.events[0].id] }, 403);
            });
            createEvents(oneEvent("Mixed One", 6)).then((one) => {
                createEvents(oneEvent("Mixed Two", 7)).then((two) => {
                    api(COORDINATOR_KEY, "POST", url, { eventIds: [one.events[0].id, two.events[0].id] }, 400).then((resp) => {
                        expect(resp.body.message).to.contain("not one series");
                    });
                });
            });
        });

        it("is a coordinator's: a team leader, a self-service login and rollout v1 are refused", () => {
            createEvents(oneEvent("Team Leader Event", 6), "B").then((made) => {
                staffThem([made.events[0].id], "B", COORDINATOR_KEY, 403);
            });
            createEvents(oneEvent("Self Service Event", 6)).then((made) => {
                grantScope(PERSON_SELFEDIT, "ministry", ministry.A);
                staffThem([made.events[0].id], "A", SELFEDIT_KEY, 403);
                dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [PERSON_SELFEDIT]);

                setVersion("v1");
                staffThem([made.events[0].id], "A", ADMIN_KEY, 403);
                setVersion("v2");
            });
        });
    });
});
