/// <reference types="cypress" />

/**
 * Regression coverage for public calendar event visibility (PR #8981 / issue #8949).
 *
 * Bugs guarded against:
 *   1. ?? vs ?: in the date-parse fallback chain — DateTime::createFromFormat()
 *      returns false, not null, so ?? stopped the chain silently and the
 *      fullcalendar endpoint returned 400, showing no events at all.
 *   2. Wrong filter semantics — containment logic hid events with NULL end
 *      dates and events that span the view boundary.
 *   3. Stale moment-with-locales.min.js script causing a 404 on the HTML page.
 *
 * Asserts all three public output formats (JSON events, iCal, HTML) show the
 * same event, and that wall-clock times survive the timezone round-trip.
 */
describe("Public Calendar - Event Visibility (regression: PR #8981)", () => {
    const eventTypeId = 1; // Church Service — seeded in every demo install

    // Event 3 days from today — always within the current month's view window
    const target = new Date();
    target.setDate(target.getDate() + 3);
    const dateStr    = target.toISOString().slice(0, 10); // YYYY-MM-DD
    const eventStart = `${dateStr}T14:00:00`;
    const eventEnd   = `${dateStr}T15:30:00`;
    const eventTitle = `Public-Calendar-Regression-${Date.now()}`;

    // View window in the format FullCalendar sends when using a named church
    // timezone: no timezone offset. This is exactly the format that the
    // ?? → ?: fix enables — if the fix regresses the /fullcalendar endpoint
    // will return 400 and the test will fail.
    const viewStart = `${dateStr.slice(0, 7)}-01T00:00:00`; // first of the month
    const viewEnd   = `${dateStr.slice(0, 7)}-31T00:00:00`; // past month end (safe)

    let calendarId;
    let accessToken;

    // -- helpers -----------------------------------------------------------
    const dateOf = (dt) => (dt ?? "").match(/(\d{4}-\d{2}-\d{2})/)?.[1] ?? null;
    const timeOf = (dt) => (dt ?? "").match(/T?(\d{2}:\d{2}):\d{2}/)?.[1] ?? null;

    // -- setup / teardown --------------------------------------------------
    before(() => {
        // Ensure the public calendar feature is enabled
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/bEnableExternalCalendarAPI",
            { value: "1" },
            200,
        );

        // Create a dedicated test calendar
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/calendars/",
            {
                Name: `Test-${eventTitle}`,
                ForegroundColor: "#ffffff",
                BackgroundColor: "#3788d8",
            },
            200,
        ).then((calResp) => {
            calendarId = calResp.body.Id;

            // Generate public access token
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/calendars/${calendarId}/NewAccessToken`,
                null,
                200,
            ).then((tokenResp) => {
                accessToken = tokenResp.body.AccessToken;
            });

            // Create the event pinned to this calendar
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/",
                {
                    Title: eventTitle,
                    Type: eventTypeId,
                    Desc: "<p>Regression test description</p>",
                    Text: "",
                    Start: eventStart,
                    End: eventEnd,
                    PinnedCalendars: [calendarId],
                },
                200,
            );
        });
    });

    after(() => {
        if (!calendarId) return;

        // The calendar API blocks deletion while events are still pinned to it.
        // Fetch event IDs from the public endpoint, delete each event, then
        // delete the calendar — all chained so order is guaranteed.
        cy.request({
            method: "GET",
            url: `/api/public/calendar/${accessToken}/events`,
            failOnStatusCode: false,
        })
            .then((resp) => {
                if (resp.status === 200 && Array.isArray(resp.body)) {
                    resp.body.forEach((evt) => {
                        if (evt.Id) {
                            cy.makePrivateAdminAPICall("DELETE", `/api/events/${evt.Id}`, null, 200);
                        }
                    });
                }
            })
            .then(() => {
                cy.makePrivateAdminAPICall("DELETE", `/api/calendars/${calendarId}`, null, 200);
            });
    });

    // -- JSON /events endpoint: no date filter ----------------------------
    it("GET /events returns the event with correct wall-clock times (unfiltered)", () => {
        cy.request({
            method: "GET",
            url: `/api/public/calendar/${accessToken}/events`,
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status, "/events must return 200").to.equal(200);
            expect(resp.body).to.be.an("array");

            const mine = resp.body.find((e) => e.Title === eventTitle);
            expect(mine, `event "${eventTitle}" must appear in /events`).to.exist;

            // Wall-clock date and time must survive the timezone round-trip
            expect(dateOf(mine.Start), "start date").to.equal(dateStr);
            expect(timeOf(mine.Start), "start wall-clock time").to.equal("14:00");
            expect(dateOf(mine.End),   "end date").to.equal(dateStr);
            expect(timeOf(mine.End),   "end wall-clock time").to.equal("15:30");
        });
    });

    // -- FullCalendar /fullcalendar endpoint: date-filtered ---------------
    it("GET /fullcalendar returns 200 and the event with FullCalendar-style date params (regression: ?? vs ?:)", () => {
        // FullCalendar sends dates WITHOUT a timezone offset when using a named
        // church timezone — this is the exact format that triggered the 400 bug.
        cy.request({
            method: "GET",
            url: `/api/public/calendar/${accessToken}/fullcalendar`,
            qs: { start: viewStart, end: viewEnd },
            failOnStatusCode: false,
        }).then((resp) => {
            expect(
                resp.status,
                "must not return 400 — the ?: fix must accept no-offset ISO 8601 dates",
            ).to.equal(200);

            const events = Array.isArray(resp.body) ? resp.body : [];
            const mine = events.find((e) => e.title === eventTitle);
            expect(mine, `event "${eventTitle}" must appear in /fullcalendar feed`).to.exist;

            // Wall-clock times must survive the timezone round-trip
            expect(dateOf(mine.start), "start date in FullCalendar feed").to.equal(dateStr);
            expect(timeOf(mine.start), "start wall-clock in feed").to.equal("14:00");
            expect(dateOf(mine.end),   "end date in FullCalendar feed").to.equal(dateStr);
            expect(timeOf(mine.end),   "end wall-clock in feed").to.equal("15:30");

            // Description must be plain text — HTML stripped by strip_tags()
            expect(
                mine.extendedProps?.description,
                "description must be plain text (HTML stripped)",
            ).to.equal("Regression test description");

            // allDay must be false — event has explicit start and end times
            expect(mine.allDay, "timed event must not be allDay").to.be.false;
        });
    });

    // -- iCal /ics endpoint -----------------------------------------------
    it("GET /ics returns a valid iCal file containing the event", () => {
        cy.request({
            method: "GET",
            url: `/api/public/calendar/${accessToken}/ics`,
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status, "/ics must return 200").to.equal(200);
            expect(resp.headers["content-type"]).to.include("text/calendar");
            expect(resp.body).to.include("BEGIN:VCALENDAR");
            expect(resp.body).to.include("BEGIN:VEVENT");
            expect(resp.body).to.include(eventTitle);
            expect(resp.body).to.include("END:VEVENT");
            expect(resp.body).to.include("END:VCALENDAR");
        });
    });

    // -- HTML page --------------------------------------------------------
    it("GET /external/calendars/{token} loads correctly — no 404s, shows timezone option", () => {
        cy.request({
            method: "GET",
            url: `/external/calendars/${accessToken}`,
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status, "HTML calendar page must return 200").to.equal(200);
            expect(resp.headers["content-type"]).to.include("text/html");

            // FullCalendar bundle must be loaded
            expect(resp.body).to.include("fullcalendar/index.global.min.js");

            // The stale moment-with-locales.min.js (which 404ed and broke the page)
            // must no longer be referenced
            expect(
                resp.body,
                "moment-with-locales.min.js must be removed (file does not exist on disk)",
            ).not.to.include("moment-with-locales.min.js");

            // FullCalendar's timeZone option must be wired up
            expect(resp.body).to.include("timeZone:");
        });
    });
});
