/// <reference types="cypress" />

/**
 * API tests for the bulk-geocode endpoint
 * POST /api/map/geocode-all — geocodes active families missing coordinates
 *
 * This endpoint makes live Nominatim API calls during a real run, so the
 * happy-path test only asserts the response *shape* (not specific geocoded
 * values) to stay deterministic in CI. The 401/403 tests verify auth guards.
 *
 * The endpoint processes up to 50 families at ~1 req/sec, so the worst-case
 * server response time is ~50 s. Pass an explicit 120 000 ms timeout so the
 * cy.request() command does not time out before the server replies.
 */
describe("API Private Map — POST /api/map/geocode-all", () => {
    context("Happy path (admin)", () => {
        it("Returns 200 with a valid summary shape", () => {
            // Pass an explicit 120-second timeout — the default (30 s) is too
            // short when the CI database has many families missing coordinates.
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/map/geocode-all",
                {},
                200,
                120000,
            ).then((response) => {
                // Core summary fields must all be present
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

                // Failure-detail fields must always be present
                expect(response.body).to.have.property("failures");
                expect(response.body.failures).to.be.an("array");
                expect(response.body).to.have.property("failuresTruncated");
                expect(response.body.failuresTruncated).to.be.a("boolean");

                // When there are failures, each entry must carry diagnostic fields
                if (response.body.failed > 0) {
                    // failures array must not be empty when failed > 0
                    // (it can be shorter than failed when failuresTruncated is true,
                    //  but it must have at least one entry so the UI has something to show)
                    expect(response.body.failures.length).to.be.at.least(1);

                    for (const f of response.body.failures) {
                        expect(f).to.have.property("id").that.is.a("number");
                        expect(f).to.have.property("name").that.is.a("string");
                        expect(f).to.have.property("address").that.is.a("string");
                        expect(f).to.have.property("editUrl").that.is.a("string");
                        expect(f).to.have.property("reason").that.is.a("string");
                        // Reason must be one of the three machine codes
                        expect(["incomplete_address", "no_result", "error"]).to.include(f.reason);
                    }

                    // failuresTruncated must be true iff failures.length < failed
                    expect(response.body.failuresTruncated).to.equal(
                        response.body.failures.length < response.body.failed,
                    );
                } else {
                    // No failures → failures array must be empty and not truncated
                    expect(response.body.failures).to.have.length(0);
                    expect(response.body.failuresTruncated).to.equal(false);
                }
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
