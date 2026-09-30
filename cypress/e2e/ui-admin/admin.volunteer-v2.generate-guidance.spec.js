/// <reference types="cypress" />

/**
 * Volunteer v2 D30 — telling the coordinator why nothing was generated, and never duplicating a
 * schedule. The Generate dialog's amber warning and its New recurring event button, the schedule
 * dialog's class and title warnings, and the Staff these events hint. Design §0.8 D30, §5.4.
 *
 * Fixtures go in through the admin API. Order in every hook is fixture → login → visit, because
 * cy.request() rotates the PHP session cookie.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const MINISTRIES_URL = "/api/ministries/ministries";
const GROUPS_URL = "/api/groups";

const PREFIX = "UIGEN30";
const MINISTRY_NAME = `${PREFIX} Children`;
const CLASS_NAME = `${PREFIX} Faith City`;
const BUSY_CLASS = `${PREFIX} Busy Class`;
const SCHEDULE_NAME = `${PREFIX} Faith City Teachers`;
const GONE_TITLE = `${PREFIX} Retired Event`;
const EMPTY_CLASS = `${PREFIX} Empty Class`;
const WEEKDAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

let ministryId = 0;
let teamId = 0;
let positionId = 0;
let classId = 0;
let busyClassId = 0;
let scheduleId = 0;
let goneScheduleId = 0;
let emptyClassId = 0;
let emptyScheduleId = 0;

function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(`${Cypress.env("admin.password")}{enter}`);
    cy.url().should("not.include", "/session/begin");
}

function admin(method, url, body, status = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, status);
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

function cleanupFixtures() {
    admin("GET", MINISTRIES_URL, null).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                admin("POST", `${MINISTRIES_URL}/${ministry.id}`, { active: false }, [200, 404]);
                admin("DELETE", `${MINISTRIES_URL}/${ministry.id}`, null, [200, 404]);
            }
        }
    });
    for (const table of ["calendar_events", "event_audience"]) {
        cy.dbQuery(`DELETE t FROM ${table} t JOIN events_event e ON e.event_id = t.event_id WHERE e.event_title LIKE ?`, [
            `${PREFIX}%`,
        ]);
    }
    cy.dbQuery("DELETE FROM events_event WHERE event_title LIKE ?", [`${PREFIX}%`]);
    admin("GET", `${GROUPS_URL}/`, null).then((resp) => {
        for (const group of resp.body) {
            if (String(group.Name).startsWith(PREFIX)) {
                admin("DELETE", `${GROUPS_URL}/${group.Id}`, null, [200, 404]);
            }
        }
    });
}

function openSchedulesTab(base = `/ministries/${ministryId}`) {
    cy.visit(base);
    cy.get("#nav-item-schedules").click();
    cy.get("#schedules-loading").should("not.be.visible");
}

function openGenerateDialog(id) {
    cy.get(`.volunteer-schedule-generate[data-schedule-id="${id}"]`, { timeout: 15000 })
        .closest("tr")
        .find("[data-bs-toggle='dropdown']")
        .click();
    cy.get(`.volunteer-schedule-generate[data-schedule-id="${id}"]`).should("be.visible").click();
    cy.get("#generateOccurrencesModal").should("be.visible");
    cy.get("#generate-form-loading").should("not.be.visible");
    cy.get("#generate-form-warning").should("not.be.visible");
}

describe("Volunteer v2 D30 — why nothing was generated, and one schedule per class", () => {
    before(() => {
        admin("POST", SETTING_URL, { value: "v2" });
        cleanupFixtures();

        admin("POST", MINISTRIES_URL, { name: MINISTRY_NAME, description: "D30 fixture", sundaySchool: true }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
            admin("GET", `${MINISTRIES_URL}/${ministryId}`).then((detail) => {
                teamId = detail.body.teams[0].id;
                admin("POST", `${MINISTRIES_URL}/${ministryId}/positions`, { name: `${PREFIX} Teacher`, teamId }, 201).then(
                    (position) => {
                        positionId = position.body.position.id;
                    },
                );
            });
        });
        admin("POST", `${GROUPS_URL}/`, { groupName: CLASS_NAME, isSundaySchool: true }).then((resp) => {
            classId = resp.body.Id;
        });
        admin("POST", `${GROUPS_URL}/`, { groupName: BUSY_CLASS, isSundaySchool: true }).then((resp) => {
            busyClassId = resp.body.Id;
        });
        admin("POST", `${GROUPS_URL}/`, { groupName: EMPTY_CLASS, isSundaySchool: true }).then((resp) => {
            emptyClassId = resp.body.Id;
        });
        cy.then(() => {
            admin("POST", `/api/ministries/teams/${teamId}`, { classGroupId: classId });
            admin(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/events`,
                {
                    title: `${PREFIX} Busy Class Meeting`,
                    eventTypeId: 1,
                    linkedGroupId: busyClassId,
                    recurrence: { type: "weekly", dow: WEEKDAYS[localDate(2).getDay()] },
                    rangeStart: isoDate(1),
                    rangeEnd: isoDate(30),
                    startTime: "09:30",
                    endTime: "10:30",
                },
                201,
            );
            admin(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/schedules`,
                {
                    name: SCHEDULE_NAME,
                    teamId,
                    linkMode: "class",
                    groupId: classId,
                    windowStart: isoDate(0),
                    requirements: [{ positionId, minCount: 1, maxCount: 1 }],
                },
                201,
            ).then((resp) => {
                scheduleId = resp.body.schedule.id;
            });
            admin(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/schedules`,
                { name: `${PREFIX} Retired`, teamId, linkMode: "ministry", titleFilter: GONE_TITLE, windowStart: isoDate(0) },
                201,
            ).then((resp) => {
                goneScheduleId = resp.body.schedule.id;
            });
            admin(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/schedules`,
                { name: `${PREFIX} Empty Class Teachers`, teamId, linkMode: "class", groupId: emptyClassId, windowStart: isoDate(0) },
                201,
            ).then((resp) => {
                emptyScheduleId = resp.body.schedule.id;
            });
        });
    });

    after(() => {
        cleanupFixtures();
        admin("POST", SETTING_URL, { value: "v1" });
    });

    beforeEach(() => {
        freshAdminLogin();
    });

    it("shows each class's upcoming meetings in the schedule dialog and warns at none", () => {
        openSchedulesTab();
        cy.get("#schedule-add-btn").click();
        cy.get("#scheduleModal").should("be.visible");
        cy.get("#schedule-form-link-mode").should("have.value", "class");
        cy.get("#schedule-form-group").should("have.value", String(classId));
        cy.get(`#schedule-form-group option[value="${classId}"]`).should("have.text", `${CLASS_NAME} (no upcoming meetings)`);
        cy.get(`#schedule-form-group option[value="${busyClassId}"]`)
            .invoke("text")
            .should("match", new RegExp(`^${BUSY_CLASS} \\(\\d+ upcoming\\)$`));
        cy.get("#schedule-form-group-warning")
            .should("be.visible")
            .and("contain", `${CLASS_NAME} has no upcoming meetings on the calendar`)
            .and("contain", "Calendar tab");

        cy.get("#schedule-form-group").select(String(busyClassId));
        cy.get("#schedule-form-group-warning").should("not.be.visible");
        cy.get("#schedule-form-link-mode").select("event_type");
        cy.get("#schedule-form-group-warning").should("not.be.visible");
    });

    it("warns when the schedule's event title has nothing upcoming", () => {
        openSchedulesTab();
        cy.get(`.volunteer-schedule-edit[data-schedule-id="${goneScheduleId}"]`, { timeout: 15000 })
            .closest("tr")
            .find("[data-bs-toggle='dropdown']")
            .click();
        cy.get(`.volunteer-schedule-edit[data-schedule-id="${goneScheduleId}"]`).should("be.visible").click();
        cy.get("#scheduleModal").should("be.visible");
        cy.get("#schedule-form-title-filter").should("have.value", GONE_TITLE);
        cy.get("#schedule-form-title-warning")
            .should("be.visible")
            .and("contain", `No upcoming events titled ${GONE_TITLE} are on the calendar.`);
        cy.get("#schedule-form-title-filter").select(`${PREFIX} Busy Class Meeting`);
        cy.get("#schedule-form-title-warning").should("not.be.visible");
    });

    it("answers a Generate run that found no events with an amber warning and a pre-filled New recurring event", () => {
        openSchedulesTab();
        openGenerateDialog(scheduleId);

        cy.intercept("POST", `**/api/ministries/schedules/${scheduleId}/generate`).as("generate");
        cy.get("#generate-form-save").click();
        cy.wait("@generate").its("response.body.noEvents").should("eq", true);
        cy.get("#generateOccurrencesModal").should("be.visible");
        cy.get("#generate-form-warning")
            .should("be.visible")
            .and("have.class", "alert-warning")
            .and("contain", `No events on the calendar use ${CLASS_NAME} as their class between`);
        cy.get(".notyf__toast--success").should("not.exist");

        cy.get("#generate-form-add-events").should("be.visible").and("contain", `New recurring event for ${CLASS_NAME}`).click();
        cy.get("#generateOccurrencesModal").should("not.be.visible");
        cy.get("#nav-item-calendar").should("have.class", "active");
        cy.get("#ministryEventModal").should("be.visible");
        cy.get("#ministryEventModalTitle").should("have.text", "New recurring event");
        cy.get("#ministry-event-form-series").should("be.checked");
        cy.get("#ministry-event-form-class").should("have.value", String(classId));
        cy.get("#ministry-event-form-title").should("have.value", CLASS_NAME);
        cy.get("#ministry-event-form-staff-toggle").should("not.be.checked");

        cy.get("#ministry-event-form-type").select("Church Service");
        cy.get("#ministry-event-form-dow").select(WEEKDAYS[localDate(3).getDay()]);
        cy.get("#ministry-event-form-range-start").clear().type(isoDate(1));
        cy.get("#ministry-event-form-range-end").clear().type(isoDate(29));
        cy.intercept("POST", `**/api/ministries/ministries/${ministryId}/events`).as("create");
        cy.get("#ministry-event-form-save").click();
        cy.wait("@create").then(({ request, response }) => {
            expect(request.body.linkedGroupId).to.eq(classId);
            expect(request.body).not.to.have.property("staff");
            expect(response.statusCode).to.eq(201);
        });
        cy.get(".notyf__toast--success").should("contain", `Generate occurrences on "${SCHEDULE_NAME}" to staff them`);
    });

    it("reports new occurrences as a success and a run that finds them all there as neutral", () => {
        openSchedulesTab();
        openGenerateDialog(scheduleId);
        cy.intercept("POST", `**/api/ministries/schedules/${scheduleId}/generate`).as("generate");
        cy.get("#generate-form-save").click();
        cy.wait("@generate").its("response.body.created").should("be.greaterThan", 0);
        cy.get("#generateOccurrencesModal").should("not.be.visible");
        cy.get(".notyf__toast--success").should("contain", "occurrences created");

        openGenerateDialog(scheduleId);
        cy.get("#generate-form-save").click();
        cy.wait("@generate").its("response.body").should("include", { created: 0, noEvents: false });
        cy.get("#generateOccurrencesModal").should("not.be.visible");
        // An info toast carries no type class of its own; its icon says what it is.
        cy.contains(".notyf__toast", "No new occurrences").should("not.have.class", "notyf__toast--success").find(".fa-circle-info");
    });

    it("says new class events go to the team's schedule and keeps only Fill by default with", () => {
        cy.visit(`/ministries/${ministryId}`);
        cy.get("#nav-item-calendar").click();
        cy.get("#ministry-events-loading").should("not.be.visible");
        cy.get("#ministry-event-add-series-btn").click();
        cy.get("#ministryEventModal").should("be.visible");
        cy.get("#ministry-event-form-title").should("have.focus").type(`${PREFIX} Faith City Summer`);
        cy.get("#ministry-event-form-type").select("Church Service");
        cy.get("#ministry-event-form-dow").select(WEEKDAYS[localDate(3).getDay()]);
        cy.get("#ministry-event-form-range-start").clear().type(isoDate(31));
        cy.get("#ministry-event-form-range-end").clear().type(isoDate(60));
        cy.get("#ministry-event-form-staff-toggle").check({ force: true });
        cy.get("#ministry-event-form-staff").scrollIntoView().should("be.visible");
        cy.get("#ministry-event-form-class").should("have.value", String(classId));

        cy.get("#ministry-event-form-reuse")
            .should("be.visible")
            .and("have.text", `These events will be added to "${SCHEDULE_NAME}" and staffed with its needs.`);
        cy.get("#ministry-event-form-plan").should("not.be.visible");
        cy.get("#ministry-event-form-offsets").should("not.be.visible");
        cy.get(`#ministry-event-form-defaults .generate-default-row[data-position-id="${positionId}"]`).should("exist");

        cy.get("#ministry-event-form-class").select("");
        cy.get("#ministry-event-form-reuse").should("not.be.visible");
        cy.get("#ministry-event-form-plan").scrollIntoView().should("be.visible");
        cy.get("#ministry-event-form-class").select(String(classId));
        cy.get("#ministry-event-form-reuse").scrollIntoView().should("be.visible");

        cy.intercept("POST", `**/api/ministries/ministries/${ministryId}/events`).as("create");
        cy.get("#ministry-event-form-save").click();
        cy.wait("@create").then(({ request, response }) => {
            expect(request.body.staff.teamId).to.eq(teamId);
            expect(request.body.staff).not.to.have.property("requirements");
            expect(response.statusCode).to.eq(201);
            expect(response.body.reusedSchedule).to.eq(true);
            expect(response.body.schedule.id).to.eq(scheduleId);
        });
        cy.get(".notyf__toast--success").should("contain", `Added to "${SCHEDULE_NAME}"`);
        cy.dbQuery("SELECT COUNT(*) AS n FROM volunteer_schedule_vsch WHERE vsch_vtem_ID = ? AND vsch_grp_ID = ?", [
            teamId,
            classId,
        ]).then((result) => expect(Number(result.rows[0].n)).to.eq(1));
    });

    it("shows the portal's team leader the warning without the button", () => {
        openSchedulesTab(`/portal/teams/${teamId}`);
        openGenerateDialog(emptyScheduleId);
        cy.intercept("POST", `**/api/ministries/schedules/${emptyScheduleId}/generate`).as("generate");
        cy.get("#generate-form-save").click();
        cy.wait("@generate").its("response.body.noEvents").should("eq", true);
        cy.get("#generate-form-warning")
            .should("be.visible")
            .and("contain", `No events on the calendar use ${EMPTY_CLASS} as their class`)
            .and("contain", "A coordinator of the ministry can add them");
        cy.get("#generate-form-add-events").should("not.be.visible");
    });
});
