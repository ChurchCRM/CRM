/// <reference types="cypress" />

/**
 * Volunteer v2 — every occurrence anchored to a calendar event (D20), schedule time
 * offsets (D21) and the four ways a schedule finds its events, Staff this event
 * included (D22). Design §0.8, §2.8, §2.9, §3.3.2.
 *
 * Events go straight into `events_event` (and `event_audience` for a class's
 * meetings): V2 only ever reads them, so how they were made does not matter, and the
 * seeded calendar is all 2016/2017. Everything carries the spec's prefix and is
 * deleted in `before` as well as `after`.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";
const PLAINAUTH_KEY = "plainauth.api.key";
const SELFEDIT_KEY = "selfedit.api.key";

const PERSON_COORDINATOR = 3; // tony.wade — a ministry scope on A only
const PERSON_VOLUNTEER = 99; // the EditSelf-only volunteer

const CHURCH_SERVICE_TYPE = 1;
const SUNDAY_SCHOOL_GROUP_TYPE = 4;

const PREFIX = "ANCHOR22";
const CLASS_TITLE = `${PREFIX} Faith City`;
const WORKDAY_TITLE = `${PREFIX} Workday`;
const PICNIC_TITLE = `${PREFIX} Picnic`;
const COFFEE_TITLE = `${PREFIX} Coffee`;
const HARVEST_TITLE = `${PREFIX} Harvest`;

const URL = "/api/ministries";

let originalVersion = "v1";
let ministryA = 0;
let ministryB = 0;
let teamA1 = 0;
let teamA2 = 0;
let teamB1 = 0;
let positionA1 = 0;
let classGroupId = 0;
let crewGroupId = 0;

/** Event ids by role, filled in `before`. */
const events = {
    classLinked: [],
    classUnlinked: 0,
    classInactive: 0,
    crew: 0,
    workdayA: [],
    workdayB: 0,
    workdayInactive: 0,
    picnicA: 0,
    coffee: 0,
    harvest: 0,
    harvestOther: 0,
    past: 0,
    cancelled: 0,
    foreignMinistry: 0,
};

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
    return cy.makePrivateAPICall(Cypress.testEnv(key), method, url, body, expectedStatus);
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** One event; resolves to its id. */
function makeEvent(title, offsetDays, { start = "10:30:00", end = "11:45:00", inactive = 0, ministryId = null } = {}) {
    return dbOk(
        `INSERT INTO events_event (event_type, event_title, event_desc, event_text, event_start, event_end, inactive, event_ministry_id)
         VALUES (?, ?, '', '', ?, ?, ?, ?)`,
        [CHURCH_SERVICE_TYPE, title, `${isoDate(offsetDays)} ${start}`, `${isoDate(offsetDays)} ${end}`, inactive, ministryId],
    ).then((rows) => rows.insertId);
}

function linkToGroup(eventId, groupId) {
    return dbOk(`INSERT INTO event_audience (event_id, group_id) VALUES (?, ?)`, [eventId, groupId]);
}

function makeGroup(name, type) {
    return dbOk(
        `INSERT INTO group_grp (grp_Type, grp_RoleListID, grp_DefaultRole, grp_Name, grp_Description, grp_hasSpecialProps, grp_active, grp_include_email_export)
         VALUES (?, 0, 0, ?, '', 0, 1, 1)`,
        [type, name],
    ).then((rows) => rows.insertId);
}

function cleanupFixtures() {
    const like = [`${PREFIX}%`];
    const byMinistry = `JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`;
    dbOk(`DELETE vasg FROM volunteer_assignment_vasg vasg ${byMinistry}`, like);
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
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [PERSON_COORDINATOR]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, like);
    dbOk(
        `DELETE ea FROM event_audience ea JOIN events_event e ON e.event_id = ea.event_id WHERE e.event_title LIKE ?`,
        like,
    );
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, like);
    dbOk(`DELETE FROM group_grp WHERE grp_Name LIKE ?`, like);
}

