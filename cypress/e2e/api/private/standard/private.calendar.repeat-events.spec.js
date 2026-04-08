/// <reference types="cypress" />

/**
 * API tests for the POST /events/repeat endpoint.
 * Tests bulk creation of recurring events from a template.
 */
describe("API Repeat Events", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("POST /api/events/repeat", () => {
        let eventTypeId;

        before(() => {
            // Fetch an event type to use in tests. /api/events/types returns a
            // Propel ObjectCollection serialized as an OBJECT keyed by index
            // ("0", "1", ...), not a true JS array — normalize via Object.values.
            cy.setupAdminSession();
            cy.makePrivateAdminAPICall("GET", "/api/events/types", null, [200, 404]).then(
                (response) => {
                    if (response.status !== 200 || !response.body) return;
                    const types = Array.isArray(response.body)
                        ? response.body
                        : Object.values(response.body);
                    if (types.length > 0 && types[0] && types[0].Id) {
                        eventTypeId = types[0].Id;
                    }
                },
            );
        });

        it("Creates weekly repeat events and returns count and IDs", function () {
            if (!eventTypeId) this.skip();

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

        it("Creates monthly repeat events", function () {
            if (!eventTypeId) this.skip();

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

        it("Creates yearly repeat events", function () {
            if (!eventTypeId) this.skip();

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

        it("Returns 400 for invalid recurrence type", function () {
            if (!eventTypeId) this.skip();

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
