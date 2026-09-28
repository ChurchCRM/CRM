/// <reference types="cypress" />

/**
 * Issue #9735 — one recurrence engine.
 *
 * POST /events/repeat and POST /events/generate-recurring used to be two
 * independent generators with different caps, different titles, different times
 * and different duplicate handling. Both now funnel through
 * EventService::createRecurringEvents(), so this spec pins the properties that
 * unification is supposed to guarantee:
 *
 *  1. the same recurrence + range produces the same occurrence dates and times
 *     on both endpoints;
 *  2. re-running the same request with skip-existing creates nothing, on both;
 *  3. the same occurrence cap is enforced with the same message, on both;
 *  4. both response shapes stay backward compatible.
 */
describe("API Recurring Event Parity (#9735)", () => {
    // The seeded "Church Service" type: weekly, Sunday, 10:30 default start.
    const eventTypeId = 1;
    const typeDefaults = {
        recurType: "weekly",
        recurDOW: "Sunday",
        startTime: "10:30",
        endTime: "11:30", // the engine's default duration is one hour
    };

    // Each test claims its own future window so re-running the suite against a
    // database that was not reset still starts from an empty date range.
    let windowSeq = 0;
    const nextWindow = (weeks = 3) => {
        const DAY_MS = 24 * 60 * 60 * 1000;
        const base = new Date(Date.UTC(2040, 0, 1));
        const offsetDays = (Date.now() % 3000) + windowSeq++ * 40;
        const start = new Date(base.getTime() + offsetDays * DAY_MS);
        const end = new Date(start.getTime() + weeks * 7 * DAY_MS);
        return {
            startDate: start.toISOString().slice(0, 10),
            endDate: end.toISOString().slice(0, 10),
        };
    };

    const fetchStartEnd = (ids) => {
        const rows = [];
        ids.forEach((id) => {
            cy.makePrivateAdminAPICall("GET", `/api/events/${id}`, null, 200).then(
                (resp) => {
                    rows.push({
                        start: resp.body.Start ?? resp.body.start,
                        end: resp.body.End ?? resp.body.end,
                    });
                },
            );
        });
        return cy.wrap(rows, { log: false });
    };

    it("Produces identical occurrence dates and times on both endpoints", () => {
        const { startDate, endDate } = nextWindow();

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/generate-recurring",
            { eventTypeId, startDate, endDate, skipExisting: false },
            200,
        ).then((generated) => {
            const generatedDates = generated.body.events.map((e) => e.date);
            expect(generatedDates.length).to.be.greaterThan(0);

            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                {
                    Title: "Parity probe " + Date.now(),
                    Type: eventTypeId,
                    StartTime: typeDefaults.startTime,
                    EndTime: typeDefaults.endTime,
                    RecurType: typeDefaults.recurType,
                    RecurDOW: typeDefaults.recurDOW,
                    RangeStart: startDate,
                    RangeEnd: endDate,
                    PinnedCalendars: [],
                },
                200,
            ).then((repeated) => {
                // Same number of occurrences from the same recurrence + range.
                expect(repeated.body.count).to.equal(generated.body.created);

                fetchStartEnd(generated.body.events.map((e) => e.id)).then(
                    (generatedRows) => {
                        fetchStartEnd(repeated.body.eventIds).then((repeatRows) => {
                            const starts = (rows) =>
                                rows.map((r) => r.start).sort();
                            const ends = (rows) => rows.map((r) => r.end).sort();
                            // Identical start and end datetimes, not merely
                            // identical dates.
                            expect(starts(repeatRows)).to.deep.equal(
                                starts(generatedRows),
                            );
                            expect(ends(repeatRows)).to.deep.equal(
                                ends(generatedRows),
                            );
                        });
                    },
                );
            });
        });
    });

    it("Is idempotent on re-run for POST /events/generate-recurring", () => {
        const { startDate, endDate } = nextWindow();

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/generate-recurring",
            { eventTypeId, startDate, endDate, skipExisting: true },
            200,
        ).then((first) => {
            expect(first.body.created).to.be.greaterThan(0);

            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/generate-recurring",
                { eventTypeId, startDate, endDate, skipExisting: true },
                200,
            ).then((second) => {
                expect(second.body.created).to.equal(0);
                expect(second.body.skipped).to.equal(first.body.created);
            });
        });
    });

    it("Is idempotent on re-run for POST /events/repeat with SkipExisting", () => {
        const { startDate, endDate } = nextWindow();
        const body = {
            Title: "Idempotent probe " + Date.now(),
            Type: eventTypeId,
            StartTime: typeDefaults.startTime,
            EndTime: typeDefaults.endTime,
            RecurType: typeDefaults.recurType,
            RecurDOW: typeDefaults.recurDOW,
            RangeStart: startDate,
            RangeEnd: endDate,
            SkipExisting: true,
            PinnedCalendars: [],
        };

        cy.makePrivateAdminAPICall("POST", "/api/events/repeat", body, 200).then(
            (first) => {
                expect(first.body.count).to.be.greaterThan(0);
                expect(first.body.skipped).to.equal(0);

                cy.makePrivateAdminAPICall(
                    "POST",
                    "/api/events/repeat",
                    body,
                    200,
                ).then((second) => {
                    expect(second.body.count).to.equal(0);
                    expect(second.body.eventIds).to.deep.equal([]);
                    expect(second.body.skipped).to.equal(first.body.count);
                });
            },
        );
    });

    it("Keeps POST /events/repeat duplicating by default (SkipExisting off)", () => {
        const { startDate, endDate } = nextWindow();
        const body = {
            Title: "Duplicating probe " + Date.now(),
            Type: eventTypeId,
            StartTime: typeDefaults.startTime,
            EndTime: typeDefaults.endTime,
            RecurType: typeDefaults.recurType,
            RecurDOW: typeDefaults.recurDOW,
            RangeStart: startDate,
            RangeEnd: endDate,
            PinnedCalendars: [],
        };

        cy.makePrivateAdminAPICall("POST", "/api/events/repeat", body, 200).then(
            (first) => {
                expect(first.body.success).to.be.true;
                expect(first.body.count).to.be.greaterThan(0);
                expect(first.body.eventIds).to.be.an("array");
                expect(first.body.eventIds.length).to.equal(first.body.count);

                cy.makePrivateAdminAPICall(
                    "POST",
                    "/api/events/repeat",
                    body,
                    200,
                ).then((second) => {
                    // Backward compatible: without SkipExisting the endpoint
                    // still creates the whole series again.
                    expect(second.body.count).to.equal(first.body.count);
                    expect(second.body.skipped).to.equal(0);
                });
            },
        );
    });

    describe("Shared 366-occurrence cap", () => {
        // Weekly for ten years is ~522 occurrences — over the cap on either
        // endpoint. The old code rejected this on /generate-recurring with a
        // 1-year range error and accepted it on /repeat.
        const overCap = { startDate: "2040-01-01", endDate: "2050-01-01" };

        it("Rejects an over-cap request on POST /events/generate-recurring", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/generate-recurring",
                {
                    eventTypeId,
                    startDate: overCap.startDate,
                    endDate: overCap.endDate,
                },
                400,
            ).then((resp) => {
                expect(resp.body.message).to.contain("Too many occurrences");
                expect(resp.body.message).to.contain("366");
            });
        });

        it("Rejects the same over-cap request on POST /events/repeat", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                {
                    Title: "Over cap probe",
                    Type: eventTypeId,
                    StartTime: typeDefaults.startTime,
                    EndTime: typeDefaults.endTime,
                    RecurType: typeDefaults.recurType,
                    RecurDOW: typeDefaults.recurDOW,
                    RangeStart: overCap.startDate,
                    RangeEnd: overCap.endDate,
                    PinnedCalendars: [],
                },
                400,
            ).then((resp) => {
                expect(resp.body.message).to.contain("Too many occurrences");
                expect(resp.body.message).to.contain("366");
            });
        });

        it("Accepts a multi-year range that stays under the cap", () => {
            // Previously a hard 400 on /generate-recurring ("Date range cannot
            // exceed 1 year") while /repeat happily created the same series.
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/generate-recurring",
                {
                    eventTypeId,
                    startDate: "2038-01-01",
                    endDate: "2039-12-31",
                    skipExisting: true,
                },
                200,
            ).then((resp) => {
                expect(resp.body.created + resp.body.skipped).to.be.greaterThan(
                    60,
                );
            });
        });
    });
});