function createMinistry(suffix) {
    return dbOk(
        `INSERT INTO volunteer_ministry_vmin (vmin_Name, vmin_Description, vmin_Active, vmin_CreatedDate, vmin_SundaySchool)
         VALUES (?, 'anchored occurrences fixture', 1, NOW(), 1)`,
        [`${PREFIX} ${suffix}`],
    ).then((rows) => rows.insertId);
}

function createTeam(ministryId, suffix) {
    return dbOk(
        `INSERT INTO volunteer_team_vtem (vtem_vmin_ID, vtem_Name, vtem_Description, vtem_Active)
         VALUES (?, ?, 'anchored occurrences fixture', 1)`,
        [ministryId, `${PREFIX} ${suffix}`],
    ).then((rows) => rows.insertId);
}

function scheduleBody(overrides = {}) {
    return {
        name: `${PREFIX} Schedule`,
        teamId: teamA1,
        windowStart: isoDate(0),
        ...overrides,
    };
}

function createSchedule(body, key = ADMIN_KEY, ministryId = ministryA) {
    return api(key, "POST", `${URL}/ministries/${ministryId}/schedules`, body, 201).then(
        (resp) => resp.body.schedule,
    );
}

function generate(scheduleId, through = isoDate(40)) {
    return api(ADMIN_KEY, "POST", `${URL}/schedules/${scheduleId}/generate`, { through }).then((resp) => resp.body);
}

function occurrencesOf(scheduleId) {
    return api(ADMIN_KEY, "GET", `${URL}/occurrences?from=${isoDate(-30)}&to=${isoDate(60)}&scheduleId=${scheduleId}`).then(
        (resp) => resp.body.occurrences,
    );
}

function staff(body, key = ADMIN_KEY, expectedStatus = 201, ministryId = ministryA) {
    return api(key, "POST", `${URL}/ministries/${ministryId}/staffed-events`, body, expectedStatus);
}

/** `YYYY-MM-DD HH:MM:SS` moved by `minutes`, read as a wall clock. */
function shifted(wallClock, minutes) {
    const [datePart, timePart] = wallClock.split(" ");
    const [y, mo, d] = datePart.split("-").map(Number);
    const [h, mi, s] = timePart.split(":").map(Number);
    const out = new Date(Date.UTC(y, mo - 1, d, h, mi, s) + minutes * 60000);
    const pad = (n) => String(n).padStart(2, "0");
    return (
        `${out.getUTCFullYear()}-${pad(out.getUTCMonth() + 1)}-${pad(out.getUTCDate())}` +
        ` ${pad(out.getUTCHours())}:${pad(out.getUTCMinutes())}:${pad(out.getUTCSeconds())}`
    );
}

// ── fixture ────────────────────────────────────────────────────────────────

before(() => {
    cy.rememberTestEnv(["admin.api.key", "user.api.key", "selfedit.api.key", "plainauth.api.key"]);
    cy.useChurchTimeZone();
});

after(() => {
    cy.useHostTimeZone();
});

