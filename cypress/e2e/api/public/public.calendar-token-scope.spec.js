/// <reference types="cypress" />

/**
 * Security regression: a public calendar access token must only ever expose
 * the events of the calendar it belongs to.
 *
 * PublicCalendarMiddleware::getEvents() built the "overlaps the view window"
 * filter with a raw Propel `where()` string:
 *
 *     $events->where('events_event.event_end IS NULL OR events_event.event_end >= ?', ...)
 *
 * Propel ANDs each criterion onto the WHERE clause without wrapping it in
 * parentheses, so the calendar restriction and the raw OR flattened into
 *
 *     ... calendar_id = ? AND event_end IS NULL OR event_end >= ? ...
 *
 * `OR` binds looser than `AND`, so the right-hand side alone satisfied the
 * predicate: every event in the system whose end fell after the view start was
 * returned, no matter which calendar it was pinned to. Any holder of any public
 * token could therefore read private calendars through /events, /fullcalendar
 * and /ics — and through the public page at /external/calendars/{token}, which
 * feeds off /fullcalendar. The leak only triggered when a `start` parameter was
 * sent, which FullCalendar always does.
 *
 * These specs pin one event to each of two separate calendars in the same view
 * window and assert that each calendar's token sees its own event and never the
 * other one — with and without the `start` filter.
 */
