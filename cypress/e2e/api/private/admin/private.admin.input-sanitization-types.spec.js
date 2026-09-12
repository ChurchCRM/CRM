/// <reference types="cypress" />

/**
 * Issue #9821 — declarative `date` / `datetime` / `enum:a,b,c` types for
 * InputSanitizationMiddleware.
 *
 * The middleware has no route of its own, so it is exercised through the two
 * calendar routes this PR migrates off their hand-rolled validation:
 *
 *   POST /api/events/repeat        RecurType → enum:weekly,monthly,yearly
 *                                  RangeStart / RangeEnd → date? (required form
 *                                  stays in the handler, see below)
 *   POST /api/events/quick-create  date → date?  (a genuinely optional field)
 *
 * What each block pins:
 *  - a valid value passes and is normalised (the created events land on the
 *    dates that were posted);
 *  - a malformed value is rejected with 400 and the middleware's own error
 *    shape — `{"error": "…"}` (`InputSanitizationMiddleware:55/:63`) — with the
 *    field named in the message;
 *  - an out-of-set enum value (including a wrong-case one) is rejected;
 *  - an absent optional field is left absent, not coerced to "": the handler's
 *    own "Missing required field" / default-to-today behaviour still fires.
 *
 * Not covered here: the `datetime` type. No production route accepts a
 * `YYYY-MM-DD HH:MM:SS` body field today (`POST /events/{id}/time` takes the
 * ISO `T` separator), so covering it would mean tightening a route's accepted
 * inputs, which this PR deliberately does not do. Its first callers are the
 * Volunteer v2 endpoints (#9705).
 */
