/// <reference types="cypress" />

/**
 * Volunteer v2 D31 on screen — the schedule dialog offers only events that exist (no "any event",
 * a class with no meetings disabled), the Generate dialog speaks of the horizon in weeks, and a
 * ministry's New event starts on the default event type. Design §0.8 D31, §5.4.
 *
 * Fixtures go in through the admin API. Order in every hook is fixture → login → visit, because
 * cy.request() rotates the PHP session cookie.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const HORIZON_URL = "/admin/api/system/config/iVolunteerSchedulingHorizonWeeks";
const DEFAULT_TYPE_URL = "/admin/api/system/config/iVolunteerDefaultEventTypeId";
const MINISTRIES_URL = "/api/ministries/ministries";
const GROUPS_URL = "/api/groups";

const PREFIX = "UIHOR31";
const WORKDAY = `${PREFIX} Workday`;
const FAITH_CITY = `${PREFIX} Faith City`;
const EMPTY_CLASS = `${PREFIX} Empty Class`;
const CHURCH_SERVICE_TYPE = 1;
const OTHER_TYPE = 3;
const WEEKDAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

let ministryId = 0;
let teamId = 0;
let faithCityId = 0;
let emptyClassId = 0;
let crewId = 0;
let shortCrewId = 0;

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

/** The screen's own short date (webpack/ministries/components/ui.ts shortDate). */
function shortDate(offsetDays) {
    return localDate(offsetDays).toLocaleDateString(undefined, { month: "short", day: "numeric" });
}

