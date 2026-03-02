/// <reference types="cypress" />

/**
 * Regression tests for the Anniversaries calendar system calendar (ID=1).
 *
 * Bug fixed: MySQL 8.0 strict mode rejected the empty-string DATE comparison
 * `WHERE fam_WeddingDate <> ''` with SQLSTATE[HY000]: 1525.
 * The query was changed to `WHERE fam_WeddingDate IS NOT NULL`.
 */
describe("API Private Admin Anniversaries Calendar", () => {
    describe("GET /api/systemcalendars/1/fullcalendar", () => {
        it("Returns 200 and a valid event array (regression: MySQL 8.0 strict-mode DATE error)", () => {
            const start = "2026-01-01T00:00:00";
            const end = "2026-12-31T00:00:00";
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/systemcalendars/1/fullcalendar?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`,
                null,
                200,
            ).then((response) => {
                expect(response.body).to.be.an("array");
                // The seed data contains families with non-null wedding dates,
                // so at least one anniversary event is expected.
                expect(response.body.length).to.be.greaterThan(0);
                const event = response.body[0];
                expect(event).to.have.property("title");
                expect(event).to.have.property("start");
            });
        });

        it("Each anniversary event has required FullCalendar fields", () => {
            const start = "2026-01-01T00:00:00";
            const end = "2026-12-31T00:00:00";
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/systemcalendars/1/fullcalendar?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`,
                null,
                200,
            ).then((response) => {
                response.body.forEach((event) => {
                    expect(event).to.have.property("title");
                    expect(event.title).to.include("Anniversary");
                    expect(event).to.have.property("start");
                    expect(event).to.have.property("url");
                });
            });
        });
    });

    describe("GET /api/systemcalendars/1/events", () => {
        it("Returns 200 with anniversary events array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/systemcalendars/1/events",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.be.an("array");
            });
        });
    });
});