describe("Volunteer v2 — every occurrence anchored to a calendar event (D20), schedule time offsets (D21) and the four ways a schedule finds its events, Staff this event included (D22)", () => {
    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
            originalVersion = resp.body.value ?? resp.body.data ?? "v1";
        });
        setVersion("v2");
        cleanupFixtures();

        createMinistry("Children").then((id) => {
            ministryA = id;
            createTeam(ministryA, "Faith City Team").then((teamId) => {
                teamA1 = teamId;
                dbOk(
                    `INSERT INTO volunteer_position_vpos (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Description, vpos_Active, vpos_Order)
                 VALUES (?, ?, ?, '', 1, 1)`,
                    [ministryA, teamId, `${PREFIX} Teacher`],
                ).then((rows) => {
                    positionA1 = rows.insertId;
                });
            });
            createTeam(ministryA, "Helpers").then((teamId) => {
                teamA2 = teamId;
            });
        });
        createMinistry("Grounds").then((id) => {
            ministryB = id;
            createTeam(ministryB, "Grounds Crew").then((teamId) => {
                teamB1 = teamId;
            });
        });

        makeGroup(CLASS_TITLE, SUNDAY_SCHOOL_GROUP_TYPE).then((id) => {
            classGroupId = id;
        });
        makeGroup(`${PREFIX} Workday Crew`, 0).then((id) => {
            crewGroupId = id;
        });

        cy.then(() => {
            // A class's meetings: three linked, one linked but inactive, one same-titled and unlinked.
            [3, 10, 17].forEach((offset) => {
                makeEvent(CLASS_TITLE, offset, { start: "09:30:00", end: "10:30:00" }).then((id) => {
                    events.classLinked.push(id);
                    linkToGroup(id, classGroupId);
                });
            });
            makeEvent(CLASS_TITLE, 24, { start: "09:30:00", end: "10:30:00", inactive: 1 }).then((id) => {
                events.classInactive = id;
                linkToGroup(id, classGroupId);
            });
            makeEvent(CLASS_TITLE, 5, { start: "09:30:00", end: "10:30:00" }).then((id) => {
                events.classUnlinked = id;
            });
            // A group that is no Sunday School class but has an upcoming linked event.
            makeEvent(`${PREFIX} Crew Meeting`, 6).then((id) => {
                events.crew = id;
                linkToGroup(id, crewGroupId);
            });

            // Ministry A's own events, B's same-titled one, and an inactive one of A's.
            makeEvent(WORKDAY_TITLE, 8, { ministryId: ministryA }).then((id) => events.workdayA.push(id));
            makeEvent(WORKDAY_TITLE, 15, { ministryId: ministryA }).then((id) => events.workdayA.push(id));
            makeEvent(WORKDAY_TITLE, 9, { ministryId: ministryB }).then((id) => {
                events.workdayB = id;
            });
            makeEvent(WORKDAY_TITLE, 22, { ministryId: ministryA, inactive: 1 }).then((id) => {
                events.workdayInactive = id;
            });
            makeEvent(PICNIC_TITLE, 12, { ministryId: ministryA }).then((id) => {
                events.picnicA = id;
            });

            makeEvent(COFFEE_TITLE, 5).then((id) => {
                events.coffee = id;
            });
            makeEvent(HARVEST_TITLE, 4, { start: "18:00:00", end: "20:00:00" }).then((id) => {
                events.harvest = id;
            });
            makeEvent(`${HARVEST_TITLE} 100%`, 11).then((id) => {
                events.harvestOther = id;
            });
            makeEvent(`${PREFIX} Past`, -3).then((id) => {
                events.past = id;
            });
            makeEvent(`${PREFIX} Cancelled`, 6, { inactive: 1 }).then((id) => {
                events.cancelled = id;
            });
            makeEvent(`${PREFIX} Grounds Workday`, 13, { ministryId: ministryB }).then((id) => {
                events.foreignMinistry = id;
            });
        });

        cy.then(() => {
            api(ADMIN_KEY, "POST", `${URL}/scopes`, { personId: PERSON_COORDINATOR, scopeType: "ministry", scopeId: ministryA }, [
                200, 201,
            ]);
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion(originalVersion);
    });

    // ── the spec ───────────────────────────────────────────────────────────────

    describe("Volunteer v2 — a class's meetings (class mode, D22)", () => {
        let schedule = null;

        it("creates a class schedule and echoes the class", () => {
            createSchedule(scheduleBody({ name: `${PREFIX} Faith City`, linkMode: "class", groupId: classGroupId, titleFilter: "ignored" })).then(
                (created) => {
                    schedule = created;
                    expect(created.linkMode).to.eq("class");
                    expect(created.groupId).to.eq(classGroupId);
                    expect(created.groupName).to.eq(CLASS_TITLE);
                    expect(created.groupSundaySchool).to.eq(true);
                    expect(created.eventTypeId).to.eq(null);
                    expect(created.titleFilter, "a class schedule has no title filter").to.eq(null);
                    expect(created.occurrenceCount, "generated on Save (D33)").to.eq(3);
                },
            );
        });

        it("anchors one occurrence to each active event whose Linked Group is the class", () => {
            occurrencesOf(schedule.id).then((rows) => {
                expect(rows.map((row) => row.eventId).sort()).to.deep.eq([...events.classLinked].sort());
                expect(rows.map((row) => row.eventId), "not the unlinked one").to.not.include(events.classUnlinked);
                expect(rows.map((row) => row.eventId), "not the inactive one").to.not.include(events.classInactive);
                rows.forEach((row) => {
                    expect(row.start).to.contain("09:30:00");
                });
            });
        });

        it("is idempotent: a second run over the same window creates nothing", () => {
            generate(schedule.id).then((result) => {
                expect(result.created).to.eq(0);
                expect(result.existing).to.eq(3);
            });
        });

        it("respects its window", () => {
            createSchedule(
                scheduleBody({ name: `${PREFIX} Faith City Windowed`, linkMode: "class", groupId: classGroupId, windowEnd: isoDate(12) }),
            ).then((windowed) => {
                expect(windowed.occurrenceCount, "only the meetings on or before the window end").to.eq(2);
                generate(windowed.id).then((result) => {
                    expect(result).to.include({ created: 0, existing: 2, through: isoDate(12) });
                });
            });
        });

        it("lists the Sunday School class and any group with upcoming linked events", () => {
            api(COORDINATOR_KEY, "GET", `${URL}/classes`).then((resp) => {
                const byId = Object.fromEntries(resp.body.classes.map((row) => [row.groupId, row]));
                expect(byId[classGroupId].sundaySchool).to.eq(true);
                expect(byId[classGroupId].upcomingCount, "active linked events only").to.eq(3);
                expect(byId[crewGroupId].sundaySchool).to.eq(false);
                expect(byId[crewGroupId].upcomingCount).to.eq(1);
            });
        });

        it("refuses a class schedule with no class or an unknown one", () => {
            api(ADMIN_KEY, "POST", `${URL}/ministries/${ministryA}/schedules`, scheduleBody({ linkMode: "class" }), 400);
            api(ADMIN_KEY, "POST", `${URL}/ministries/${ministryA}/schedules`, scheduleBody({ linkMode: "class", groupId: 987654 }), 400);
        });

        it("finds nothing once its class is deleted (ON DELETE SET NULL)", () => {
            makeGroup(`${PREFIX} Short-lived Class`, SUNDAY_SCHOOL_GROUP_TYPE).then((groupId) => {
                makeEvent(`${PREFIX} Short-lived Meeting`, 7).then((eventId) => linkToGroup(eventId, groupId));
                createSchedule(scheduleBody({ name: `${PREFIX} Short-lived`, linkMode: "class", groupId })).then((created) => {
                    dbOk(`DELETE FROM group_grp WHERE grp_ID = ?`, [groupId]);
                    api(ADMIN_KEY, "GET", `${URL}/schedules/${created.id}`).then((resp) => {
                        expect(resp.body.schedule.groupId).to.eq(null);
                    });
                    generate(created.id).then((result) => {
                        expect(result.created).to.eq(0);
                    });
                });
            });
        });
    });

    describe("Volunteer v2 — this ministry's events (ministry mode, D22)", () => {
        it("follows the ministry's own active events of one title", () => {
            createSchedule(scheduleBody({ name: `${PREFIX} Workdays`, linkMode: "ministry", titleFilter: WORKDAY_TITLE })).then(
                (created) => {
                    expect(created.linkMode).to.eq("ministry");
                    expect(created.titleFilter).to.eq(WORKDAY_TITLE);
                    expect(created.occurrenceCount).to.eq(2);
                    occurrencesOf(created.id).then((rows) => {
                        const ids = rows.map((row) => row.eventId);
                        expect(ids.sort()).to.deep.eq([...events.workdayA].sort());
                        expect(ids, "not another ministry's event of the same title").to.not.include(events.workdayB);
                        expect(ids, "not an inactive one").to.not.include(events.workdayInactive);
                    });
                },
            );
        });

        it("refuses a schedule that names no title: there is no 'any of this ministry's events' (D31)", () => {
            api(ADMIN_KEY, "POST", `${URL}/ministries/${ministryA}/schedules`, scheduleBody({ name: `${PREFIX} Everything`, linkMode: "ministry" }), 400).then(
                (resp) => {
                    expect(resp.body.message).to.eq("Choose the event this schedule follows");
                },
            );
        });

        it("lists the ministry's upcoming titles for the title picker", () => {
            api(COORDINATOR_KEY, "GET", `${URL}/event-series?ministryId=${ministryA}`).then((resp) => {
                const byTitle = Object.fromEntries(resp.body.series.map((row) => [row.title, row]));
                expect(byTitle[WORKDAY_TITLE].count, "active events only").to.eq(2);
                expect(byTitle[PICNIC_TITLE].count).to.eq(1);
                expect(byTitle).to.not.have.property(`${PREFIX} Grounds Workday`);
            });
            api(COORDINATOR_KEY, "GET", `${URL}/event-series`, null, 400);
        });
    });

    describe("Volunteer v2 — church events of a type (event_type mode)", () => {
        it("skips inactive events", () => {
            createSchedule(
                scheduleBody({
                    name: `${PREFIX} By Type`,
                    linkMode: "event_type",
                    eventTypeId: CHURCH_SERVICE_TYPE,
                    titleFilter: CLASS_TITLE,
                }),
            ).then((created) => {
                // The three linked meetings and the unlinked one; never the inactive one.
                expect(created.occurrenceCount).to.eq(4);
                occurrencesOf(created.id).then((rows) => {
                    expect(rows.map((row) => row.eventId)).to.not.include(events.classInactive);
                });
            });
        });
    });

    describe("Volunteer v2 — the volunteers' times move with the event (D21)", () => {
        let schedule = null;
        let occurrence = null;

        it("adds the offsets to the event's start and end", () => {
            createSchedule(
                scheduleBody({
                    name: `${PREFIX} Coffee Setup`,
                    linkMode: "event_type",
                    eventTypeId: CHURCH_SERVICE_TYPE,
                    titleFilter: COFFEE_TITLE,
                    startOffsetMinutes: -45,
                    endOffsetMinutes: 15,
                }),
            ).then((created) => {
                schedule = created;
                expect(created.startOffsetMinutes).to.eq(-45);
                expect(created.endOffsetMinutes).to.eq(15);
                generate(created.id);
                occurrencesOf(created.id).then((rows) => {
                    expect(rows).to.have.length(1);
                    occurrence = rows[0];
                    expect(occurrence.start).to.eq(`${isoDate(5)} 09:45:00`);
                    expect(occurrence.end).to.eq(`${isoDate(5)} 12:00:00`);
                });
            });
            cy.then(() => {
                api(ADMIN_KEY, "GET", `${URL}/occurrences/${occurrence.id}`).then((resp) => {
                    expect(resp.body.occurrence.start).to.eq(`${isoDate(5)} 09:45:00`);
                });
            });
        });

        it("reports the shift to the volunteer on their own schedule", () => {
            dbOk(
                `INSERT INTO volunteer_qualification_vqal (vqal_per_ID, vqal_vpos_ID, vqal_Active, vqal_GrantedDate) VALUES (?, ?, 1, NOW())`,
                [PERSON_VOLUNTEER, positionA1],
            );
            api(ADMIN_KEY, "POST", `${URL}/occurrences/${occurrence.id}/assignments`, {
                positionId: positionA1,
                personId: PERSON_VOLUNTEER,
                allowOutsidePool: true,
            }, 201);
            api(SELFEDIT_KEY, "GET", `${URL}/me/assignments`).then((resp) => {
                const mine = resp.body.assignments.find((row) => row.occurrenceId === occurrence.id);
                expect(mine.start).to.eq(`${isoDate(5)} 09:45:00`);
            });
        });

        it("recomputes the times when the offsets change, with no regeneration", () => {
            api(ADMIN_KEY, "POST", `${URL}/schedules/${schedule.id}`, { startOffsetMinutes: 30, endOffsetMinutes: -15 });
            api(ADMIN_KEY, "GET", `${URL}/occurrences/${occurrence.id}`).then((resp) => {
                expect(resp.body.occurrence.start).to.eq(`${isoDate(5)} 11:00:00`);
                expect(resp.body.occurrence.end).to.eq(`${isoDate(5)} 11:30:00`);
            });
        });

        it("moves with the event", () => {
            dbOk(`UPDATE events_event SET event_start = ?, event_end = ? WHERE event_id = ?`, [
                `${isoDate(5)} 14:00:00`,
                `${isoDate(5)} 15:00:00`,
                events.coffee,
            ]);
            api(ADMIN_KEY, "GET", `${URL}/occurrences/${occurrence.id}`).then((resp) => {
                expect(resp.body.occurrence.start).to.eq(shifted(`${isoDate(5)} 14:00:00`, 30));
                expect(resp.body.occurrence.end).to.eq(shifted(`${isoDate(5)} 15:00:00`, -15));
            });
        });

        it("keeps the date and loses the times once the event is deleted", () => {
            dbOk(`DELETE FROM events_event WHERE event_id = ?`, [events.coffee]);
            api(ADMIN_KEY, "GET", `${URL}/occurrences/${occurrence.id}`).then((resp) => {
                expect(resp.body.occurrence.eventId).to.eq(null);
                expect(resp.body.occurrence.start).to.eq(null);
                expect(resp.body.occurrence.end).to.eq(null);
                expect(resp.body.occurrence.occurrenceDate).to.eq(isoDate(5));
            });
        });

        it("accepts offsets up to 720 minutes either way and refuses anything else", () => {
            createSchedule(
                scheduleBody({ name: `${PREFIX} Far`, linkMode: "ministry", titleFilter: WORKDAY_TITLE, startOffsetMinutes: -720, endOffsetMinutes: 720 }),
            );
            [721, -721, 1.5, "abc", true].forEach((bad) => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `${URL}/ministries/${ministryA}/schedules`,
                    scheduleBody({ name: `${PREFIX} Bad Offset`, linkMode: "ministry", titleFilter: WORKDAY_TITLE, startOffsetMinutes: bad }),
                    400,
                );
            });
            api(
                ADMIN_KEY,
                "POST",
                `${URL}/ministries/${ministryA}/schedules`,
                scheduleBody({ name: `${PREFIX} Bad Offset`, linkMode: "ministry", titleFilter: WORKDAY_TITLE, endOffsetMinutes: -800 }),
                400,
            );
        });
    });

    describe("Volunteer v2 — the retired standalone fields are refused (D20)", () => {
        ["recurType", "recurDow", "recurDom", "startTime", "endTime"].forEach((field) => {
            it(`refuses ${field} with 400`, () => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `${URL}/ministries/${ministryA}/schedules`,
                    scheduleBody({ linkMode: "ministry", [field]: null }),
                    400,
                ).then((resp) => {
                    expect(resp.body.message).to.contain(field);
                });
            });
        });

        it("refuses the standalone link mode, the event link mode and an eventId", () => {
            api(ADMIN_KEY, "POST", `${URL}/ministries/${ministryA}/schedules`, scheduleBody({ linkMode: "standalone" }), 400);
            api(ADMIN_KEY, "POST", `${URL}/ministries/${ministryA}/schedules`, scheduleBody({ linkMode: "event", eventId: events.harvest }), 400);
            api(ADMIN_KEY, "POST", `${URL}/ministries/${ministryA}/schedules`, scheduleBody({ linkMode: "ministry", eventId: events.harvest }), 400);
        });

        it("refuses them on an update as well", () => {
            createSchedule(scheduleBody({ name: `${PREFIX} Updatable`, linkMode: "ministry", titleFilter: WORKDAY_TITLE })).then((created) => {
                api(ADMIN_KEY, "POST", `${URL}/schedules/${created.id}`, { startTime: "10:00" }, 400);
                api(ADMIN_KEY, "POST", `${URL}/schedules/${created.id}`, { linkMode: "event" }, 400);
                // Switching between the three editable modes is fine, and clears what the old one used.
                api(ADMIN_KEY, "POST", `${URL}/schedules/${created.id}`, { linkMode: "class", groupId: classGroupId }).then((resp) => {
                    expect(resp.body.schedule.linkMode).to.eq("class");
                    expect(resp.body.schedule.titleFilter).to.eq(null);
                });
            });
        });
    });

    describe("Volunteer v2 — Staff this event (D22)", () => {
        let staffed = null;

        it("creates a hidden single-event schedule with its needs and exactly one occurrence", () => {
            staff({
                eventId: events.harvest,
                teamId: teamA1,
                startOffsetMinutes: -30,
                requirements: [{ positionId: positionA1, minCount: 2, maxCount: 3 }],
            }).then((resp) => {
                staffed = resp.body;
                const { occurrence, schedule } = resp.body;
                expect(schedule.linkMode).to.eq("event");
                expect(schedule.oneOff).to.eq(true);
                expect(schedule.eventId).to.eq(events.harvest);
                expect(schedule.name, "named after the event").to.eq(HARVEST_TITLE);
                expect(schedule.windowStart).to.eq(isoDate(4));
                expect(schedule.windowEnd).to.eq(isoDate(4));
                expect(schedule.occurrenceCount).to.eq(1);
                expect(occurrence.eventId).to.eq(events.harvest);
                expect(occurrence.teamId).to.eq(teamA1);
                expect(occurrence.start).to.eq(`${isoDate(4)} 17:30:00`);
                expect(occurrence.end).to.eq(`${isoDate(4)} 20:00:00`);
            });
            cy.then(() => {
                api(ADMIN_KEY, "GET", `${URL}/schedules/${staffed.schedule.id}/requirements`).then((resp) => {
                    expect(resp.body.requirements).to.have.length(1);
                    expect(resp.body.requirements[0].minCount).to.eq(2);
                });
            });
        });

        it("keeps the hidden schedule off both schedule lists", () => {
            api(ADMIN_KEY, "GET", `${URL}/ministries/${ministryA}/schedules`).then((resp) => {
                expect(resp.body.schedules.map((row) => row.id)).to.not.include(staffed.schedule.id);
            });
            api(ADMIN_KEY, "GET", `${URL}/teams/${teamA1}/schedules`).then((resp) => {
                expect(resp.body.schedules.map((row) => row.id)).to.not.include(staffed.schedule.id);
            });
        });

        it("refuses a second one for the same team and event (409), but not for another team", () => {
            staff({ eventId: events.harvest, teamId: teamA1 }, ADMIN_KEY, 409);
            staff({ eventId: events.harvest, teamId: teamA2 }).then((resp) => {
                expect(resp.body.occurrence.teamId).to.eq(teamA2);
            });
        });

        it("says which events the team already staffs in the search", () => {
            api(ADMIN_KEY, "GET", `${URL}/upcoming-events?q=${encodeURIComponent(HARVEST_TITLE)}&teamId=${teamA1}`).then((resp) => {
                const byId = Object.fromEntries(resp.body.events.map((row) => [row.id, row]));
                expect(byId[events.harvest].staffedByTeam).to.eq(true);
                expect(byId[events.harvestOther].staffedByTeam).to.eq(false);
                expect(byId[events.harvest].eventTypeName).to.eq("Church Service");
            });
        });

        it("searches upcoming active events by title and date, % literal", () => {
            api(ADMIN_KEY, "GET", `${URL}/upcoming-events?q=${encodeURIComponent(PREFIX)}`).then((resp) => {
                const ids = resp.body.events.map((row) => row.id);
                expect(ids).to.include(events.harvest);
                expect(ids, "not a past event").to.not.include(events.past);
                expect(ids, "not an inactive event").to.not.include(events.cancelled);
                const starts = resp.body.events.map((row) => row.start);
                expect(starts, "soonest first").to.deep.eq([...starts].sort());
            });
            api(ADMIN_KEY, "GET", `${URL}/upcoming-events?q=${encodeURIComponent("100%")}`).then((resp) => {
                expect(resp.body.events.map((row) => row.id)).to.deep.eq([events.harvestOther]);
            });
            api(ADMIN_KEY, "GET", `${URL}/upcoming-events?q=${encodeURIComponent(PREFIX)}&from=${isoDate(4)}&to=${isoDate(4)}`).then(
                (resp) => {
                    expect(resp.body.events.map((row) => row.id)).to.deep.eq([events.harvest]);
                },
            );
            api(ADMIN_KEY, "GET", `${URL}/upcoming-events?from=${isoDate(10)}&to=${isoDate(2)}`, null, 400);
            api(ADMIN_KEY, "GET", `${URL}/upcoming-events?from=tomorrow`, null, 400);
        });

        it("may anchor to any event, not only the ministry's own", () => {
            staff({ eventId: events.foreignMinistry, teamId: teamA1 }).then((resp) => {
                expect(resp.body.occurrence.eventId).to.eq(events.foreignMinistry);
            });
        });

        it("refuses a bad request with 400 and writes nothing", () => {
            staff({ eventId: 987654, teamId: teamA1 }, ADMIN_KEY, 400);
            staff({ eventId: events.past, teamId: teamA1 }, ADMIN_KEY, 400);
            staff({ eventId: events.cancelled, teamId: teamA1 }, ADMIN_KEY, 400);
            staff({ eventId: events.harvestOther, teamId: teamB1 }, ADMIN_KEY, 400);
            staff({ eventId: events.harvestOther, teamId: teamA1, startOffsetMinutes: 900 }, ADMIN_KEY, 400);
            staff({ eventId: events.harvestOther, teamId: teamA1, recurType: "weekly" }, ADMIN_KEY, 400);
            staff({ eventId: events.harvestOther, teamId: teamA1, requirements: [{ positionId: 987654, minCount: 1 }] }, ADMIN_KEY, 400);
            dbOk(`SELECT COUNT(*) AS c FROM volunteer_schedule_vsch WHERE vsch_event_id = ?`, [events.harvestOther]).then((rows) => {
                expect(Number(rows[0].c), "no half-made schedule").to.eq(0);
            });
        });

        it("lets a coordinator staff for their ministry and nowhere else", () => {
            staff({ eventId: events.harvestOther, teamId: teamA1 }, COORDINATOR_KEY, 201);
            staff({ eventId: events.harvestOther, teamId: teamB1 }, COORDINATOR_KEY, 403, ministryB);
            staff({ eventId: events.harvestOther, teamId: teamA2 }, PLAINAUTH_KEY, 403);
        });

        it("deletes the hidden schedule with its only occurrence, and leaves the event alone", () => {
            api(ADMIN_KEY, "DELETE", `${URL}/occurrences/${staffed.occurrence.id}`);
            api(ADMIN_KEY, "GET", `${URL}/schedules/${staffed.schedule.id}`, null, 404);
            dbOk(`SELECT COUNT(*) AS c FROM events_event WHERE event_id = ?`, [events.harvest]).then((rows) => {
                expect(Number(rows[0].c)).to.eq(1);
            });
        });
    });
});
