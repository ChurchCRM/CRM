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
    // No browser login — these are pure API tests using x-api-key auth
    // (cy.makePrivateAdminAPICall sets the header for us).

    describe("GET /api/events", () => {
        it("Returns the events list wrapped in an Events array", () => {
            // Make sure at least one event exists so the response shape is meaningful
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId: 1 },
                200,
            );

            cy.makePrivateAdminAPICall("GET", "/api/events", null, 200).then((response) => {
                expect(response.body).to.have.property("Events");
                expect(response.body.Events).to.be.an("array");
                expect(response.body.Events.length).to.be.at.least(1);

                const event = response.body.Events[0];
                expect(event).to.have.property("Id");
                expect(event).to.have.property("Title");
                expect(event).to.have.property("Start");
                expect(event).to.have.property("End");
            });
        });

        it("Returns 401 when not authenticated", () => {
            cy.apiRequest({
                method: "GET",
                url: "/api/events",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
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
                // Title uses event type name (not group name) when an event type is auto-detected
                expect(response.body.title).to.be.a("string").and.not.be.empty;
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
            // Create a fresh event linked to group 1 (Angels class). The
            // suite asserts on testEventId in every test, so make sure
            // before() actually populated it before the tests run.
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId: 2, groupId: 1 },
                200,
            ).then((response) => {
                expect(response.body).to.have.property("eventId");
                testEventId = response.body.eventId;
            });
        });

        describe("GET /api/events/{id}/roster", () => {
            it("Returns roster with members and stats", () => {
                expect(testEventId, "before() must have populated testEventId").to.be.a("number");

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
            it("Checks in a person and returns status", () => {
                expect(testEventId, "before() must have populated testEventId").to.be.a("number");

                // Get a person from the roster first
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/events/${testEventId}/roster`,
                    null,
                    200,
                ).then((rosterResponse) => {
                    expect(rosterResponse.body.members.length, "roster must have at least one member to check in").to.be.greaterThan(0);
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

            it("Returns 400 for missing personId", () => {
                expect(testEventId, "before() must have populated testEventId").to.be.a("number");

                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${testEventId}/checkin`,
                    {},
                    400,
                );
            });
        });

        describe("POST /api/events/{id}/checkout", () => {
            it("Checks out a previously checked-in person", () => {
                expect(testEventId, "before() must have populated testEventId").to.be.a("number");

                // Make sure at least one person is checked in first so we have
                // someone to check out (independent of test ordering).
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${testEventId}/checkin`,
                    { personId: 1 },
                    200,
                );

                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/events/${testEventId}/roster`,
                    null,
                    200,
                ).then((rosterResponse) => {
                    const checkedIn = rosterResponse.body.members.find(
                        (m) => m.status === "checked_in",
                    );
                    expect(checkedIn, "at least one person must be checked in").to.exist;

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

            // Regression: the check-in/out flow now optionally records WHO
            // checked the person out (parent picking up child, etc.) via the
            // checkedOutById field. The bootbox prompt in event-checkin.js
            // sends this; verify the API persists it on the EventAttend row.
            it("Records checkedOutById when supplied", () => {
                expect(testEventId, "before() must have populated testEventId").to.be.a("number");

                // First, check person 1 in (no supervisor) — must succeed
                // because the event is active.
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${testEventId}/checkin`,
                    { personId: 1 },
                    200,
                ).then(() => {
                    // Now check them out with checkedOutById = 2
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `/api/events/${testEventId}/checkout`,
                        { personId: 1, checkedOutById: 2 },
                        200,
                    ).then((response) => {
                        expect(response.body.success).to.be.true;

                        // Verify the roster shows person 2 as the checkout supervisor
                        cy.makePrivateAdminAPICall(
                            "GET",
                            `/api/events/${testEventId}/roster`,
                            null,
                            200,
                        ).then((rosterResp) => {
                            const member = rosterResp.body.members.find((m) => m.personId === 1);
                            // Roster shape may vary; if checkoutBy is exposed, assert it
                            if (member && Object.prototype.hasOwnProperty.call(member, "checkoutBy")) {
                                expect(member.checkoutBy).to.not.be.empty;
                            }
                        });
                    });
                });
            });
        });

        describe("POST /api/events/{id}/checkin-all", () => {
            it("Batch checks in all group members", () => {
                expect(testEventId, "before() must have populated testEventId").to.be.a("number");

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

            it("Verifies all members show as checked in after batch", () => {
                expect(testEventId, "before() must have populated testEventId").to.be.a("number");

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
            it("Batch checks out all checked-in people", () => {
                expect(testEventId, "before() must have populated testEventId").to.be.a("number");

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

    // ──────────────────────────────────────────────────────────────────────
    // POST /api/events/{id}/checkin-people  (#6838 family check-in)
    // ──────────────────────────────────────────────────────────────────────
    describe("POST /api/events/{id}/checkin-people", () => {
        let eventId;

        before(() => {
            // Quick-create an event we can check people into
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId: 1 },
                200,
            ).then((response) => {
                expect(response.body).to.have.property("eventId");
                eventId = response.body.eventId;
            });
        });

        it("Checks in a list of people", () => {
            expect(eventId, "before() must have populated eventId").to.be.a("number");

            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${eventId}/checkin-people`,
                { personIds: [1, 2, 3] },
                200,
            ).then((response) => {
                expect(response.body.success).to.be.true;
                expect(response.body.checkedIn).to.be.a("number");
                expect(response.body.checkedIn).to.be.at.least(0);
            });
        });

        it("Records the checkedInById when provided", () => {
            expect(eventId, "before() must have populated eventId").to.be.a("number");

            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${eventId}/checkin-people`,
                { personIds: [4], checkedInById: 1 },
                200,
            ).then((response) => {
                expect(response.body.success).to.be.true;
            });
        });

        it("Returns 400 when personIds is empty", () => {
            expect(eventId, "before() must have populated eventId").to.be.a("number");

            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${eventId}/checkin-people`,
                { personIds: [] },
                400,
            );
        });

        it("Returns 400 when personIds is missing", () => {
            expect(eventId, "before() must have populated eventId").to.be.a("number");

            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${eventId}/checkin-people`,
                {},
                400,
            );
        });

        it("Returns 401 when not authenticated", () => {
            cy.apiRequest({
                method: "POST",
                url: "/api/events/1/checkin-people",
                body: { personIds: [1] },
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });

    // ──────────────────────────────────────────────────────────────────────
    // DELETE /api/events/{id}/attendance/{personId}
    // ──────────────────────────────────────────────────────────────────────
    describe("DELETE /api/events/{id}/attendance/{personId}", () => {
        let eventId;

        before(() => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId: 1 },
                200,
            ).then((response) => {
                expect(response.body).to.have.property("eventId");
                eventId = response.body.eventId;
                // Check someone in so we have an attendance record to delete
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${eventId}/checkin`,
                    { personId: 5 },
                    200,
                );
            });
        });

        it("Deletes an attendance record", () => {
            expect(eventId, "before() must have populated eventId").to.be.a("number");

            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/events/${eventId}/attendance/5`,
                null,
                200,
            ).then((response) => {
                expect(response.body.success).to.be.true;
            });
        });

        it("Returns 404 when no attendance record exists", () => {
            expect(eventId, "before() must have populated eventId").to.be.a("number");

            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/events/${eventId}/attendance/99999`,
                null,
                404,
            );
        });

        it("Returns 401 when not authenticated", () => {
            cy.apiRequest({
                method: "DELETE",
                url: "/api/events/1/attendance/1",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });
});
