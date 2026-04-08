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

                    // If any results, each must have the expected shape.
                    // stillCheckedIn can be 0 (old stale active events) or
                    // positive (someone forgot to check out) — both qualify.
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
                        expect(e.stillCheckedIn).to.be.a("number").and.be.at.least(0);
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

        it("closes a stuck event end-to-end (create → check in → audit → close)", function () {
            // 1. Find an event type to use. The /api/events/types endpoint returns
            // a Propel ObjectCollection serialized as an OBJECT keyed by index
            // ("0", "1", ...) — not a true JS array. Normalize to an array of values
            // and skip the test gracefully if no types are seeded.
            cy.makePrivateAdminAPICall("GET", "/api/events/types", null, [200, 404]).then(
                (typesResp) => {
                    if (typesResp.status !== 200 || !typesResp.body) {
                        this.skip();
                        return;
                    }
                    const types = Array.isArray(typesResp.body)
                        ? typesResp.body
                        : Object.values(typesResp.body);
                    if (types.length === 0 || !types[0] || !types[0].Id) {
                        this.skip();
                        return;
                    }
                    const eventTypeId = types[0].Id;

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
