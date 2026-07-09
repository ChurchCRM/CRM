/// <reference types="cypress" />

/**
 * Regression coverage for the calendar timezone bug tracked in issue #8712.
 *
 * The bug: events created via the calendar API were stored/returned with the
 * wrong wall-clock time whenever PHP's default timezone did not match the
 * configured sTimeZone. Root cause: timezone-naive `new \DateTime($string)`
 * construction in src/api/routes/calendar/events.php (lines ~710, 1251, 1361)
 * and server-zone leakage via `->format('c')` in
 * src/ChurchCRM/dto/FullCalendarEvent.php.
 *
 * These tests assert the contract that the API MUST preserve: whatever
 * HH:MM:SS the client sends on write is the HH:MM:SS the client reads back,
 * regardless of the server's default timezone. If the underlying
 * timezone-naive DateTime bug is reintroduced, these tests will fail.
 */
describe("API Calendar - Timezone Round-trip", () => {
    // Seeded Church Service event type — same one used by the other passing
    // event specs, avoids a dependency on type-listing order.
    const eventTypeId = 1;

    /**
     * Extract the HH:MM:SS component from a datetime string that may be
     * either "YYYY-MM-DD HH:MM:SS" (Propel toArray default for naive columns)
     * or an ISO 8601 string "YYYY-MM-DDTHH:MM:SS[+OFFSET]".
     */
    const timeOf = (dt) => {
        if (typeof dt !== "string") return null;
        const m = dt.match(/(\d{2}):(\d{2}):(\d{2})/);
        return m ? `${m[1]}:${m[2]}:${m[3]}` : null;
    };

    /** Extract YYYY-MM-DD from a datetime string. */
    const dateOf = (dt) => {
        if (typeof dt !== "string") return null;
        const m = dt.match(/(\d{4}-\d{2}-\d{2})/);
        return m ? m[1] : null;
    };

    /**
     * Extract the UTC offset component from an ISO 8601 string.
     * Returns "+HH:MM", "-HH:MM", or "Z" — or null if not present.
     * Verifying this catches the exact regression in #8712: the server
     * emitting the PHP default zone offset instead of the configured sTimeZone
     * offset, which shifts wall-clock times in FullCalendar even when
     * the HH:MM:SS component looks correct.
     */
    const offsetOf = (dt) => {
        if (typeof dt !== "string") return null;
        const m = dt.match(/(Z|[+-]\d{2}:\d{2})$/);
        return m ? m[1] : null;
    };

    describe("POST /api/events (explicit Start/End)", () => {
        it("preserves the wall-clock start and end times on read-back", () => {
            const date = "2030-06-15"; // future date, avoids DST boundary noise
            const start = `${date}T14:30:00`;
            const end = `${date}T16:45:00`;
            const title = "TZ roundtrip " + Date.now();

            // Create the event
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/",
                {
                    Title: title,
                    Type: eventTypeId,
                    Desc: "",
                    Text: "",
                    Start: start,
                    End: end,
                    PinnedCalendars: [],
                },
                200,
            );

            // Find the event we just created and verify its stored times
            cy.makePrivateAdminAPICall("GET", "/api/events/", null, 200).then(
                (resp) => {
                    const mine = resp.body.Events.find((e) => e.Title === title);
                    expect(mine, `event "${title}" must exist in /api/events`).to.exist;

                    // Date component must match exactly — no overflow into
                    // an adjacent day due to a UTC shift.
                    expect(dateOf(mine.Start)).to.equal(date);
                    expect(dateOf(mine.End)).to.equal(date);

                    // Wall-clock time component must survive the round-trip.
                    expect(timeOf(mine.Start)).to.equal("14:30:00");
                    expect(timeOf(mine.End)).to.equal("16:45:00");
                },
            );
        });
    });

    describe("POST /api/events/quick-create", () => {
        it("stores the event on the requested date (no TZ-shift into adjacent day)", () => {
            const date = "2030-07-10";

            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId, date },
                200,
            ).then((createResp) => {
                const eventId = createResp.body.eventId;
                expect(eventId).to.be.a("number");

                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/events/${eventId}`,
                    null,
                    200,
                ).then((getResp) => {
                    // Propel's toJSON emits a JSON-encoded string body; the
                    // /api/events/{id} handler passes it through unchanged.
                    const event =
                        typeof getResp.body === "string"
                            ? JSON.parse(getResp.body)
                            : getResp.body;

                    expect(dateOf(event.Start)).to.equal(date);
                    // End is derived from Start + 1h; must stay on the same date.
                    expect(dateOf(event.End)).to.equal(date);

                    // End should be exactly one hour after Start — regardless
                    // of what the default start time happens to be. This is
                    // the contract of quick-create. If the DateTime
                    // construction at events.php:710 is timezone-naive and
                    // the server zone differs from sTimeZone, the '+1 hour'
                    // modify() happens in the wrong zone and the resulting
                    // End hour drifts.
                    const startH = parseInt(timeOf(event.Start).slice(0, 2), 10);
                    const endH = parseInt(timeOf(event.End).slice(0, 2), 10);
                    expect((endH - startH + 24) % 24).to.equal(1);
                });
            });
        });
    });

    describe("POST /api/events/repeat", () => {
        it("every generated occurrence starts at the requested StartTime", () => {
            const title = "TZ repeat " + Date.now();

            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                {
                    Title: title,
                    Type: eventTypeId,
                    StartTime: "09:00",
                    EndTime: "10:30",
                    RecurType: "monthly",
                    RecurDOM: 15,
                    // 3 months → 3 occurrences, small enough to fetch cheaply
                    RangeStart: "2030-01-01",
                    RangeEnd: "2030-03-31",
                    PinnedCalendars: [],
                },
                200,
            ).then((resp) => {
                expect(resp.body.success).to.be.true;
                expect(resp.body.count).to.equal(3);
                const ids = resp.body.eventIds;
                expect(ids).to.have.length(3);

                // Check each occurrence preserves the 09:00 / 10:30 contract.
                ids.forEach((id) => {
                    cy.makePrivateAdminAPICall(
                        "GET",
                        `/api/events/${id}`,
                        null,
                        200,
                    ).then((getResp) => {
                        const event =
                            typeof getResp.body === "string"
                                ? JSON.parse(getResp.body)
                                : getResp.body;

                        expect(
                            timeOf(event.Start),
                            `event ${id} start time`,
                        ).to.equal("09:00:00");
                        expect(
                            timeOf(event.End),
                            `event ${id} end time`,
                        ).to.equal("10:30:00");
                        // Day-of-month must survive — if DateTime is parsed
                        // in server TZ but formatted in sTimeZone, 09:00 on
                        // the 15th can end up as the 14th or 16th.
                        expect(dateOf(event.Start).slice(-2)).to.equal("15");
                    });
                });
            });
        });
    });

    describe("GET /api/calendars/{id}/fullcalendar", () => {
        /**
         * FullCalendar consumes the JSON feed and renders events at whatever
         * wall-clock time the server emits. The DTO formats timed events
         * with PHP's 'c' (ISO 8601 with offset). The offset *and* the HH:MM
         * must be consistent with the wall-clock time the user entered —
         * not the server's default zone.
         *
         * The regression we're guarding against: the DateTime object
         * returned by Propel is zone-naive, so 'c' stamps it with the
         * server offset. If server is UTC (+00:00) but sTimeZone is
         * Europe/Berlin (+02:00), the feed emits "14:30:00+00:00" which
         * FullCalendar interprets as 16:30 local — a visible shift.
         */
        it("emits a start ISO 8601 string whose wall-clock hour matches the stored event", () => {
            const date = "2030-08-20";
            const start = `${date}T11:15:00`;
            const end = `${date}T12:15:00`;
            const title = "TZ feed " + Date.now();

            // Find user calendar id 1 — seeded default
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/",
                {
                    Title: title,
                    Type: eventTypeId,
                    Desc: "",
                    Text: "",
                    Start: start,
                    End: end,
                    PinnedCalendars: [1],
                },
                200,
            );

            const rangeStart = "2030-08-01";
            const rangeEnd = "2030-08-31";
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/calendars/1/fullcalendar?start=${rangeStart}&end=${rangeEnd}`,
                null,
                200,
            ).then((resp) => {
                const events = Array.isArray(resp.body)
                    ? resp.body
                    : resp.body.events || [];
                const mine = events.find((e) => e.title === title);
                expect(mine, `event "${title}" must appear in the feed`).to.exist;

                // Assert wall-clock date and time are preserved exactly.
                expect(dateOf(mine.start)).to.equal(date);
                expect(timeOf(mine.start)).to.equal("11:15:00");
                expect(dateOf(mine.end)).to.equal(date);
                expect(timeOf(mine.end)).to.equal("12:15:00");

                // Also assert the ISO-8601 offset is present. This catches
                // the #8712 regression where the server emits the PHP default
                // zone offset (e.g. "+00:00") instead of the configured
                // sTimeZone offset — which causes FullCalendar to shift
                // wall-clock times even when HH:MM:SS looks correct.
                // The offset must be a valid ISO 8601 designator ("Z" or
                // "+HH:MM" / "-HH:MM") — a missing/null offset means the
                // server sent a naive datetime string with no zone info.
                expect(
                    offsetOf(mine.start),
                    "start must carry an ISO-8601 offset (not a naive datetime)",
                ).to.match(/^(Z|[+-]\d{2}:\d{2})$/);
                expect(
                    offsetOf(mine.end),
                    "end must carry an ISO-8601 offset (not a naive datetime)",
                ).to.match(/^(Z|[+-]\d{2}:\d{2})$/);
            });
        });
    });
});
