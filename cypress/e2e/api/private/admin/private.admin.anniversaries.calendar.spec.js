/// <reference types="cypress" />

/**
 * Regression tests for the Anniversaries calendar system calendar (ID=1).
 *
 * Bug fixed: MySQL 8.0 strict mode rejected the empty-string DATE comparison
 * `WHERE fam_WeddingDate <> ''` with SQLSTATE[HY000]: 1525.
 * The query was changed to `WHERE fam_WeddingDate IS NOT NULL`.
 */
describe("API Private Admin Anniversaries Calendar", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /api/systemcalendars/1/fullcalendar", () => {
        it("Returns 200 and valid event array (regression: MySQL 8.0 strict-mode DATE error)", () => {
            const start = "2026-01-01T00:00:00";
            const end = "2026-12-31T00:00:00";
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/systemcalendars/1/fullcalendar?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`,
                null,
                200,
            ).then((response) => {
                // Verify response is an array
                expect(response.body).to.be.an("array");
                cy.log(`✅ Anniversaries endpoint returned ${response.body.length} events`);
                
                // If events exist, verify their structure
                if (response.body.length > 0) {
                    const event = response.body[0];
                    expect(event).to.have.property("title");
                    expect(event).to.have.property("start");
                    cy.log(`✅ Anniversary event structure is valid: ${event.title}`);
                } else {
                    cy.log("✅ No anniversary events found (seed data may not have wedding dates)");
                }
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
                // Skip validation if no events; test still passes as API is accessible
                if (response.body.length === 0) {
                    cy.log("✅ Anniversary event endpoint accessible (no events in date range)");
                    return;
                }

                response.body.forEach((event) => {
                    expect(event).to.have.property("title");
                    expect(event.title).to.include("Anniversary");
                    expect(event).to.have.property("start");
                    expect(event).to.have.property("url");
                });
                cy.log(`✅ All ${response.body.length} anniversary events have required fields`);
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
                cy.log(`✅ Anniversary events endpoint accessible (${response.body.length} events)`);
            });
        });
    });
});
