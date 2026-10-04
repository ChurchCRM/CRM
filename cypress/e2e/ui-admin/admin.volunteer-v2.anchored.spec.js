/// <reference types="cypress" />

/**
 * Volunteer v2 — the schedule dialog's three sources and offsets, and the Staff an
 * event dialog (D20, D21, D22; design §5.4).
 *
 * Fixtures go in through the admin API and, for the calendar events the dialogs pick
 * from, straight into `events_event`. Order in every hook is fixture → login → visit,
 * because cy.request() rotates the PHP session cookie (cypress-testing.md).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/ministries";

const PREFIX = "UIANCHOR";
const MINISTRY_NAME = `${PREFIX} Children`;
const CLASS_NAME = `${PREFIX} Faith City`;
const MINISTRY_EVENT = `${PREFIX} Workday`;
const STAFF_EVENT = `${PREFIX} Harvest Supper`;

let ministryId = 0;
let teamId = 0;
let teamName = "";
let classGroupId = 0;

function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.testEnv("admin.username"));
    cy.get("input[name=Password]").type(Cypress.testEnv("admin.password") + "{enter}");
    cy.url().then((url) => {
        if (url.includes("/session/begin")) {
            cy.get("input[name=User]").clear().type(Cypress.testEnv("admin.username"));
            cy.get("input[name=Password]").clear().type(Cypress.testEnv("admin.password") + "{enter}");
        }
    });
    cy.url().should("not.include", "/session/begin");
}

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function insertEvent(title, offsetDays, startTime, endTime, ministry = null) {
    return cy
        .dbQuery(
            `INSERT INTO events_event (event_type, event_title, event_desc, event_text, event_start, event_end, inactive, event_ministry_id)
             VALUES (1, ?, '', '', ?, ?, 0, ?)`,
            [title, `${isoDate(offsetDays)} ${startTime}`, `${isoDate(offsetDays)} ${endTime}`, ministry],
        )
        .then((result) => result.rows.insertId);
}

function cleanupFixtures() {
    adminApi("GET", `${VOLUNTEER_URL}/ministries`, null, 200).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (!ministry.name.startsWith(PREFIX)) {
                continue;
            }
            adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministry.id}`, { active: false }, [200, 404]);
            adminApi("DELETE", `${VOLUNTEER_URL}/ministries/${ministry.id}`, null, [200, 404, 409]);
        }
    });
    cy.dbQuery(
        "DELETE ea FROM event_audience ea JOIN events_event e ON e.event_id = ea.event_id WHERE e.event_title LIKE ?",
        [`${PREFIX}%`],
    );
    cy.dbQuery("DELETE FROM events_event WHERE event_title LIKE ?", [`${PREFIX}%`]);
    cy.dbQuery("DELETE FROM group_grp WHERE grp_Name LIKE ?", [`${PREFIX}%`]);
}

function openSchedulesTab() {
    cy.visit(`/ministries/${ministryId}`);
    cy.get("#nav-item-schedules").click();
    cy.get("#schedules-loading").should("not.be.visible");
}

function openAddSchedule() {
    cy.get("#schedule-add-btn").click();
    cy.get("#scheduleModal").should("be.visible");
    cy.get("#schedule-form-name").should("have.focus");
}

before(() => {
    cy.rememberTestEnv(["admin.username", "admin.password"]);
    cy.useChurchTimeZone();
});

after(() => {
    cy.useHostTimeZone();
});

describe("Volunteer v2 — where a schedule's dates come from, and Staff an event", () => {
    before(() => {
        adminApi("POST", SETTING_URL, { value: "v2" }, 200);
        cleanupFixtures();

        adminApi("POST", `${VOLUNTEER_URL}/ministries`, { name: MINISTRY_NAME, description: "anchored UI fixture", sundaySchool: true }, 201).then(
            (resp) => {
                ministryId = resp.body.ministry.id;
            },
        );
        cy.then(() => {
            adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`).then((resp) => {
                teamId = resp.body.teams[0].id;
                teamName = resp.body.teams[0].name;
                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                    { name: `${PREFIX} Teacher`, teamId, order: 1 },
                    201,
                );
            });
        });

        cy.dbQuery(
            `INSERT INTO group_grp (grp_Type, grp_RoleListID, grp_DefaultRole, grp_Name, grp_Description, grp_hasSpecialProps, grp_active, grp_include_email_export)
             VALUES (4, 0, 0, ?, '', 0, 1, 1)`,
            [CLASS_NAME],
        ).then((result) => {
            classGroupId = result.rows.insertId;
            [3, 10].forEach((offset) => {
                insertEvent(CLASS_NAME, offset, "09:30:00", "10:30:00").then((eventId) => {
                    cy.dbQuery("INSERT INTO event_audience (event_id, group_id) VALUES (?, ?)", [eventId, classGroupId]);
                });
            });
        });
        cy.then(() => {
            insertEvent(MINISTRY_EVENT, 8, "08:00:00", "12:00:00", ministryId);
            insertEvent(STAFF_EVENT, 4, "18:00:00", "20:00:00");
        });
    });

    after(() => {
        cleanupFixtures();
        adminApi("POST", SETTING_URL, { value: "v1" }, 200);
    });

    beforeEach(() => {
        freshAdminLogin();
    });

    it("offers three sources and no weekly pattern of its own", () => {
        openSchedulesTab();
        openAddSchedule();

        cy.get("#schedule-form-link-mode option").then(($options) => {
            expect([...$options].map((option) => option.value)).to.deep.eq(["event_type", "class", "ministry"]);
        });
        cy.get("#schedule-form-link-mode option[value=class]").should("contain", "A class's meetings");
        cy.get("#schedule-form-link-mode option[value=ministry]").should("contain", "This ministry's events");
        cy.get("#schedule-form-dow").should("not.exist");
        cy.get("#schedule-form-start-time").should("not.exist");
        cy.get("#schedule-form-standalone-rows").should("not.exist");

        cy.get("#schedule-form-offsets").should("contain", "Volunteers start").and("contain", "Volunteers finish");
        cy.get("#schedule-form-start-offset").should("have.value", "0");
        cy.get("#schedule-form-end-offset-direction").should("have.value", "after");
    });

    it("creates a class schedule from the class picker, with offsets", () => {
        openSchedulesTab();
        openAddSchedule();
        cy.get("#schedule-form-name").type(`${PREFIX} Faith City Volunteers`);
        cy.get("#schedule-form-link-mode").select("class");
        cy.get("#schedule-form-event-type-row").should("not.be.visible");
        cy.get("#schedule-form-title-filter-row").should("not.be.visible");
        cy.get("#schedule-form-group-row").should("be.visible");
        cy.get(`#schedule-form-group option[value="${classGroupId}"]`).should("contain", `${CLASS_NAME} (2 upcoming)`);
        cy.get("#schedule-form-group").select(String(classGroupId));

        cy.get("#schedule-form-start-offset").clear().type("45");
        cy.get("#schedule-form-start-offset-direction").select("before");
        cy.get("#schedule-form-end-offset").clear().type("15");
        cy.get("#schedule-form-end-offset-direction").select("after");

        cy.intercept("POST", `**/api/ministries/ministries/${ministryId}/schedules`).as("create");
        cy.get("#schedule-form-save").click();
        cy.wait("@create").then(({ request, response }) => {
            expect(response.statusCode, JSON.stringify(response.body)).to.eq(201);
            expect(request.body.linkMode).to.eq("class");
            expect(request.body.groupId).to.eq(classGroupId);
            expect(request.body.startOffsetMinutes).to.eq(-45);
            expect(request.body.endOffsetMinutes).to.eq(15);
            expect(request.body).to.not.have.any.keys("recurType", "recurDow", "startTime", "endTime", "eventTypeId");
        });
        cy.get("#scheduleModal").should("not.be.visible");

        cy.get("#volunteerSchedulesTable thead").should("contain", "Which events");
        cy.get("#volunteerSchedulesTable tbody tr")
            .contains("tr", `${PREFIX} Faith City Volunteers`)
            .within(() => {
                cy.get("td").eq(1).should("contain", `Sunday School: ${CLASS_NAME}`);
                cy.get("td").eq(1).should("contain", "Volunteers start 45 minutes before the event starts.");
                cy.get("td").eq(1).should("contain", "They finish 15 minutes after the event ends.");
            });
    });

    it("pre-fills the source, the class and the offsets on edit", () => {
        openSchedulesTab();
        cy.get("#volunteerSchedulesTable tbody tr")
            .contains("tr", `${PREFIX} Faith City Volunteers`)
            .find(".volunteer-schedule-edit")
            .click({ force: true });
        cy.get("#scheduleModal").should("be.visible");
        cy.get("#schedule-form-link-mode").should("have.value", "class");
        cy.get("#schedule-form-group").should("have.value", String(classGroupId));
        cy.get("#schedule-form-start-offset").should("have.value", "45");
        cy.get("#schedule-form-start-offset-direction").should("have.value", "before");
        cy.get("#schedule-form-end-offset").should("have.value", "15");
        cy.get("#schedule-form-end-offset-direction").should("have.value", "after");
    });

    it("creates a schedule of this ministry's events from the title picker", () => {
        openSchedulesTab();
        openAddSchedule();
        cy.get("#schedule-form-name").type(`${PREFIX} Workday Crew`);
        cy.get("#schedule-form-link-mode").select("ministry");
        cy.get("#schedule-form-event-type-row").should("not.be.visible");
        cy.get("#schedule-form-title-filter-row").should("be.visible");
        cy.get("#schedule-form-title-filter option").first().should("be.disabled").and("contain", "Choose an event");
        cy.get(`#schedule-form-title-filter option[value="${MINISTRY_EVENT}"]`).should("contain", "(1 upcoming)");
        cy.get("#schedule-form-title-filter").select(MINISTRY_EVENT);

        cy.intercept("POST", `**/api/ministries/ministries/${ministryId}/schedules`).as("create");
        cy.get("#schedule-form-save").click();
        cy.wait("@create").then(({ request, response }) => {
            expect(response.statusCode, JSON.stringify(response.body)).to.eq(201);
            expect(request.body.linkMode).to.eq("ministry");
            expect(request.body.titleFilter).to.eq(MINISTRY_EVENT);
        });
        cy.get("#volunteerSchedulesTable tbody tr")
            .contains("tr", `${PREFIX} Workday Crew`)
            .find("td")
            .eq(1)
            .should("contain", `This ministry's events titled ${MINISTRY_EVENT}`);
    });

    it("staffs one event from the Staff an event dialog, and refuses a second in the dialog", () => {
        cy.visit(`/ministries/${ministryId}`);
        cy.get("#nav-item-occurrences").click();
        cy.get("#occurrences-loading").should("not.be.visible");
        cy.get("#occurrences-add-btn").should("not.exist");

        cy.get("#occurrences-staff-event-btn").click();
        cy.get("#staffEventModal").should("be.visible");
        cy.get("#staff-event-form-search").should("have.focus");
        cy.get("#staff-event-form-offsets").should("contain", "Volunteers start");
        cy.get("#staff-event-form-search").type("Harvest");
        cy.get("#staff-event-form-date").type(isoDate(4));
        cy.get("#staff-event-form-event option").should("have.length", 2);
        cy.get("#staff-event-form-event option").eq(1).should("contain", STAFF_EVENT).and("contain", "(Church Service)");
        cy.get("#staff-event-form-event option").eq(1).then(($option) => {
            cy.get("#staff-event-form-event").select(String($option.val()));
        });
        cy.get("#staff-event-form-team").select(teamName);
        cy.get("#staff-event-form-needs .volunteer-need-row").should("have.length", 1);

        cy.intercept("POST", `**/api/ministries/ministries/${ministryId}/staffed-events`).as("staff");
        cy.get("#staff-event-form-save").click();
        cy.wait("@staff").its("response.statusCode").should("eq", 201);
        cy.get("#staffEventModal").should("not.be.visible");
        cy.get("#volunteerOccurrencesTable tbody").should("contain", STAFF_EVENT).and("contain", "single event");

        // Again: the picker says the team already staffs it, and the server's 409 is
        // shown in the dialog rather than lost.
        cy.get("#occurrences-staff-event-btn").click();
        cy.get("#staffEventModal").should("be.visible");
        cy.get("#staff-event-form-search").should("have.focus").type("Harvest");
        cy.get("#staff-event-form-event option").should("have.length", 2);
        cy.get("#staff-event-form-event option").eq(1).should("contain", "already staffed by this team");
        cy.get("#staff-event-form-event option").eq(1).then(($option) => {
            cy.get("#staff-event-form-event").select(String($option.val()));
        });
        cy.get("#staff-event-form-save").click();
        cy.get("#staff-event-form-error").should("be.visible").and("contain", "already staffing this event");
        cy.get("#staffEventModal").should("be.visible");
    });

    it("asks for an event before staffing", () => {
        cy.visit(`/ministries/${ministryId}`);
        cy.get("#nav-item-occurrences").click();
        cy.get("#occurrences-loading").should("not.be.visible");
        cy.get("#occurrences-staff-event-btn").click();
        cy.get("#staffEventModal").should("be.visible");
        cy.get("#staff-event-form-search").should("have.focus");
        cy.get("#staff-event-form-save").click();
        cy.get("#staff-event-form-error").should("be.visible").and("contain", "Choose the event to staff");
    });
});
