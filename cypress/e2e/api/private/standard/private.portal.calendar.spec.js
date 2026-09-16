/// <reference types="cypress" />

/**
 * GET /api/portal/calendar/events (MP5, #9866).
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §5.3.
 *   - session only: an API key is refused, because the portal derives the
 *     acting person from the session (P11)
 *   - the answer is the union of the calendars the administrator switched on;
 *     there is no calendar id in the URL to point somewhere else
 *   - `from` and `to` are plain days; a reversed range is a 400 and the window
 *     is clamped to 62 days
 *   - the shaping — and so the timezone — is the one the admin calendar API
 *     uses, so a given event reads identically on both
 *
 * Seed facts: calendar 2 is "Private Calendar" and holds event 2, "Christmas
 * Service", starting 2016-12-24 22:30 wall-clock in the church's timezone.
 */
const PRIVATE_CALENDAR_ID = 2;
const PUBLIC_CALENDAR_ID = 1;
const SEEDED_EVENT_TITLE = "Christmas Service";
const WINDOW_AROUND_SEEDED_EVENT = { from: "2016-12-01", to: "2016-12-31" };

const MEMBER_USER = "lena.black.editself.notes@exampl";
const MEMBER_PASSWORD = "changeme";

const EVENTS_URL = "/api/portal/calendar/events";

const adminKey = () => Cypress.env("admin.api.key");

const setVisibleCalendars = (visible) =>
    cy.request({
        method: "POST",
        url: "/admin/api/member-portal/calendars",
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { visible },
        failOnStatusCode: false,
    });

/**
 * Log in the way a browser does. An x-api-key request replaces the session
 * cookie with an API-token session, so every admin set-up step above has to be
 * followed by this before the portal endpoint is asked anything.
 */
const loginAsMember = () => {
    cy.clearCookies();
    cy.request("/session/begin");
    cy.request({
        method: "POST",
        url: "/session/begin",
        form: true,
        body: { User: MEMBER_USER, Password: MEMBER_PASSWORD },
        followRedirect: false,
    }).then((response) => {
        expect(response.status, "the member's login").to.be.oneOf([200, 302]);
    });
};

const getEvents = (query = {}) =>
    cy.request({
        method: "GET",
        url: EVENTS_URL,
        qs: query,
        failOnStatusCode: false,
    });

describe("Member Portal calendar API", () => {
    after(() => {
        setVisibleCalendars([]);
    });

    it("Refuses an API key: the portal is a signed-in surface, not a key-shaped one", () => {
        cy.request({
            method: "GET",
            url: EVENTS_URL,
            headers: { "x-api-key": adminKey() },
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.eq(403);
        });
    });

    it("Answers an empty list when no calendar is shared", () => {
        setVisibleCalendars([]);
        loginAsMember();

        getEvents(WINDOW_AROUND_SEEDED_EVENT).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.hasVisibleCalendars).to.eq(false);
            expect(response.body.events).to.have.length(0);
        });
    });

    it("Returns only the events of the calendars that are switched on", () => {
        setVisibleCalendars([{ type: "calendar", id: PRIVATE_CALENDAR_ID }]);
        loginAsMember();

        getEvents(WINDOW_AROUND_SEEDED_EVENT).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.events.length).to.be.greaterThan(0);
            for (const event of response.body.events) {
                expect(event.extendedProps.calendarType).to.eq("calendar");
                expect(event.extendedProps.calendarId).to.eq(PRIVATE_CALENDAR_ID);
            }
        });
    });

    it("Does not return events of a calendar that is not switched on", () => {
        setVisibleCalendars([{ type: "calendar", id: PUBLIC_CALENDAR_ID }]);
        loginAsMember();

        getEvents(WINDOW_AROUND_SEEDED_EVENT).then((response) => {
            expect(response.status).to.eq(200);
            const titles = response.body.events.map((event) => event.title);
            expect(titles).to.not.include(SEEDED_EVENT_TITLE);
        });
    });

    it("Shapes an event exactly as the admin calendar API does, timezone included", () => {
        setVisibleCalendars([{ type: "calendar", id: PRIVATE_CALENDAR_ID }]);

        cy.request({
            method: "GET",
            url: `/api/calendars/${PRIVATE_CALENDAR_ID}/fullcalendar`,
            qs: { start: WINDOW_AROUND_SEEDED_EVENT.from, end: WINDOW_AROUND_SEEDED_EVENT.to },
            headers: { "x-api-key": adminKey() },
        }).then((adminResponse) => {
            const fromAdmin = adminResponse.body.find((event) => event.title === SEEDED_EVENT_TITLE);
            expect(fromAdmin, "the seeded event on the admin API").to.exist;

            loginAsMember();
            getEvents(WINDOW_AROUND_SEEDED_EVENT).then((response) => {
                const fromPortal = response.body.events.find(
                    (event) => event.title === SEEDED_EVENT_TITLE
                );
                expect(fromPortal, "the seeded event on the portal API").to.exist;

                // Same instant, same offset, same all-day flag.
                expect(fromPortal.start).to.eq(fromAdmin.start);
                expect(fromPortal.end).to.eq(fromAdmin.end);
                expect(fromPortal.allDay).to.eq(fromAdmin.allDay);
                expect(fromPortal.start).to.match(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/);

                // The portal never hands a member a link into the admin area.
                expect(fromPortal.url).to.be.undefined;
                expect(fromPortal.editable).to.be.undefined;
            });
        });
    });

    it("Clamps a window longer than 62 days and says which window it answered", () => {
        setVisibleCalendars([{ type: "calendar", id: PRIVATE_CALENDAR_ID }]);
        loginAsMember();

        getEvents({ from: "2026-01-01", to: "2026-12-31" }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.from).to.eq("2026-01-01");
            expect(response.body.to).to.eq("2026-03-04");
        });
    });

    it("Refuses a reversed range", () => {
        loginAsMember();

        getEvents({ from: "2026-09-30", to: "2026-09-01" }).then((response) => {
            expect(response.status).to.eq(400);
        });
    });

    it("Refuses a range bound that is not a date", () => {
        loginAsMember();

        getEvents({ from: "nonsense" }).then((response) => {
            expect(response.status).to.eq(400);
        });
        getEvents({ from: "2026-02-30" }).then((response) => {
            expect(response.status, "30 February is not a day").to.eq(400);
        });
    });

    it("Renders a birthday as a first name and a last initial, with no age", () => {
        // Albert Campbell is seeded with a 9 September birthday.
        setVisibleCalendars([{ type: "system", id: 0 }]);
        loginAsMember();

        const year = new Date().getFullYear();
        getEvents({ from: `${year}-09-09`, to: `${year}-09-09` }).then((response) => {
            expect(response.status).to.eq(200);
            const titles = response.body.events.map((event) => event.title);
            expect(titles).to.include("Albert C.");
            expect(titles).to.not.include("Albert Campbell");
            for (const title of titles) {
                expect(title, "no age is ever shown").to.not.match(/\(\d+\)/);
            }
        });
    });
});