describe("API InputSanitizationMiddleware date/enum types (#9821)", () => {
    // The seeded "Church Service" event type.
    const eventTypeId = 1;

    // Each test claims its own far-future window so a re-run against a database
    // that was not reset still starts from an empty date range.
    let windowSeq = 0;
    const nextWindow = (weeks = 3) => {
        const DAY_MS = 24 * 60 * 60 * 1000;
        const base = new Date(Date.UTC(2050, 0, 1));
        const offsetDays = (Date.now() % 2000) + windowSeq++ * 40;
        const start = new Date(base.getTime() + offsetDays * DAY_MS);
        const end = new Date(start.getTime() + weeks * 7 * DAY_MS);
        return {
            startDate: start.toISOString().slice(0, 10),
            endDate: end.toISOString().slice(0, 10),
        };
    };

    const repeatBody = (overrides) => ({
        Title: "9821 Sanitizer Test " + Date.now(),
        Type: eventTypeId,
        StartTime: "09:00",
        EndTime: "10:00",
        RecurType: "weekly",
        RecurDOW: "Sunday",
        PinnedCalendars: [],
        ...overrides,
    });

    describe("enum:weekly,monthly,yearly — POST /api/events/repeat", () => {
        it("Accepts an in-set value and creates the events", () => {
            const { startDate, endDate } = nextWindow();
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                repeatBody({
                    RecurType: "weekly",
                    RangeStart: startDate,
                    RangeEnd: endDate,
                }),
                200,
            ).then((response) => {
                expect(response.body.success).to.be.true;
                expect(response.body.eventIds).to.be.an("array");
            });
        });

        it("Rejects an out-of-set value with 400 naming the field", () => {
            const { startDate, endDate } = nextWindow();
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                repeatBody({
                    RecurType: "fortnightly",
                    RangeStart: startDate,
                    RangeEnd: endDate,
                }),
                400,
            ).then((response) => {
                expect(response.body.success, "canonical error shape").to.be.false;
                expect(response.body.message, "canonical error shape").to.be.a(
                    "string",
                );
                expect(response.body.message).to.contain("RecurType");
            });
        });

        it("Is case-sensitive — 'Weekly' is out of set", () => {
            const { startDate, endDate } = nextWindow();
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                repeatBody({
                    RecurType: "Weekly",
                    RangeStart: startDate,
                    RangeEnd: endDate,
                }),
                400,
            ).then((response) => {
                expect(response.body.message).to.contain("RecurType");
            });
        });
    });

    describe("date — POST /api/events/repeat", () => {
        it("Accepts YYYY-MM-DD and generates occurrences inside the range", () => {
            const { startDate, endDate } = nextWindow(2);
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                repeatBody({ RangeStart: startDate, RangeEnd: endDate }),
                200,
            ).then((response) => {
                expect(response.body.success).to.be.true;
                const ids = response.body.eventIds;
                expect(ids.length).to.be.greaterThan(0);
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/events/${ids[0]}`,
                    null,
                    200,
                ).then((evt) => {
                    const start = String(evt.body.Start ?? evt.body.start).slice(
                        0,
                        10,
                    );
                    expect(start >= startDate).to.be.true;
                    expect(start <= endDate).to.be.true;
                });
            });
        });

        it("Rejects a malformed date with 400 naming the field", () => {
            const { endDate } = nextWindow();
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                repeatBody({ RangeStart: "2050-13-45", RangeEnd: endDate }),
                400,
            ).then((response) => {
                expect(response.body.success, "canonical error shape").to.be.false;
                expect(response.body.message, "canonical error shape").to.be.a(
                    "string",
                );
                expect(response.body.message).to.contain("RangeStart");
            });
        });

        it("Rejects a non-existent calendar date (no silent roll-over)", () => {
            const { endDate } = nextWindow();
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                repeatBody({ RangeStart: "2050-02-30", RangeEnd: endDate }),
                400,
            ).then((response) => {
                expect(response.body.message).to.contain("RangeStart");
            });
        });

        it("Rejects a loose date string that strtotime() would have accepted", () => {
            const { endDate } = nextWindow();
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                repeatBody({ RangeStart: "2050/01/01", RangeEnd: endDate }),
                400,
            ).then((response) => {
                expect(response.body.message).to.contain("RangeStart");
            });
        });

        it("Leaves an absent optional date absent — the handler's own required check still fires", () => {
            const { endDate } = nextWindow();
            const body = repeatBody({ RangeEnd: endDate });
            delete body.RangeStart;
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                body,
                400,
            ).then((response) => {
                // Handler shape (SlimUtils::renderErrorJSON), not the
                // middleware's — proof the middleware did not claim the field
                // and did not substitute an empty string for it.
                expect(response.body.message).to.contain(
                    "Missing required field",
                );
                expect(response.body.message).to.contain("RangeStart");
            });
        });

        it("Keeps the handler's range-ordering check", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                repeatBody({ RangeStart: "2050-06-01", RangeEnd: "2050-05-01" }),
                400,
            ).then((response) => {
                expect(response.body.message).to.contain("RangeEnd");
            });
        });
    });

    describe("date? (optional form) — POST /api/events/quick-create", () => {
        it("Accepts a valid date and creates the event on that date", () => {
            const { startDate } = nextWindow();
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId, date: startDate },
                200,
            ).then((response) => {
                expect(response.body).to.have.property("eventId");
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/events/${response.body.eventId}`,
                    null,
                    200,
                ).then((evt) => {
                    expect(
                        String(evt.body.Start ?? evt.body.start).slice(0, 10),
                    ).to.equal(startDate);
                });
            });
        });

        it("Leaves an absent optional date absent — the handler defaults to today", () => {
            const today = new Date();
            const localToday = [
                today.getFullYear(),
                String(today.getMonth() + 1).padStart(2, "0"),
                String(today.getDate()).padStart(2, "0"),
            ].join("-");
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId },
                200,
            ).then((response) => {
                expect(response.body).to.have.property("eventId");
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/events/${response.body.eventId}`,
                    null,
                    200,
                ).then((evt) => {
                    // The event is dated "today" in the church timezone, which
                    // may be a day either side of the runner's local date.
                    const start = String(evt.body.Start ?? evt.body.start).slice(
                        0,
                        10,
                    );
                    const deltaDays =
                        Math.abs(new Date(start) - new Date(localToday)) /
                        86400000;
                    expect(deltaDays).to.be.at.most(1);
                });
            });
        });

        it("Treats an empty optional date as not supplied", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId, date: "" },
                200,
            ).then((response) => {
                expect(response.body).to.have.property("eventId");
            });
        });

        it("Rejects a malformed optional date with 400 naming the field", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId, date: "not-a-date" },
                400,
            ).then((response) => {
                expect(response.body.success, "canonical error shape").to.be.false;
                expect(response.body.message, "canonical error shape").to.be.a(
                    "string",
                );
                expect(response.body.message).to.contain("date");
            });
        });
    });

    describe("int — unchanged by this change", () => {
        it("Still accepts a valid integer", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/system/logs/loglevel",
                { value: "200" },
                200,
            );
        });

        it("Still rejects a non-integer with the middleware error shape", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/system/logs/loglevel",
                { value: "not-an-int" },
                400,
            ).then((response) => {
                expect(response.body.message).to.contain("value");
            });
        });

        it("Still rejects an absent value (int is required)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/system/logs/loglevel",
                {},
                400,
            ).then((response) => {
                expect(response.body.message).to.contain("required");
            });
        });
    });
});
