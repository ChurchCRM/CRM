/// <reference types="cypress" />

/**
 * API tests for the event audit endpoints:
 * - GET  /events/audit/stuck   — find past events still active with people
 *                                 checked in but never checked out
 * - POST /events/audit/close   — batch close (check-out all + deactivate)
 */
describe("API Event Audit Endpoints", () => {
    // No browser login — these are pure API tests using x-api-key auth
    // (cy.makePrivateAdminAPICall sets the header for us).

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

        it("closes a stuck event end-to-end (create → check in → audit → close)", () => {
            // 1. Find an event type. /api/events/types returns a Propel
            // ObjectCollection serialized as an OBJECT keyed by index — not a
            // true JS array. Normalize via Object.values() before reading [0].
            cy.makePrivateAdminAPICall("GET", "/api/events/types", null, 200).then((typesResp) => {
                const types = Array.isArray(typesResp.body)
                    ? typesResp.body
                    : Object.values(typesResp.body);
                expect(types.length, "at least one event type must be seeded").to.be.greaterThan(0);
                expect(types[0]).to.have.property("Id");
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

                    // 3. Check person 1 in (no checkout). The event is in the
                    // past so the inactive guard does NOT apply (the event is
                    // still active by default), so this must succeed with 200.
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `/api/events/${eventId}/checkin`,
                        { personId: 1 },
                        200,
                    );

                    // 4. Close it via the audit endpoint
                    cy.makePrivateAdminAPICall(
                        "POST",
                        "/api/events/audit/close",
                        { eventIds: [eventId], checkoutPeople: true, deactivate: true },
                        200,
                    ).then((closeResp) => {
                        expect(closeResp.body).to.have.property("success", true);
                        expect(closeResp.body).to.have.property("eventsClosed");
                        expect(closeResp.body).to.have.property("peopleCheckedOut");
                        expect(closeResp.body.eventsClosed).to.be.greaterThan(0);
                        expect(closeResp.body.peopleCheckedOut).to.be.greaterThan(0);
                    });
                });
            });
        });
    });
});
