/// <reference types="cypress" />

/**
 * Volunteer v2 — the ministry page's Calendar tab (D24), the headcount card on an occurrence
 * page (D26) and the class defaults of a class-linked team (D23 d). Since D33 the New event dialog
 * only creates events, then asks whether to staff them: one event opens Staff an event, a series
 * goes to the schedules that follow it, or opens Add schedule for it. Design §5.4, §5.5.
 *
 * Fixtures go in through the admin API, counts straight into `eventcounts_evtcnt`. Order in
 * every hook is fixture → login → visit, because cy.request() rotates the PHP session cookie.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const MINISTRIES_URL = "/api/ministries/ministries";
const GROUPS_URL = "/api/groups";

const PREFIX = "UIMEVT24";
const MINISTRY_NAME = `${PREFIX} Grounds`;
const CLASS_NAME = `${PREFIX} Faith City`;
const ONE_OFF = `${PREFIX} Workday`;
const SERIES = `${PREFIX} Breakfast`;
const LATE_SERIES = `${PREFIX} Late Breakfast`;
const PICNIC = `${PREFIX} Picnic`;
const PAST = `${PREFIX} Spring Cleanup`;
const POOL_MEMBER = 8;
const WEEKDAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

let ministryId = 0;
let calendarId = 0;
let teamId = 0;
let positionId = 0;
let classId = 0;

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

/** `YYYY-MM-DD` as the page's shortDate() writes it. */
function shortDate(iso) {
    const date = new Date(`${iso}T12:00:00`);
    return date.toLocaleDateString(undefined, {
        month: "short",
        day: "numeric",
        year: date.getFullYear() === new Date().getFullYear() ? undefined : "numeric",
    });
}

/** `YYYY-MM-DD` at `HH:MM` as the page's shortDateTime() writes it. */
function shortDateTime(iso, time) {
    const date = new Date(`${iso}T${time}:00`);
    return date.toLocaleString(undefined, {
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
        year: date.getFullYear() === new Date().getFullYear() ? undefined : "numeric",
    });
}

/** `HH:MM` as the page's shortTime() writes it. */
function shortTime(time) {
    return new Date(`2000-01-01T${time}:00`).toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" });
}