describe("Public Calendar - access token scope (security)", () => {
    const eventTypeId = 1; // Church Service — seeded in every demo install

    const stamp = Date.now();
    const calendarAName = `TokenScope-A-${stamp}`;
    const calendarBName = `TokenScope-B-${stamp}`;
    const eventATitle = `TokenScope-Event-A-${stamp}`;
    const eventBTitle = `TokenScope-Event-B-${stamp}`;

    // Both events land on the same day, 3 days out, so a single view window
    // covers both. Only the calendar filter can tell them apart.
    const target = new Date();
    target.setDate(target.getDate() + 3);
    const dateStr = target.toISOString().slice(0, 10); // YYYY-MM-DD
    const eventStart = `${dateStr}T14:00:00`;
    const eventEnd = `${dateStr}T15:30:00`;

    // FullCalendar-style window: first of this month .. first of next month,
    // sent without a timezone offset, exactly as the embedded calendar does.
    const viewStart = `${dateStr.slice(0, 7)}-01T00:00:00`;
    const _nextYear = target.getUTCMonth() === 11 ? target.getUTCFullYear() + 1 : target.getUTCFullYear();
    const _nextMon = target.getUTCMonth() === 11 ? 1 : target.getUTCMonth() + 2; // +2: getUTCMonth is 0-based
    const viewEnd = `${_nextYear}-${String(_nextMon).padStart(2, "0")}-01T00:00:00`;
    const windowQs = { start: viewStart, end: viewEnd };

    let calendarAId;
    let calendarBId;
    let tokenA;
    let tokenB;

    // -- helpers -----------------------------------------------------------

    /** Create a calendar, mint its public access token, pin one event to it. */
    function seedCalendar(name, eventTitle, onReady) {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/calendars/",
            { Name: name, ForegroundColor: "#ffffff", BackgroundColor: "#3788d8" },
            200,
        ).then((calResp) => {
            const calendarId = calResp.body.Id;

            cy.makePrivateAdminAPICall(
                "POST",
                `/api/calendars/${calendarId}/NewAccessToken`,
                null,
                200,
            ).then((tokenResp) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    "/api/events/",
                    {
                        Title: eventTitle,
                        Type: eventTypeId,
                        Desc: "",
                        Text: "",
                        Start: eventStart,
                        End: eventEnd,
                        PinnedCalendars: [calendarId],
                    },
                    200,
                ).then(() => onReady(calendarId, tokenResp.body.AccessToken));
            });
        });
    }

    /** Delete every event visible on a token's calendar, then the calendar. */
    function tearDownCalendar(calendarId, token) {
        if (!calendarId) return;

        cy.request({
            method: "GET",
            url: `/api/public/calendar/${token}/events`,
            failOnStatusCode: false,
        })
            .then((resp) => {
                if (resp.status === 200 && Array.isArray(resp.body)) {
                    resp.body.forEach((evt) => {
                        if (evt.Id) {
                            // 404 is tolerated: an earlier crashed run may have
                            // already removed the event. Aborting here would skip
                            // the calendar delete below and orphan the calendar,
                            // which then 409s forever because events stay pinned.
                            cy.makePrivateAdminAPICall(
                                "DELETE",
                                `/api/events/${evt.Id}`,
                                null,
                                [200, 404],
                            );
                        }
                    });
                }
            })
            .then(() => {
                cy.makePrivateAdminAPICall("DELETE", `/api/calendars/${calendarId}`, null, 200);
            });
    }

    // -- setup / teardown --------------------------------------------------
    before(() => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/bEnableExternalCalendarAPI",
            { value: "1" },
            200,
        );

        seedCalendar(calendarAName, eventATitle, (id, token) => {
            calendarAId = id;
            tokenA = token;
        });
        seedCalendar(calendarBName, eventBTitle, (id, token) => {
            calendarBId = id;
            tokenB = token;
        });
    });

    after(() => {
        // Events must go before their calendars: the calendar API refuses to
        // delete a calendar that still has events pinned to it.
        tearDownCalendar(calendarAId, tokenA);
        tearDownCalendar(calendarBId, tokenB);

        // Leave external sharing off, matching the seed default.
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/bEnableExternalCalendarAPI",
            { value: "0" },
            200,
        );
    });

    // -- JSON /events, date-filtered (the leaking path) --------------------
    it("GET /events?start&end returns only the token's own calendar", () => {
        cy.request({
            method: "GET",
            url: `/api/public/calendar/${tokenA}/events`,
            qs: windowQs,
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status, "/events must return 200").to.equal(200);
            expect(resp.body, "/events must return an array").to.be.an("array");

            const titles = resp.body.map((e) => e.Title);
            expect(titles, "calendar A's own event must be present").to.include(eventATitle);
            expect(
                titles,
                "calendar B's event must NOT leak through calendar A's token",
            ).not.to.include(eventBTitle);
        });
    });

    it("GET /events?start&end does not leak in the other direction either", () => {
        cy.request({
            method: "GET",
            url: `/api/public/calendar/${tokenB}/events`,
            qs: windowQs,
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status, "/events must return 200").to.equal(200);
            expect(resp.body, "/events must return an array").to.be.an("array");

            const titles = resp.body.map((e) => e.Title);
            expect(titles, "calendar B's own event must be present").to.include(eventBTitle);
            expect(
                titles,
                "calendar A's event must NOT leak through calendar B's token",
            ).not.to.include(eventATitle);
        });
    });

    // -- FullCalendar feed (what the public HTML page fetches) -------------

    /** Assert the FullCalendar feed for `token` shows `ownTitle` and never `otherTitle`. */
    function assertFullCalendarScoped(getToken, ownTitle, otherTitle, ownLabel, otherLabel) {
        cy.request({
            method: "GET",
            url: `/api/public/calendar/${getToken()}/fullcalendar`,
            qs: windowQs,
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status, "/fullcalendar must return 200").to.equal(200);
            expect(resp.body, "/fullcalendar must return an array").to.be.an("array");

            const titles = resp.body.map((e) => e.title);
            expect(titles, `calendar ${ownLabel}'s own event must be present`).to.include(ownTitle);
            expect(
                titles,
                `calendar ${otherLabel}'s event must NOT leak into calendar ${ownLabel}'s FullCalendar feed`,
            ).not.to.include(otherTitle);
        });
    }

    it("GET /fullcalendar?start&end returns only the token's own calendar", () => {
        assertFullCalendarScoped(() => tokenA, eventATitle, eventBTitle, "A", "B");
    });

    it("GET /fullcalendar?start&end does not leak in the other direction either", () => {
        assertFullCalendarScoped(() => tokenB, eventBTitle, eventATitle, "B", "A");
    });

    // -- iCal export -------------------------------------------------------

    /** Assert the .ics export for `token` contains `ownTitle` and never `otherTitle`. */
    function assertIcsScoped(getToken, ownTitle, otherTitle, ownLabel, otherLabel) {
        cy.request({
            method: "GET",
            url: `/api/public/calendar/${getToken()}/ics`,
            qs: windowQs,
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status, "/ics must return 200").to.equal(200);
            expect(resp.headers["content-type"]).to.include("text/calendar");
            expect(resp.body, `calendar ${ownLabel}'s own event must be present`).to.include(ownTitle);
            expect(
                resp.body,
                `calendar ${otherLabel}'s event must NOT leak into calendar ${ownLabel}'s .ics download`,
            ).not.to.include(otherTitle);
        });
    }

    it("GET /ics?start&end exports only the token's own calendar", () => {
        assertIcsScoped(() => tokenA, eventATitle, eventBTitle, "A", "B");
    });

    it("GET /ics?start&end does not leak in the other direction either", () => {
        assertIcsScoped(() => tokenB, eventBTitle, eventATitle, "B", "A");
    });

    // -- unfiltered path: already correct, guards against regressing it ----
    it("GET /events without a date filter returns only the token's own calendar", () => {
        cy.request({
            method: "GET",
            url: `/api/public/calendar/${tokenA}/events`,
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status, "/events must return 200").to.equal(200);
            expect(resp.body, "/events must return an array").to.be.an("array");

            const titles = resp.body.map((e) => e.Title);
            expect(titles, "calendar A's own event must be present").to.include(eventATitle);
            expect(
                titles,
                "calendar B's event must not appear without a date filter either",
            ).not.to.include(eventBTitle);
        });
    });

    it("GET /ics without a date filter exports only the token's own calendar", () => {
        cy.request({
            method: "GET",
            url: `/api/public/calendar/${tokenA}/ics`,
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status, "/ics must return 200").to.equal(200);
            expect(resp.body, "calendar A's own event must be present").to.include(eventATitle);
            expect(
                resp.body,
                "calendar B's event must not appear without a date filter either",
            ).not.to.include(eventBTitle);
        });
    });

    // -- end-only filter ---------------------------------------------------
    it("GET /events with only an end date returns only the token's own calendar", () => {
        cy.request({
            method: "GET",
            url: `/api/public/calendar/${tokenA}/events`,
            qs: { end: viewEnd },
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status, "/events must return 200").to.equal(200);
            expect(resp.body, "/events must return an array").to.be.an("array");

            const titles = resp.body.map((e) => e.Title);
            expect(titles, "calendar A's own event must be present").to.include(eventATitle);
            expect(
                titles,
                "calendar B's event must not appear with an end-only filter",
            ).not.to.include(eventBTitle);
        });
    });
});
