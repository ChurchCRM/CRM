/// <reference types="cypress" />

/**
 * Member Portal — the calendar page (MP5, #9866).
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §5.3.
 *   - nothing shared → the page says so, in those words
 *   - a church calendar switched on → its events are on the member's calendar,
 *     and a click opens the detail panel
 *   - Birthdays switched on → a first name and a last initial, no age and no
 *     surname
 *   - the Calendar nav entry is behind bPortalShowCalendar, and so is the page
 *
 * Seed persona: user 100, Lena Black (person 100). usr_EditSelf=1 and no admin
 * flag, so she is confined to the portal. The username column is VARCHAR(32),
 * so the seeded address is stored truncated — log in with the 32-char form.
 *
 * Seed facts: calendar 1 is "Public Calendar"; person 5 is Albert Campbell,
 * born on 9 September, which is what the Birthdays assertion reads. The
 * calendar is driven to a fixed day with FullCalendar's own gotoDate() so the
 * spec does not depend on the day it runs.
 *
 * NOTE: every x-api-key request replaces the browser session cookie with an
 * API-token session, so a member step that follows one has to log in again.
 */
const MEMBER_USER = "lena.black.editself.notes@exampl";
const MEMBER_PASSWORD = "changeme";

const CHURCH_CALENDAR_ID = 1;
const CHURCH_CALENDAR_NAME = "Public Calendar";
const BIRTHDAYS_CALENDAR_ID = 0;

/** Albert Campbell, seeded with a 9 September birthday. */
const BIRTHDAY_PERSON_INITIALS = "Albert C.";
const BIRTHDAY_PERSON_FULL_NAME = "Albert Campbell";
const BIRTHDAY_MONTH_DAY = "09-09";

const adminKey = () => Cypress.env("admin.api.key");

const setVisibleCalendars = (visible) =>
    cy.request({
        method: "POST",
        url: "/admin/api/member-portal/calendars",
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { visible },
        failOnStatusCode: false,
    });

const setConfig = (name, value) =>
    cy.request({
        method: "POST",
        url: `/admin/api/system/config/${name}`,
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { value },
        failOnStatusCode: false,
    });

const loginAsMember = () => {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(MEMBER_USER);
    cy.get("input[name=Password]").type(`${MEMBER_PASSWORD}{enter}`);
    cy.url({ timeout: 10000 }).should("include", "/portal");
};

/** Drive FullCalendar to a fixed day, so the spec does not depend on today. */
const gotoDay = (isoDay) => {
    cy.window({ timeout: 15000 }).should((win) => {
        expect(win.CRM.fullcalendar, "the portal calendar is rendered").to.exist;
    });
    cy.window().then((win) => win.CRM.fullcalendar.gotoDate(isoDay));
};

const thisYear = () => new Date().getFullYear();

