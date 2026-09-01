/// <reference types="cypress" />

/**
 * API tests for the bulk-geocode endpoint
 * POST /api/map/geocode-all — geocodes active families missing coordinates
 *
 * This endpoint makes live Nominatim API calls during a real run, so the
 * happy-path test only asserts the response *shape* (not specific geocoded
 * values) to stay deterministic in CI. The 401/403 tests verify auth guards.
 */
describe("API Private Map — POST /api/map/geocode-all", () => {
    context("Happy path (admin)", () => {
        it("Returns 200 with a valid summary shape", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/map/geocode-all",
                {},
                200,
            ).then((response) => {
                // Response must have all four summary fields
                expect(response.body).to.have.property("total");
                expect(response.body).to.have.property("geocoded");
                expect(response.body).to.have.property("failed");
                expect(response.body).to.have.property("remaining");

                // All values must be non-negative integers
                expect(response.body.total).to.be.a("number").and.be.at.least(0);
                expect(response.body.geocoded).to.be.a("number").and.be.at.least(0);
                expect(response.body.failed).to.be.a("number").and.be.at.least(0);
                expect(response.body.remaining).to.be.a("number").and.be.at.least(0);

                // geocoded + failed should not exceed total processed in this batch
                expect(response.body.geocoded + response.body.failed).to.be.at.most(
                    response.body.total,
                );
            });
        });
    });

    context("Authentication", () => {
        it("Returns 401 when no API key is supplied", () => {
            cy.apiRequest({
                method: "POST",
                url: "/api/map/geocode-all",
                body: {},
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });

    context("Authorization", () => {
        it("Returns 403 for a non-admin authenticated user", () => {
            // Uses tony.wade (user.api.key): EditRecords=1, Admin=0.
            // This user passes AuthMiddleware (not EditSelf-exclusive) but lacks
            // Admin=1, so AdminRoleAuthMiddleware correctly returns 403.
            cy.makePrivateUserAPICall("POST", "/api/map/geocode-all", {}, 403);
        });
    });
});
