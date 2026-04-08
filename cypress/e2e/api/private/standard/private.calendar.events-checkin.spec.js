/// <reference types="cypress" />

/**
 * API tests for the streamlined event check-in endpoints:
 * - POST /events/quick-create
 * - GET /events/today
 * - GET /events/{id}/roster
 * - POST /events/{id}/checkin
 * - POST /events/{id}/checkout
 * - POST /events/{id}/checkin-all
 * - POST /events/{id}/checkout-all
 * - POST /events/generate-recurring
 */
describe("API Event Check-in Endpoints", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("POST /api/events/quick-create", () => {
        it("Creates a new event from an event type", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId: 1 },
                200,
            ).then((response) => {
                expect(response.body).to.have.property("eventId");
                expect(response.body.eventId).to.be.a("number");
                expect(response.body).to.have.property("title");
                expect(response.body.title).to.be.a("string");
                expect(response.body).to.have.property("created");
                expect(response.body.created).to.be.a("boolean");
            });
        });

        it("Returns existing event on duplicate date+type", () => {
            // Create first
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId: 2 },
                200,
            ).then((first) => {
                // Create again — should return the same event
                cy.makePrivateAdminAPICall(
                    "POST",
                    "/api/events/quick-create",
                    { eventTypeId: 2 },
                    200,
                ).then((second) => {
                    expect(second.body.eventId).to.equal(first.body.eventId);
                    expect(second.body.created).to.be.false;
                });
            });
        });

        it("Creates event from groupId without eventTypeId", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { groupId: 1 },
                200,
            ).then((response) => {
                expect(response.body).to.have.property("eventId");
                expect(response.body.title).to.include("Angels class");
            });
        });

        it("Returns 400 without eventTypeId or groupId", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                {},
                400,
            );
        });

        it("Returns 400 for invalid event type ID", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId: 999999 },
                400,
            );
        });

        it("Returns 401 when not authenticated", () => {
            cy.apiRequest({
                method: "POST",
                url: "/api/events/quick-create",
                body: { eventTypeId: 1 },
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });

    describe("GET /api/events/today", () => {
        it("Returns today's events array", () => {
            // Ensure at least one event exists today
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId: 1 },
                200,
            ).then(() => {
                cy.makePrivateAdminAPICall(
                    "GET",
                    "/api/events/today",
                    null,
                    200,
                ).then((response) => {
                    expect(response.body).to.have.property("events");
                    expect(response.body.events).to.be.an("array");
                    expect(response.body.events.length).to.be.at.least(1);

                    const event = response.body.events[0];
                    expect(event).to.have.property("id");
                    expect(event).to.have.property("title");
                    expect(event).to.have.property("start");
                    expect(event).to.have.property("checkedIn");
                    expect(event).to.have.property("totalAttendees");
                });
            });
        });

        it("Returns 401 when not authenticated", () => {
            cy.apiRequest({
                method: "GET",
                url: "/api/events/today",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });

    describe("Event Roster and Check-in/out", () => {
        let testEventId;

        before(() => {
            cy.setupAdminSession();
            // Create a fresh event linked to group 1 (Angels class)
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId: 2, groupId: 1 },
                200,
            ).then((response) => {
                testEventId = response.body.eventId;
            });
        });

        describe("GET /api/events/{id}/roster", () => {
            it("Returns roster with members and stats", function () {
                if (!testEventId) this.skip();

                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/events/${testEventId}/roster`,
                    null,
                    200,
                ).then((response) => {
                    expect(response.body).to.have.property("event");
                    expect(response.body.event.id).to.equal(testEventId);
                    expect(response.body).to.have.property("groups");
                    expect(response.body.groups).to.be.an("array");
                    expect(response.body).to.have.property("stats");
                    expect(response.body.stats).to.have.property("total");
                    expect(response.body.stats).to.have.property("checkedIn");
                    expect(response.body).to.have.property("members");
                    expect(response.body.members).to.be.an("array");

                    if (response.body.members.length > 0) {
                        const member = response.body.members[0];
                        expect(member).to.have.property("personId");
                        expect(member).to.have.property("firstName");
                        expect(member).to.have.property("lastName");
                        expect(member).to.have.property("status");
                        expect(member.status).to.be.oneOf([
                            "checked_in",
                            "checked_out",
                            "not_checked_in",
                        ]);
                    }
                });
            });

            it("Returns empty roster for event with no groups", () => {
                // Event 2 (Christmas Service) has no event_audience linking
                cy.makePrivateAdminAPICall(
                    "GET",
                    "/api/events/2/roster",
                    null,
                    200,
                ).then((response) => {
                    expect(response.body.members).to.be.an("array");
                    expect(response.body.members.length).to.equal(0);
                    expect(response.body.stats.total).to.equal(0);
                });
            });
        });

        describe("POST /api/events/{id}/checkin", () => {
            it("Checks in a person and returns status", function () {
                if (!testEventId) this.skip();

                // Get a person from the roster first
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/events/${testEventId}/roster`,
                    null,
                    200,
                ).then((rosterResponse) => {
                    if (rosterResponse.body.members.length === 0) {
                        this.skip();
                        return;
                    }
                    const personId = rosterResponse.body.members[0].personId;

                    cy.makePrivateAdminAPICall(
                        "POST",
                        `/api/events/${testEventId}/checkin`,
                        { personId },
                        200,
                    ).then((response) => {
                        expect(response.body.success).to.be.true;
                        expect(response.body.status).to.equal("checked_in");
                        expect(response.body).to.have.property("checkinTime");
                    });
                });
            });

            it("Returns 400 for missing personId", function () {
                if (!testEventId) this.skip();

                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${testEventId}/checkin`,
                    {},
                    400,
                );
            });
        });

        describe("POST /api/events/{id}/checkout", () => {
            it("Checks out a previously checked-in person", function () {
                if (!testEventId) this.skip();

                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/events/${testEventId}/roster`,
                    null,
                    200,
                ).then((rosterResponse) => {
                    const checkedIn = rosterResponse.body.members.find(
                        (m) => m.status === "checked_in",
                    );
                    if (!checkedIn) {
                        this.skip();
                        return;
                    }

                    cy.makePrivateAdminAPICall(
                        "POST",
                        `/api/events/${testEventId}/checkout`,
                        { personId: checkedIn.personId },
                        200,
                    ).then((response) => {
                        expect(response.body.success).to.be.true;
                        expect(response.body.status).to.equal("checked_out");
                        expect(response.body).to.have.property("checkoutTime");
                    });
                });
            });
        });

        describe("POST /api/events/{id}/checkin-all", () => {
            it("Batch checks in all group members", function () {
                if (!testEventId) this.skip();

                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${testEventId}/checkin-all`,
                    null,
                    200,
                ).then((response) => {
                    expect(response.body.success).to.be.true;
                    expect(response.body.checkedIn).to.be.a("number");
                    expect(response.body.checkedIn).to.be.at.least(0);
                });
            });

            it("Verifies all members show as checked in after batch", function () {
                if (!testEventId) this.skip();

                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/events/${testEventId}/roster`,
                    null,
                    200,
                ).then((response) => {
                    const notCheckedIn = response.body.members.filter(
                        (m) => m.status === "not_checked_in",
                    );
                    expect(notCheckedIn.length).to.equal(0);
                });
            });
        });

        describe("POST /api/events/{id}/checkout-all", () => {
            it("Batch checks out all checked-in people", function () {
                if (!testEventId) this.skip();

                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${testEventId}/checkout-all`,
                    null,
                    200,
                ).then((response) => {
                    expect(response.body.success).to.be.true;
                    expect(response.body.checkedOut).to.be.a("number");
                    expect(response.body.checkedOut).to.be.at.least(0);
                });
            });
        });
    });

    describe("POST /api/events/generate-recurring", () => {
        it("Generates weekly recurring events from event type", () => {
            const today = new Date();
            const fourWeeksLater = new Date(
                today.getTime() + 28 * 24 * 60 * 60 * 1000,
            );
            const startDate = today.toISOString().slice(0, 10);
            const endDate = fourWeeksLater.toISOString().slice(0, 10);

            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/generate-recurring",
                {
                    eventTypeId: 1,
                    startDate,
                    endDate,
                    skipExisting: true,
                },
                200,
            ).then((response) => {
                expect(response.body).to.have.property("created");
                expect(response.body.created).to.be.a("number");
                expect(response.body).to.have.property("skipped");
                expect(response.body).to.have.property("events");
                expect(response.body.events).to.be.an("array");
                expect(response.body.events.length).to.equal(
                    response.body.created,
                );

                if (response.body.events.length > 0) {
                    const evt = response.body.events[0];
                    expect(evt).to.have.property("id");
                    expect(evt).to.have.property("title");
                    expect(evt).to.have.property("date");
                }
            });
        });

        it("Skips existing events when skipExisting is true", () => {
            const today = new Date();
            const fourWeeksLater = new Date(
                today.getTime() + 28 * 24 * 60 * 60 * 1000,
            );
            const startDate = today.toISOString().slice(0, 10);
            const endDate = fourWeeksLater.toISOString().slice(0, 10);

            // Run twice — second call should skip all
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/generate-recurring",
                { eventTypeId: 1, startDate, endDate, skipExisting: true },
                200,
            ).then((first) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    "/api/events/generate-recurring",
                    {
                        eventTypeId: 1,
                        startDate,
                        endDate,
                        skipExisting: true,
                    },
                    200,
                ).then((second) => {
                    expect(second.body.created).to.equal(0);
                    expect(second.body.skipped).to.be.at.least(
                        first.body.created,
                    );
                });
            });
        });

        it("Returns 400 for invalid event type ID", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/generate-recurring",
                {
                    eventTypeId: 999999,
                    startDate: "2026-01-01",
                    endDate: "2026-03-31",
                },
                400,
            );
        });

        it("Returns 400 for missing event type ID", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/generate-recurring",
                { startDate: "2026-01-01", endDate: "2026-03-31" },
                400,
            );
        });

        it("Returns 400 when endDate is before startDate", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/generate-recurring",
                {
                    eventTypeId: 1,
                    startDate: "2026-06-01",
                    endDate: "2026-01-01",
                },
                400,
            );
        });

        it("Returns 400 when date range exceeds 1 year", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/generate-recurring",
                {
                    eventTypeId: 1,
                    startDate: "2026-01-01",
                    endDate: "2028-01-01",
                },
                400,
            );
        });

        it("Returns 401 when not authenticated", () => {
            cy.apiRequest({
                method: "POST",
                url: "/api/events/generate-recurring",
                body: {
                    eventTypeId: 1,
                    startDate: "2026-01-01",
                    endDate: "2026-03-31",
                },
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });
});
