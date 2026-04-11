/// <reference types="cypress" />

/**
 * API tests for the POST /events/repeat endpoint.
 * Tests bulk creation of recurring events from a template.
 */
describe("API Repeat Events", () => {
    // No browser login — these are pure API tests using x-api-key auth
    // (cy.makePrivateAdminAPICall sets the header for us).

    describe("POST /api/events/repeat", () => {
        // Use eventTypeId 1 (the seeded "Church Service" type) the same way
        // the other passing event API specs do, instead of fetching it.
        const eventTypeId = 1;

        it("Creates weekly repeat events and returns count and IDs", () => {
            const today = new Date();
            const TWENTY_EIGHT_DAYS_MS = 28 * 24 * 60 * 60 * 1000;
            const startDate = today.toISOString().slice(0, 10);
            const endDate = new Date(today.getTime() + TWENTY_EIGHT_DAYS_MS)
                .toISOString()
                .slice(0, 10);

            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                {
                    Title: "API Weekly Test " + Date.now(),
                    Type: eventTypeId,
                    StartTime: "09:00",
                    EndTime: "10:00",
                    RecurType: "weekly",
                    RecurDOW: "Sunday",
                    RangeStart: startDate,
                    RangeEnd: endDate,
                    PinnedCalendars: [],
                },
                200,
            ).then((response) => {
                expect(response.body.success).to.be.true;
                expect(response.body.count).to.be.a("number");
                expect(response.body.count).to.be.at.least(0);
                expect(response.body.eventIds).to.be.an("array");
                expect(response.body.eventIds.length).to.equal(response.body.count);
            });
        });

        it("Creates monthly repeat events", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                {
                    Title: "API Monthly Test " + Date.now(),
                    Type: eventTypeId,
                    StartTime: "09:00",
                    EndTime: "10:00",
                    RecurType: "monthly",
                    RecurDOM: 1,
                    RangeStart: "2025-01-01",
                    RangeEnd: "2025-12-31",
                    PinnedCalendars: [],
                },
                200,
            ).then((response) => {
                expect(response.body.success).to.be.true;
                // Monthly on 1st Jan–Dec = 12 events
                expect(response.body.count).to.equal(12);
                expect(response.body.eventIds.length).to.equal(12);
            });
        });

        it("Creates yearly repeat events", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                {
                    Title: "API Yearly Test " + Date.now(),
                    Type: eventTypeId,
                    StartTime: "09:00",
                    EndTime: "10:00",
                    RecurType: "yearly",
                    RecurDOY: "12-25",
                    RangeStart: "2020-01-01",
                    RangeEnd: "2025-12-31",
                    PinnedCalendars: [],
                },
                200,
            ).then((response) => {
                expect(response.body.success).to.be.true;
                // Yearly on Dec 25, 2020-2025 = 6 events
                expect(response.body.count).to.equal(6);
            });
        });

        it("Returns 400 for invalid recurrence type", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                {
                    Title: "Bad Recur Test",
                    Type: eventTypeId,
                    StartTime: "09:00",
                    EndTime: "10:00",
                    RecurType: "invalid_type",
                    RangeStart: "2025-01-01",
                    RangeEnd: "2025-12-31",
                    PinnedCalendars: [],
                },
                400,
            );
        });

        it("Returns 400 for invalid event type ID", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                {
                    Title: "Bad Type Test",
                    Type: 999999,
                    StartTime: "09:00",
                    EndTime: "10:00",
                    RecurType: "weekly",
                    RecurDOW: "Sunday",
                    RangeStart: "2025-01-01",
                    RangeEnd: "2025-12-31",
                    PinnedCalendars: [],
                },
                400,
            );
        });

        it("Returns 401 when not authenticated", () => {
            cy.apiRequest({
                method: "POST",
                url: "/api/events/repeat",
                body: {
                    Title: "Unauth Test",
                    Type: 1,
                    StartTime: "09:00",
                    EndTime: "10:00",
                    RecurType: "weekly",
                    RecurDOW: "Sunday",
                    RangeStart: "2025-01-01",
                    RangeEnd: "2025-12-31",
                    PinnedCalendars: [],
                },
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });
});
