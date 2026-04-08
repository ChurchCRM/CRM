/// <reference types="cypress" />

/**
 * API tests for the event audit endpoints:
 * - GET  /events/audit/stuck   — find past events still active with people
 *                                 checked in but never checked out
 * - POST /events/audit/close   — batch close (check-out all + deactivate)
 */
describe("API Event Audit Endpoints", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /api/events/audit/stuck", () => {
        it("returns a count + events array", () => {
            cy.makePrivateAdminAPICall("GET", "/api/events/audit/stuck", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("count");
                    expect(response.body.count).to.be.a("number");
                    expect(response.body).to.have.property("events");
                    expect(response.body.events).to.be.an("array");

                    // If any results, each must have the expected shape
                    if (response.body.events.length > 0) {
                        const e = response.body.events[0];
                        expect(e).to.have.all.keys(
                            "id",
                            "title",
                            "typeName",
                            "start",
                            "end",
                            "stillCheckedIn",
                        );
                        expect(e.stillCheckedIn).to.be.greaterThan(0);
                    }
                },
            );
        });
    });

    describe("POST /api/events/audit/close", () => {
        it("rejects empty eventIds with 400", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/audit/close",
                { eventIds: [] },
                400,
            );
        });

        it("rejects missing eventIds with 400", () => {
            cy.makePrivateAdminAPICall("POST", "/api/events/audit/close", {}, 400);
        });

        it("closes a stuck event end-to-end (create → check in → audit → close)", () => {
            // 1. Find an event type to use
            cy.makePrivateAdminAPICall("GET", "/api/events/types", null, [200, 404]).then(
                (typesResp) => {
                    if (typesResp.status !== 200 || !typesResp.body || typesResp.body.length === 0) {
                        return;
                    }
                    const eventTypeId = typesResp.body[0].Id;

                    // 2. Create a fresh event in the past
                    const past = new Date(Date.now() - 24 * 60 * 60 * 1000); // 24h ago
                    const yyyy = past.getFullYear();
                    const mm = String(past.getMonth() + 1).padStart(2, "0");
                    const dd = String(past.getDate()).padStart(2, "0");

                    cy.makePrivateAdminAPICall(
                        "POST",
                        "/api/events/quick-create",
                        { eventTypeId, date: `${yyyy}-${mm}-${dd}` },
                        200,
                    ).then((createResp) => {
                        const eventId = createResp.body.eventId;
                        expect(eventId).to.be.a("number");

                        // 3. Check person 1 in (no checkout)
                        cy.makePrivateAdminAPICall(
                            "POST",
                            `/api/events/${eventId}/checkin`,
                            { personId: 1 },
                            [200, 409],
                        );

                        // 4. Force the event end into the past so the audit picks it up
                        cy.makePrivateAdminAPICall(
                            "POST",
                            `/api/events/${eventId}`,
                            {
                                Title: `Audit-test-${Date.now()}`,
                                Type: eventTypeId,
                                Start: `${yyyy}-${mm}-${dd} 09:00:00`,
                                End: `${yyyy}-${mm}-${dd} 10:00:00`,
                                Inactive: 0,
                            },
                            [200, 400],
                        );

                        // 5. Close it via the audit endpoint
                        cy.makePrivateAdminAPICall(
                            "POST",
                            "/api/events/audit/close",
                            { eventIds: [eventId], checkoutPeople: true, deactivate: true },
                            200,
                        ).then((closeResp) => {
                            expect(closeResp.body).to.have.property("success", true);
                            expect(closeResp.body).to.have.property("eventsClosed");
                            expect(closeResp.body).to.have.property("peopleCheckedOut");
                        });
                    });
                },
            );
        });
    });
});
