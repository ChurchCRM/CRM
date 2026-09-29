/// <reference types="cypress" />

/**
 * API tests for DonationFund READ-ONLY endpoints
 *
 * Write operations (POST / PUT / DELETE) have moved to the admin-only
 * /finance/api/funds endpoint (see finance-funds.spec.js).
 *
 * Covers:
 *   GET    /api/donation-funds
 *   GET    /api/donation-funds/{id}
 */
describe("API Private Donation Funds (read-only)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /api/donation-funds - List funds", () => {
        it("Returns 200 with funds array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/donation-funds",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("funds");
                expect(response.body.funds).to.be.an("array");
            });
        });

        it("Respects activeOnly filter", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/donation-funds?activeOnly=true",
                null,
                200,
            ).then((response) => {
                expect(response.body.funds).to.be.an("array");
                response.body.funds.forEach((f) => {
                    expect(f.active).to.equal(true);
                });
            });
        });
    });

    describe("GET /api/donation-funds/{id}", () => {
        it("Returns 404 for non-existent fund", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/donation-funds/999999",
                null,
                404,
            );
        });

        it("Returns 200 with fund object for seeded fund", () => {
            // Use fund id=1 which always exists in the test seed data
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/donation-funds/1",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("fund");
                expect(response.body.fund.id).to.equal(1);
                expect(response.body.fund).to.have.property("name");
                expect(response.body.fund).to.have.property("active");
            });
        });
    });

    describe("Access control", () => {
        it("Returns 401 when no API key is provided", () => {
            cy.clearCookies();
            cy.request({
                method: "GET",
                url: "/api/donation-funds",
                failOnStatusCode: false,
                headers: { "content-type": "application/json" },
            }).then((response) => {
                expect(response.status).to.equal(401);
            });
        });

        it("Denies a caller without Finance permission", () => {
            cy.makePrivateNoFinanceAPICall(
                "GET",
                "/api/donation-funds",
                null,
                [401, 403],
            );
        });
    });
});