describe("Member Portal calendar", () => {
    after(() => {
        setVisibleCalendars([]);
        setConfig("bPortalShowCalendar", "1");
    });

    it("Says so when no calendar has been shared", () => {
        setVisibleCalendars([]);
        loginAsMember();

        cy.visit("/portal/calendar");
        cy.get("#portal-calendar-empty").should("be.visible");
        cy.contains("No calendar has been shared with members yet.").should("exist");
        cy.get("#portal-calendar").should("not.exist");
    });

    it("Shows the events of a church calendar that is switched on, and opens their details", () => {
        const title = `Portal Calendar Event ${Cypress._.random(0, 1e6)}`;
        const day = `${thisYear()}-05-14`;

        setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);
        cy.request({
            method: "POST",
            url: "/api/events",
            headers: { "content-type": "application/json", "x-api-key": adminKey() },
            body: {
                Title: title,
                Type: 1,
                PinnedCalendars: [CHURCH_CALENDAR_ID],
                // Wall-clock in the church's own zone, which is how event
                // datetimes are stored (timezone-handling.md).
                Start: `${day}T19:00:00`,
                End: `${day}T20:30:00`,
                Desc: "<p>An evening gathering</p>",
            },
        });

        loginAsMember();
        cy.visit("/portal/calendar");
        gotoDay(day);

        cy.contains(title, { timeout: 15000 }).should("be.visible").click();

        cy.get("#portal-calendar-detail").should("be.visible");
        cy.get("#portal-calendar-detail-title").should("have.text", title);
        cy.get("#portal-calendar-detail-calendar").should("have.text", CHURCH_CALENDAR_NAME);
        cy.get("#portal-calendar-detail-when").should("not.have.text", "");
        cy.get("#portal-calendar-detail-description").should("contain", "An evening gathering");

        // Read-only: clicking an event must not navigate anywhere.
        cy.url().should("include", "/portal/calendar");
    });

    it("The legend names every calendar that is switched on", () => {
        setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);
        loginAsMember();

        cy.visit("/portal/calendar");
        cy.get(".portal-calendar-legend").should("contain", CHURCH_CALENDAR_NAME);
    });

    it("Shows a birthday as a first name and a last initial, with no age", () => {
        setVisibleCalendars([{ type: "system", id: BIRTHDAYS_CALENDAR_ID }]);
        loginAsMember();

        cy.visit("/portal/calendar");
        gotoDay(`${thisYear()}-${BIRTHDAY_MONTH_DAY}`);

        cy.contains(BIRTHDAY_PERSON_INITIALS, { timeout: 15000 }).should("be.visible");
        cy.get("#portal-calendar").should("not.contain", BIRTHDAY_PERSON_FULL_NAME);
        // The admin calendar appends "(34)"; the portal never says an age.
        cy.get("#portal-calendar").invoke("text").should("not.match", /\(\d+\)/);
    });

    it("The Calendar nav entry and the page itself are behind bPortalShowCalendar", () => {
        setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);
        setConfig("bPortalShowCalendar", "1");
        loginAsMember();
        cy.get(".portal-nav-list").should("contain", "Calendar");

        setConfig("bPortalShowCalendar", "0");
        loginAsMember();
        cy.get(".portal-nav-list").should("not.contain", "Calendar");

        cy.visit("/portal/calendar", { failOnStatusCode: false });
        cy.get(".portal-calendar-page").should("not.exist");

        setConfig("bPortalShowCalendar", "1");
    });

    describe("Subscribing to the calendar", () => {
        it("Offers a Subscribe button above the calendar, one checkbox per shared calendar, and a feed address after saving", () => {
            setVisibleCalendars([
                { type: "calendar", id: CHURCH_CALENDAR_ID },
                { type: "system", id: BIRTHDAYS_CALENDAR_ID },
            ]);
            setConfig("bPortalShowCalendar", "1");
            loginAsMember();

            cy.visit("/portal/calendar");

            cy.get("#portal-calendar-subscribe").should("be.visible").click();
            cy.get("#portal-calendar-subscribe-dialog").should("be.visible");
            cy.contains("Subscribe to the calendar").should("be.visible");

            // One checkbox per calendar the administrator shares, all ticked
            // when nothing has been saved yet.
            cy.get("#portal-calendar-subscribe-choices input[type=checkbox]")
                .should("have.length", 2)
                .and("be.checked");

            cy.get("#portal-calendar-subscribe-save").click();

            cy.get("#portal-calendar-subscribe-url", { timeout: 10000 })
                .should("be.visible")
                .invoke("val")
                .should((value) => {
                    expect(value).to.include(Cypress.config("baseUrl").replace(/\/$/, ""));
                    expect(value).to.include("/api/public/portal-calendar/");
                    expect(value).to.match(/\/calendar\.ics$/);
                });

            cy.get("#portal-calendar-subscribe-copy").should("be.visible");
            cy.get("#portal-calendar-subscribe-open")
                .should("have.attr", "href")
                .and("match", /^webcal:\/\//);
            cy.get("#portal-calendar-subscribe-reset").should("be.visible");
        });

        it("Remembers the calendars that were ticked", () => {
            setVisibleCalendars([
                { type: "calendar", id: CHURCH_CALENDAR_ID },
                { type: "system", id: BIRTHDAYS_CALENDAR_ID },
            ]);
            loginAsMember();

            cy.visit("/portal/calendar");
            cy.get("#portal-calendar-subscribe").click();
            cy.get(`#portal-calendar-subscribe-choices input[value="system:${BIRTHDAYS_CALENDAR_ID}"]`).uncheck();
            cy.get("#portal-calendar-subscribe-save").click();
            cy.get("#portal-calendar-subscribe-url", { timeout: 10000 }).should("be.visible");

            cy.reload();
            cy.get("#portal-calendar-subscribe").click();
            cy.get(`#portal-calendar-subscribe-choices input[value="calendar:${CHURCH_CALENDAR_ID}"]`).should("be.checked");
            cy.get(`#portal-calendar-subscribe-choices input[value="system:${BIRTHDAYS_CALENDAR_ID}"]`).should("not.be.checked");
        });
    });

    it("The home page card lists the next events instead of a placeholder", () => {
        setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);
        setConfig("bPortalShowCalendar", "1");
        loginAsMember();

        cy.visit("/portal/");
        cy.get(".portal-card-calendar").should("be.visible");
        cy.get(".portal-card-calendar").should("not.contain", "Coming soon");
        cy.get(".portal-card-calendar").should("contain", "See the whole calendar");
    });

    /**
     * The last row of the MP5 scope-split table, delivered on the volunteer branch
     * (#9869): "a member who leads a team sees their ministry's calendar highlighted".
     *
     * It needed team leadership, which only reaches a self-service login once MP6 has
     * landed — which is why it could not ship with #9866.
     */
    describe("a team leader's own ministry (#9869)", () => {
        const MINISTRY_NAME = `PortalLegend ${Cypress._.random(0, 1e6)}`;
        let ministryId = 0;
        let ministryCalendarId = 0;
        let originalVersion = "v1";

        before(() => {
            cy.request({
                method: "GET",
                url: "/admin/api/system/config/sVolunteerVersion",
                headers: { "x-api-key": adminKey() },
            }).then((resp) => {
                originalVersion = resp.body.value ?? resp.body.data ?? "v1";
            });
            setConfig("sVolunteerVersion", "v2");

            cy.request({
                method: "POST",
                url: "/api/volunteer/ministries",
                headers: {
                    "content-type": "application/json",
                    "x-api-key": adminKey(),
                },
                body: { name: MINISTRY_NAME, description: "#9869 legend fixture" },
            }).then((resp) => {
                ministryId = resp.body.ministry.id;
                ministryCalendarId = resp.body.calendarId;

                // The ministry came with one team; make person 100 its leader.
                cy.request({
                    method: "GET",
                    url: `/api/volunteer/ministries/${ministryId}/teams`,
                    headers: { "x-api-key": adminKey() },
                }).then((tResp) => {
                    cy.request({
                        method: "POST",
                        url: "/api/volunteer/scopes",
                        headers: {
                            "content-type": "application/json",
                            "x-api-key": adminKey(),
                        },
                        body: {
                            personId: 100,
                            scopeType: "team",
                            scopeId: tResp.body.teams[0].id,
                        },
                        failOnStatusCode: false,
                    });
                });
            });
        });

        after(() => {
            cy.then(() => {
                cy.request({
                    method: "DELETE",
                    url: `/api/volunteer/ministries/${ministryId}`,
                    headers: { "x-api-key": adminKey() },
                    failOnStatusCode: false,
                });
                setConfig("sVolunteerVersion", originalVersion);
                setVisibleCalendars([]);
            });
        });

        it("marks the calendar of a ministry whose team the member leads", () => {
            cy.then(() => {
                setVisibleCalendars([
                    { type: "calendar", id: CHURCH_CALENDAR_ID },
                    { type: "calendar", id: ministryCalendarId },
                ]);
            });
            loginAsMember();

            cy.visit("/portal/calendar");

            cy.get(".portal-calendar-legend").should("contain", MINISTRY_NAME);
            cy.get(".portal-calendar-legend-mine")
                .should("have.length", 1)
                .and("contain", MINISTRY_NAME)
                .and("contain", "You lead this");

            // The church calendar is nobody's ministry and carries no tag.
            cy.get(".portal-calendar-legend-item")
                .contains(CHURCH_CALENDAR_NAME)
                .closest(".portal-calendar-legend-item")
                .should("not.have.class", "portal-calendar-legend-mine");
        });
    });
});
