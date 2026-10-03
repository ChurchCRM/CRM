/// <reference types="cypress" />

/**
 * Volunteer v2 D30 — a Generate run that finds no events says what it looked for. Design §0.8 D30,
 * §2.8, §3.3.2. D30's other half — new events go to the schedule that already follows them — is
 * "Staff them" since D33 and lives in `private.volunteer.staff-after-create.spec.js`.
 *
 * Personas (seed.sql): `admin.api.key` (administrator). The ministry comes from the real create
 * API with the Sunday School switch on, so its schedules may follow a class.
 *
 * Since D31 a schedule is only created while an upcoming event matches, so every empty run here
 * is one whose events lie beyond the dates it looks at — the scheduling horizon, a `through`, or
 * a window that has ended. A new schedule's Save is itself a run (D33).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
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

/** The new schedule, carrying the run its Save made (D33) as `generated`. */
function createSchedule(overrides) {
    return api(
        "POST",
        `${URL}/ministries/${ministryId}/schedules`,
        { teamId: teamA, windowStart: isoDate(0), ...overrides, name: `${PREFIX} ${overrides.name}` },
        201,
    ).then((resp) => ({ ...resp.body.schedule, generated: resp.body.generated }));
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
    cy.rememberTestEnv(["admin.api.key"]);
});

describe("Volunteer v2 D30 — a Generate run that finds no events says what it looked for", () => {
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
        for (const key of ["Empty Class", "Meeting Class"]) {
            makeClass(key);
        }
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
                expect(schedule.generated, "its Save found nothing either (D33)").to.include({ created: 0, noEvents: true });
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
                    expect(schedule.generated).to.include({ created: made.events.length, existing: 0, noEvents: false });
                    generate(schedule.id).then((again) => {
                        expect(again).to.include({ created: 0, existing: made.events.length, noEvents: false });
                        expect(again.searched.groupName).to.eq(`${PREFIX} Meeting Class`);
                    });
                });
            });
        });
    });
});