function weekly(title, from, to, extra = {}) {
    return {
        title,
        eventTypeId: CHURCH_SERVICE_TYPE,
        recurrence: { type: "weekly", dow: WEEKDAYS[localDate(from).getDay()] },
        rangeStart: isoDate(from),
        rangeEnd: isoDate(to),
        startTime: "09:30",
        endTime: "10:30",
        ...extra,
    };
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

function openAddSchedule() {
    cy.get("#schedule-add-btn").click();
    cy.get("#scheduleModal").should("be.visible");
    cy.get("#schedule-form-name").should("have.focus");
}

function openGenerateDialog(id) {
    cy.get(`.volunteer-schedule-generate[data-schedule-id="${id}"]`, { timeout: 15000 })
        .closest("tr")
        .find("[data-bs-toggle='dropdown']")
        .click();
    cy.get(`.volunteer-schedule-generate[data-schedule-id="${id}"]`).should("be.visible").click();
    cy.get("#generateOccurrencesModal").should("be.visible");
}

function openNewEvent() {
    cy.visit(`/ministries/${ministryId}`);
    cy.get("#nav-item-calendar").click();
    cy.get("#ministry-events-loading").should("not.be.visible");
    cy.get("#ministry-event-add-btn").click();
    cy.get("#ministryEventModal").should("be.visible");
    cy.get("#ministry-event-form-title").should("have.focus");
}

describe("Volunteer v2 D31 — schedules follow events that exist, up to the horizon", () => {
    before(() => {
        admin("POST", SETTING_URL, { value: "v2" });
        admin("POST", HORIZON_URL, { value: "8" });
        admin("POST", DEFAULT_TYPE_URL, { value: "" });
        cleanupFixtures();

        admin("POST", MINISTRIES_URL, { name: `${PREFIX} Children`, description: "D31 fixture", sundaySchool: true }, 201).then(
            (resp) => {
                ministryId = resp.body.ministry.id;
                admin("GET", `${MINISTRIES_URL}/${ministryId}`).then((detail) => {
                    teamId = detail.body.teams[0].id;
                });
            },
        );
        admin("POST", `${GROUPS_URL}/`, { groupName: FAITH_CITY, isSundaySchool: true }).then((resp) => {
            faithCityId = resp.body.Id;
        });
        admin("POST", `${GROUPS_URL}/`, { groupName: EMPTY_CLASS, isSundaySchool: true }).then((resp) => {
            emptyClassId = resp.body.Id;
        });
        cy.then(() => {
            admin("POST", `${MINISTRIES_URL}/${ministryId}/events`, weekly(WORKDAY, 1, 90), 201);
            admin(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/events`,
                weekly(`${PREFIX} Faith City Meeting`, 2, 30, { linkedGroupId: faithCityId }),
                201,
            );
        });
        cy.then(() => {
            admin(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/schedules`,
                { name: `${PREFIX} Workday Crew`, teamId, linkMode: "ministry", titleFilter: WORKDAY, windowStart: isoDate(0) },
                201,
            ).then((resp) => {
                crewId = resp.body.schedule.id;
            });
            admin(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/schedules`,
                {
                    name: `${PREFIX} Short Crew`,
                    teamId,
                    linkMode: "ministry",
                    titleFilter: WORKDAY,
                    windowStart: isoDate(0),
                    windowEnd: isoDate(20),
                },
                201,
            ).then((resp) => {
                shortCrewId = resp.body.schedule.id;
            });
        });
    });

    after(() => {
        cleanupFixtures();
        admin("POST", DEFAULT_TYPE_URL, { value: "" });
        admin("POST", SETTING_URL, { value: "v1" });
    });

    beforeEach(() => {
        freshAdminLogin();
    });

    it("offers no 'any event' choice: the Event picker lists only upcoming titles", () => {
        openSchedulesTab();
        openAddSchedule();
        cy.get("#schedule-form-link-mode").select("ministry");
        cy.get("#schedule-form-title-filter option").first().should("have.value", "").and("be.disabled").and("have.text", "Choose an event");
        cy.get("#schedule-form-title-filter").should("not.contain", "Any of this ministry's events");
        cy.get(`#schedule-form-title-filter option[value="${WORKDAY}"]`).should("contain", "upcoming");

        cy.get("#schedule-form-link-mode").select("event_type");
        cy.get("#schedule-form-event-type").select("Church Service");
        cy.get(`#schedule-form-title-filter option[value="${WORKDAY}"]`).should("exist");
        cy.get("#schedule-form-title-filter").should("not.contain", "Any event of this type");
        cy.get("#schedule-form-title-filter option").first().should("be.disabled");
    });

    it("shows a class with no meetings disabled, and says to add its meetings first", () => {
        openSchedulesTab();
        openAddSchedule();
        cy.get("#schedule-form-link-mode").select("class");
        cy.get(`#schedule-form-group option[value="${emptyClassId}"]`)
            .should("be.disabled")
            .and("have.text", `${EMPTY_CLASS} (no upcoming meetings — add its meetings first)`);
        cy.get(`#schedule-form-group option[value="${faithCityId}"]`).should("not.be.disabled").invoke("text").should("match", /upcoming\)$/);
        cy.get("#schedule-form-group-hint")
            .should("be.visible")
            .and("contain", "Add its meetings first with New recurring event on the Calendar tab");
    });

    it("shows nothing below the event, and no Save, until an event is chosen", () => {
        openSchedulesTab();
        openAddSchedule();
        cy.get("#schedule-form-name").type(`${PREFIX} Nameless`);
        cy.get("#schedule-form-link-mode").select("ministry");
        cy.get(`#schedule-form-title-filter option[value="${WORKDAY}"]`).should("exist");
        cy.get("#schedule-form-details").should("not.be.visible");
        cy.get("#schedule-form-save").should("not.be.visible");
        cy.get("#scheduleModal .modal-footer").contains("Cancel").should("be.visible");
        cy.get("#schedule-form-title-filter").select(WORKDAY);
        cy.get("#schedule-form-details").should("be.visible");
        cy.get("#schedule-form-save").should("be.visible");
    });

    it("says in weeks how far Generate reaches, or that the schedule ends first", () => {
        openSchedulesTab();
        openGenerateDialog(crewId);
        cy.get("#generate-form-intro").should(
            "have.text",
            `Occurrences are created for events in the next 8 weeks (through ${shortDate(56)}).`,
        );

        openSchedulesTab();
        openGenerateDialog(shortCrewId);
        cy.get("#generate-form-intro").should(
            "have.text",
            `Occurrences are created for events through ${shortDate(20)}, when this schedule ends.`,
        );
    });

    it("follows a new horizon in the Generate dialog", () => {
        admin("POST", HORIZON_URL, { value: "3" });
        freshAdminLogin();
        openSchedulesTab();
        openGenerateDialog(crewId);
        cy.get("#generate-form-intro").should("contain", `in the next 3 weeks (through ${shortDate(21)})`);
        admin("POST", HORIZON_URL, { value: "8" });
    });

    it("starts a new ministry event on the default type, and it stays changeable", () => {
        openNewEvent();
        cy.get("#ministry-event-form-type").should("have.value", String(OTHER_TYPE));
        cy.get("#ministry-event-form-type option:selected").should("have.text", "Other");
        cy.get("#ministry-event-form-type").select("Church Service").should("have.value", String(CHURCH_SERVICE_TYPE));

        admin("POST", DEFAULT_TYPE_URL, { value: String(CHURCH_SERVICE_TYPE) });
        freshAdminLogin();
        openNewEvent();
        cy.get("#ministry-event-form-type").should("have.value", String(CHURCH_SERVICE_TYPE));
    });

    it("gives the portal's team leader the same dialog, pointing to a coordinator for missing meetings", () => {
        openSchedulesTab(`/portal/teams/${teamId}`);
        cy.get("#schedule-add-btn").click();
        cy.get("#scheduleModal").should("be.visible");
        cy.get("#schedule-form-link-mode").select("ministry");
        cy.get("#schedule-form-title-filter option").first().should("be.disabled");
        cy.get("#schedule-form-title-filter").should("not.contain", "Any of this ministry's events");
        cy.get("#schedule-form-link-mode").select("class");
        cy.get(`#schedule-form-group option[value="${emptyClassId}"]`).should("be.disabled");
        cy.get("#schedule-form-group-hint").should("be.visible").and("contain", "A coordinator of the ministry adds its meetings");
    });
});
