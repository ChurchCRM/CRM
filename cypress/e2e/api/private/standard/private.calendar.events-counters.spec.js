/// <reference types="cypress" />

/**
 * API tests for Calendar Events Counters endpoint
 * Tests the /api/calendar/events-counters endpoint that provides
 * today's birthdays, anniversaries, and events counts for menu badges
 */
describe("API Private Calendar Events Counters", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /api/calendar/events-counters", () => {
        it("Returns 200 with event counter data", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/calendar/events-counters",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.exist;
                expect(response.body).to.have.property("Birthdays");
                expect(response.body).to.have.property("Anniversaries");
                expect(response.body).to.have.property("Events");
            });
        });

        it("Returns numeric values for all counters", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/calendar/events-counters",
                null,
                200,
            ).then((response) => {
                expect(response.body.Birthdays).to.be.a("number");
                expect(response.body.Anniversaries).to.be.a("number");
                expect(response.body.Events).to.be.a("number");
            });
        });

        it("Returns non-negative values for all counters", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/calendar/events-counters",
                null,
                200,
            ).then((response) => {
                expect(response.body.Birthdays).to.be.at.least(0);
                expect(response.body.Anniversaries).to.be.at.least(0);
                expect(response.body.Events).to.be.at.least(0);
            });
        });
    });

    describe("GET /api/calendar/events-counters - Authentication", () => {
        it("Returns 401 when not authenticated", () => {
            cy.apiRequest({
                method: "GET",
                url: "/api/calendar/events-counters",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });
});