function oneYearAfter(iso) {
    const date = new Date(`${iso}T12:00:00`);
    date.setFullYear(date.getFullYear() + 1);
    const pad = (n) => String(n).padStart(2, "0");
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** How many days in [from, to] fall on the weekday of day `of`. */
function weekdaysBetween(of, from, to) {
    let count = 0;
    for (let offset = from; offset <= to; offset++) {
        if (localDate(offset).getDay() === localDate(of).getDay()) {
            count++;
        }
    }
    return count;
}

/** Fill New recurring event for a weekly series on the weekday of day `from`. */
function fillWeekly(title, from, to) {
    cy.get("#ministry-event-add-series-btn").click();
    // The dialog opens once its choices have loaded.
    cy.get("#ministryEventModal", { timeout: 15000 }).should("be.visible");
    cy.get("#ministry-event-form-title").should("have.focus").type(title);
    cy.get("#ministry-event-form-type").select("Church Service");
    cy.get("#ministry-event-form-recur-type").select("weekly");
    cy.get("#ministry-event-form-dow").select(WEEKDAYS[localDate(from).getDay()]);
    cy.get("#ministry-event-form-range-start").clear().type(isoDate(from));
    cy.get("#ministry-event-form-range-end").clear().type(isoDate(to));
}

/** The question asked after Create (D33). */
function staffPrompt() {
    return cy.get(".ministry-event-staff-prompt", { timeout: 15000 }).should("be.visible");
}

function eventIdTitled(title) {
    return cy
        .dbQuery("SELECT event_id FROM events_event WHERE event_title = ? ORDER BY event_start", [title])
        .then((result) => result.rows.map((row) => Number(row.event_id)));
}

function addCounts(eventId, total, members) {
    cy.dbQuery(
        `INSERT INTO eventcounts_evtcnt (evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes)
         VALUES (?, 1, 'Total', ?, ''), (?, 2, 'Members', ?, '')`,
        [eventId, total, eventId, members],
    );
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
    cy.dbQuery(
        "DELETE c FROM eventcounts_evtcnt c JOIN events_event e ON e.event_id = c.evtcnt_eventid WHERE e.event_title LIKE ?",
        [`${PREFIX}%`],
    );
    cy.dbQuery("DELETE FROM events_event WHERE event_title LIKE ?", [`${PREFIX}%`]);
    admin("GET", `${GROUPS_URL}/`, null).then((resp) => {
        for (const group of resp.body) {
            if (String(group.Name).startsWith(PREFIX)) {
                admin("DELETE", `${GROUPS_URL}/${group.Id}`, null, [200, 404]);
            }
        }
    });
}

function openCalendarTab() {
    cy.visit(`/ministries/${ministryId}`);
    cy.get("#nav-item-calendar").click();
    cy.get("#ministry-events-loading").should("not.be.visible");
}

/** Open a row's action menu and pick one of its items. */
function rowAction(title, selector) {
    cy.contains("#volunteerMinistryEventsTable tbody tr", title).first().find("[data-bs-toggle='dropdown']").click();
    cy.contains("#volunteerMinistryEventsTable tbody tr", title).first().find(selector).should("be.visible").click();
}

describe("Volunteer v2 — the ministry Calendar tab (D24) and the headcount card (D26)", () => {
    before(() => {
        admin("POST", SETTING_URL, { value: "v2" });
        cleanupFixtures();

        admin("POST", MINISTRIES_URL, { name: MINISTRY_NAME, description: "Calendar tab fixture", sundaySchool: true }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
            calendarId = resp.body.calendarId;
            admin("GET", `${MINISTRIES_URL}/${ministryId}`).then((detail) => {
                teamId = detail.body.teams[0].id;
                admin("POST", `${MINISTRIES_URL}/${ministryId}/positions`, { name: `${PREFIX} Cook`, teamId }, 201).then(
                    (position) => {
                        positionId = position.body.position.id;
                        admin("POST", `/api/ministries/positions/${positionId}/qualifications`, { personId: POOL_MEMBER }, [
                            200, 201,
                        ]);
                    },
                );
            });
        });
        admin("POST", `${GROUPS_URL}/`, { groupName: CLASS_NAME, isSundaySchool: true }).then((resp) => {
            classId = resp.body.Id;
        });
        cy.then(() => {
            admin("POST", `/api/ministries/teams/${teamId}`, { classGroupId: classId });
        });
    });

    after(() => {
        cleanupFixtures();
        admin("POST", SETTING_URL, { value: "v1" });
    });

    beforeEach(() => {
        freshAdminLogin();
    });

    it("sits next to Occurrences and starts empty", () => {
        cy.visit(`/ministries/${ministryId}`);
        cy.get("#volunteer-ministry-tabs .nav-link").then(($tabs) => {
            const labels = [...$tabs].map((el) => el.textContent.trim());
            expect(labels.indexOf("Calendar")).to.eq(labels.indexOf("Occurrences") + 1);
        });
        cy.get("#nav-item-calendar").click();
        cy.get("#ministry-events-empty").should("be.visible");
        cy.get("#ministry-events-empty-upcoming").should("be.visible");
        cy.get("#ministry-events-past").should("not.be.checked");
    });

    it("creates a one-off event on the ministry's own calendar", () => {
        openCalendarTab();
        cy.get("#ministry-event-add-btn").click();
        cy.get("#ministryEventModal").should("be.visible");
        cy.get("#ministryEventModalTitle").should("have.text", "New event");
        cy.get("#ministry-event-form-title").should("have.focus").type(ONE_OFF);
        cy.get("#ministry-event-form-type").select("Church Service");
        cy.get("#ministry-event-form-date").clear().type(isoDate(4));
        cy.get("#ministry-event-form-start-time").clear().type("09:00");
        cy.get("#ministry-event-form-end-time").clear().type("12:00");
        cy.get(`#ministry-event-form-calendars .ministry-event-calendar[value="${calendarId}"]`).should("be.checked");
        cy.get("#ministry-event-form-series-fields").should("not.be.visible");
        cy.get("#ministryEventModal").should("not.contain", "Staff these events");
        cy.get("#ministry-event-form-staff-toggle, #ministry-event-form-team, #ministry-event-form-needs").should("not.exist");

        cy.intercept("POST", `**/api/ministries/ministries/${ministryId}/events`).as("create");
        cy.get("#ministry-event-form-save").click();
        cy.wait("@create").then(({ request, response }) => {
            expect(request.body).to.include({ title: ONE_OFF, eventTypeId: 1, date: isoDate(4), startTime: "09:00", endTime: "12:00" });
            expect(request.body.calendarIds).to.deep.eq([calendarId]);
            expect(request.body).not.to.have.property("staff");
            expect(response.statusCode).to.eq(201);
        });
        cy.get("#ministryEventModal").should("not.be.visible");

        staffPrompt().should("contain", `${ONE_OFF} on ${shortDate(isoDate(4))} created. Staff it now?`);
        cy.contains(".ministry-event-staff-prompt button", "Not now").click();
        cy.get(".ministry-event-staff-prompt").should("not.exist");
        cy.get("#staffEventModal").should("not.be.visible");

        cy.contains("#volunteerMinistryEventsTable tbody tr", ONE_OFF).within(() => {
            cy.contains(`${shortDateTime(isoDate(4), "09:00")} – ${shortTime("12:00")}`);
            cy.get(`td[data-order^="${isoDate(4)} 09:00"]`).should("exist");
            cy.contains(".badge", MINISTRY_NAME);
            cy.contains("Not staffed");
        });
    });

    it("starts a series' Last date a year after its first date, follows the first date until it is chosen, and refuses a blank", () => {
        openCalendarTab();
        cy.get("#ministry-event-add-series-btn").click();
        cy.get("#ministryEventModal").should("be.visible");
        cy.get("#ministry-event-form-title").should("have.focus");
        cy.get("#ministry-event-form-range-start").should("have.value", isoDate(1));
        cy.get("#ministry-event-form-range-end").should("have.value", oneYearAfter(isoDate(1)));
        cy.get("#ministry-event-form-range-end").should("have.attr", "required");

        cy.get("#ministry-event-form-range-start").clear().type(isoDate(10));
        cy.get("#ministry-event-form-range-end").should("have.value", oneYearAfter(isoDate(10)));

        cy.get("#ministry-event-form-range-end").clear().blur();
        cy.get("#ministry-event-form-range-end").should("have.value", oneYearAfter(isoDate(10)));
        cy.get("#ministry-event-form-range-end-note")
            .should("be.visible")
            .and("contain", `A recurring event needs a last date, so it is back to ${shortDate(oneYearAfter(isoDate(10)))}.`);

        cy.get("#ministry-event-form-range-end").clear().type(isoDate(40)).blur();
        cy.get("#ministry-event-form-range-end-note").should("not.be.visible");
        cy.get("#ministry-event-form-range-start").clear().type(isoDate(12));
        cy.get("#ministry-event-form-range-end").should("have.value", isoDate(40));
        cy.get("#ministryEventModal .btn-outline-secondary[data-bs-dismiss='modal']").click();
        cy.get("#ministryEventModal").should("not.be.visible");
    });

    it("creates a weekly series and, with no schedule following it, opens Add schedule for its class; Save lands on Occurrences (D33)", () => {
        const expected = weekdaysBetween(3, 1, 29);

        openCalendarTab();
        fillWeekly(SERIES, 3, 29);
        cy.get("#ministry-event-form-preview").should("be.visible").and("contain", `Creates ${expected} events`);
        cy.get("#ministry-event-form-class").should("have.value", "").select(String(classId));

        cy.intercept("POST", `**/api/ministries/ministries/${ministryId}/events`).as("create");
        cy.intercept("POST", `**/api/ministries/ministries/${ministryId}/events/staff`).as("staff");
        cy.get("#ministry-event-form-save").click();
        cy.wait("@create").then(({ request, response }) => {
            expect(request.body.recurrence).to.deep.eq({ type: "weekly", dow: WEEKDAYS[localDate(3).getDay()] });
            expect(request.body.linkedGroupId).to.eq(classId);
            expect(response.body.events).to.have.length(expected);
        });
        cy.get("#ministryEventModal").should("not.be.visible");

        staffPrompt().should("contain", `${expected} ${SERIES} events created. Staff them now?`);
        cy.contains(".ministry-event-staff-prompt button", "Staff them").click();
        cy.wait("@staff").its("response.body.schedules").should("deep.eq", []);

        cy.get("#nav-item-schedules").should("have.class", "active");
        cy.get("#scheduleModal").should("be.visible");
        cy.get("#scheduleModalTitle").should("have.text", "Add schedule");
        cy.get("#schedule-form-name").should("have.value", SERIES);
        cy.get("#schedule-form-team").should("have.value", String(teamId));
        cy.get("#schedule-form-link-mode").should("have.value", "class");
        cy.get("#schedule-form-group").should("have.value", String(classId));
        cy.get(`#schedule-form-needs .volunteer-need-row[data-position-id="${positionId}"] .volunteer-need-check`).should("be.checked");

        cy.intercept("POST", `**/api/ministries/ministries/${ministryId}/schedules`).as("schedule");
        cy.get("#schedule-form-save").click();
        cy.wait("@schedule").its("response.body.generated").should("include", { created: expected, noEvents: false });
        cy.get("#scheduleModal").should("not.be.visible");
        cy.get(".notyf__toast--success").should("contain", `Schedule "${SERIES}" created: ${expected} occurrences`);

        cy.get("#nav-item-occurrences").should("have.class", "active");
        cy.get("#occurrence-event-filter").should("have.value", SERIES);
        cy.get("#occurrence-team-filter").should("have.value", String(teamId));
        cy.get("#volunteerOccurrencesTable tbody tr").should("have.length", expected);
    });

    it("sends a later series to the schedule that follows its class and shows what that made (D33)", () => {
        const expected = weekdaysBetween(31, 31, 50);

        openCalendarTab();
        fillWeekly(LATE_SERIES, 31, 50);
        cy.get("#ministry-event-form-class").select(String(classId));
        cy.intercept("POST", `**/api/ministries/ministries/${ministryId}/events/staff`).as("staff");
        cy.get("#ministry-event-form-save").click();
        cy.get("#ministryEventModal").should("not.be.visible");

        staffPrompt().should("contain", `${expected} ${LATE_SERIES} events created. Staff them now?`);
        cy.contains(".ministry-event-staff-prompt button", "Staff them").click();
        cy.wait("@staff").then(({ response }) => {
            expect(response.body.schedules.map((run) => [run.schedule.name, run.created])).to.deep.eq([[SERIES, expected]]);
        });
        cy.get(".notyf__toast--success").should("contain", `"${SERIES}": ${expected} occurrences created`);
        cy.get("#nav-item-occurrences").should("have.class", "active");
        cy.get("#occurrence-event-filter").should("have.value", LATE_SERIES);
        cy.get("#volunteerOccurrencesTable tbody tr").should("have.length", expected);
        cy.get("#scheduleModal").should("not.be.visible");
    });

    it("opens Staff an event with a new one-time event chosen when asked to staff it", () => {
        openCalendarTab();
        cy.get("#ministry-event-add-btn").click();
        cy.get("#ministryEventModal").should("be.visible");
        cy.get("#ministry-event-form-title").should("have.focus").type(PICNIC);
        cy.get("#ministry-event-form-date").clear().type(isoDate(6));
        cy.get("#ministry-event-form-save").click();
        cy.get("#ministryEventModal").should("not.be.visible");

        staffPrompt().should("contain", `${PICNIC} on ${shortDate(isoDate(6))} created. Staff it now?`);
        cy.contains(".ministry-event-staff-prompt button", "Staff them").click();
        eventIdTitled(PICNIC).then(([eventId]) => {
            cy.get("#staffEventModal").should("be.visible");
            cy.get("#staff-event-form-event").should("have.value", String(eventId));
        });
        cy.get("#staff-event-form-save").click();
        cy.get("#staffEventModal").should("not.be.visible");
        cy.contains("#volunteerMinistryEventsTable tbody tr", PICNIC)
            .find(".ministry-event-staffing")
            .should("have.attr", "data-status", "gap");
    });

    it("staffs an event from its row through the Staff an event dialog", () => {
        openCalendarTab();
        eventIdTitled(ONE_OFF).then(([eventId]) => {
            rowAction(ONE_OFF, ".ministry-event-staff");
            cy.get("#staffEventModal").should("be.visible");
            cy.get("#staff-event-form-event").should("have.value", String(eventId));
            cy.get("#staff-event-form-save").click();
            cy.get("#staffEventModal").should("not.be.visible");
        });
        cy.contains("#volunteerMinistryEventsTable tbody tr", ONE_OFF)
            .find(".ministry-event-staffing")
            .should("have.attr", "data-status", "gap");
    });

    it("links each row to the core event editor and view", () => {
        openCalendarTab();
        eventIdTitled(ONE_OFF).then(([eventId]) => {
            cy.contains("#volunteerMinistryEventsTable tbody tr", ONE_OFF).within(() => {
                cy.get(`a.dropdown-item[href$="/event/editor/${eventId}"]`).should("exist");
                cy.get(`a.dropdown-item[href$="/event/view/${eventId}"]`).should("exist");
            });
        });
    });

    it("shows past events with their headcount behind the switch", () => {
        admin(
            "POST",
            `${MINISTRIES_URL}/${ministryId}/events`,
            { title: PAST, eventTypeId: 1, date: isoDate(-3), startTime: "08:00", endTime: "11:00" },
            201,
        ).then((resp) => {
            addCounts(resp.body.events[0].id, 30, 12);
        });
        freshAdminLogin();
        openCalendarTab();
        cy.get("#volunteerMinistryEventsTable tbody").should("contain", ONE_OFF).and("not.contain", PAST);

        cy.get("#ministry-events-past").check({ force: true });
        cy.contains("#volunteerMinistryEventsTable tbody tr", PAST).within(() => {
            cy.get(".ministry-event-headcount").should("have.text", "Headcount: 42");
            cy.get(".ministry-event-staff").should("not.exist");
        });
        cy.get("#volunteerMinistryEventsTable tbody").should("not.contain", ONE_OFF);

        cy.get("#ministry-events-past").uncheck({ force: true });
        cy.get("#volunteerMinistryEventsTable tbody").should("contain", ONE_OFF);
    });

    it("shows the anchored event's headcount on the occurrence page, with the class's check-ins", () => {
        eventIdTitled(SERIES).then(([firstId]) => {
            addCounts(firstId, 20, 7);
        });
        freshAdminLogin();
        openCalendarTab();
        cy.contains("#volunteerMinistryEventsTable tbody tr", SERIES).first().find(".ministry-event-staffing").click();

        cy.url().should("include", "/ministries/occurrences/");
        cy.get("#occurrence-headcount").should("be.visible");
        cy.get("#occurrence-headcount-counts").should("contain", "Total").and("contain", "Members");
        cy.get("#occurrence-headcount-total").should("have.text", "27");
        cy.get("#occurrence-headcount-checkins")
            .invoke("text")
            .should("match", /Checked in: 0 of \d+ on the class roster/);
        eventIdTitled(SERIES).then(([firstId]) => {
            cy.get("#occurrence-headcount-edit").should("have.attr", "href").and("match", new RegExp(`/event/editor/${firstId}$`));
        });
    });

    it("shows the same card, read-only, on the portal's team occurrence page", () => {
        eventIdTitled(SERIES).then(([firstId]) => {
            cy.dbQuery("SELECT vocc_ID FROM volunteer_occurrence_vocc WHERE vocc_event_id = ?", [firstId]).then((result) => {
                cy.visit(`/portal/teams/${teamId}/occurrences/${result.rows[0].vocc_ID}`);
            });
            cy.get("#occurrence-headcount").should("be.visible");
            cy.get("#occurrence-headcount-total").should("have.text", "27");
            cy.get("#occurrence-headcount-edit").should("have.attr", "href").and("match", new RegExp(`/event/editor/${firstId}$`));
        });
    });

    it("says when no headcount is recorded yet", () => {
        openCalendarTab();
        cy.contains("#volunteerMinistryEventsTable tbody tr", ONE_OFF).find(".ministry-event-staffing").click();
        cy.get("#occurrence-headcount-empty").should("contain", "No headcount recorded yet");
        cy.get("#occurrence-headcount-checkins").should("not.exist");
    });

    it("starts a new schedule for the class-linked team on its class (D23 d)", () => {
        cy.visit(`/ministries/${ministryId}`);
        cy.get("#nav-item-schedules").click();
        cy.get("#schedules-loading").should("not.be.visible");
        cy.get("#schedule-add-btn").click();
        cy.get("#scheduleModal").should("be.visible");
        cy.get("#schedule-form-name").should("have.focus");
        cy.get("#schedule-form-link-mode").should("have.value", "class");
        cy.get("#schedule-form-group").should("have.value", String(classId));
        cy.get("#schedule-form-group-row").should("be.visible");

        cy.get("#schedule-form-link-mode").select("event_type");
        cy.get("#schedule-form-group-row").should("not.be.visible");
    });
});
