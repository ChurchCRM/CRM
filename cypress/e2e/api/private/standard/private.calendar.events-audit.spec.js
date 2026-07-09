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
            // 1. Quick-create a fresh past-dated event using the seeded
            // "Church Service" type (id 1), the same way the other passing
            // event API specs do.
            const past = new Date(Date.now() - 24 * 60 * 60 * 1000); // 24h ago
            const yyyy = past.getFullYear();
            const mm = String(past.getMonth() + 1).padStart(2, "0");
            const dd = String(past.getDate()).padStart(2, "0");

            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId: 1, date: `${yyyy}-${mm}-${dd}` },
                200,
            ).then((createResp) => {
                const eventId = createResp.body.eventId;
                expect(eventId).to.be.a("number");

                // 2. Check person 1 in (no checkout). The event is active by
                // default so the inactive guard does NOT apply.
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${eventId}/checkin`,
                    { personId: 1 },
                    200,
                );

                // 3. Close it via the audit endpoint
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
