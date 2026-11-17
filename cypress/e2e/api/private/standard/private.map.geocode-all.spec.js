/// <reference types="cypress" />

const GEOCODE_TEST_FAMILY_ID = 45;
const GEOCODE_TEST_LOCATION = { latitude: 39.1116191, longitude: -94.5838027 };

/**
 * API tests for the bulk-geocode endpoint
 * POST /api/map/geocode-all — geocodes active families missing coordinates
 *
 * The response-shape and authorization tests skip past all missing coordinates.
 * The skip-offset coverage geocodes one dedicated seed address.
 */
describe("API Private Map — POST /api/map/geocode-all", { retries: 2 }, () => {
    context("Happy path", () => {
        it("Returns 200 with a valid summary shape", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/map/geocode-all",
                { skip: Number.MAX_SAFE_INTEGER },
                200,
                30000,
            ).then((response) => {
                expect(response.body.skip).to.equal(Number.MAX_SAFE_INTEGER);
                expect(response.body.processed).to.equal(0);

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
                expect(response.body.remainingNotFound).to.be.a("number").and.be.within(0, response.body.remaining);

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
                        expect(["incomplete_address", "no_result", "unavailable", "error"]).to.include(f.reason);
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

    context("skip offset (#9846)", () => {
        it("Skips the pending geocode address", () => {
            cy.makePrivateAdminAPICall("POST", "/api/map/geocode-all", { skip: 1 }, 200, 30000).then(
                (response) => {
                    expect(response.body.skip).to.equal(1);
                    expect(response.body.processed).to.equal(0);
                },
            );
        });

        it("Omitting skip defaults to zero and geocodes the pending address", { retries: 0 }, () => {
            cy.makePrivateAdminAPICall("POST", "/api/map/geocode-all", {}, 200, 30000).then((response) => {
                expect(response.body.skip).to.equal(0);
                expect(response.body.total).to.equal(1);
                expect(response.body.processed).to.equal(1);
                expect(response.body.geocoded).to.equal(1);
                expect(response.body.failed).to.equal(0);
            });

            cy.makePrivateAdminAPICall("GET", `/api/family/${GEOCODE_TEST_FAMILY_ID}`, null, 200).then((response) => {
                expect(response.body.Name).to.equal("Geocode Test Family");
                expect(response.body.Latitude).to.be.closeTo(GEOCODE_TEST_LOCATION.latitude, 0.001);
                expect(response.body.Longitude).to.be.closeTo(GEOCODE_TEST_LOCATION.longitude, 0.001);
            });
        });

        it("Rejects a negative skip with 400", () => {
            cy.makePrivateAdminAPICall("POST", "/api/map/geocode-all", { skip: -1 }, 400, 30000);
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
        it("Allows a user with Edit Records who is not an admin", () => {
            // tony.wade (user.api.key): EditRecords=1, Admin=0. Skipping past every family
            // keeps the call off the geocoding services.
            cy.makePrivateUserAPICall("POST", "/api/map/geocode-all", { skip: 100000 }, 200, 30000);
        });

        it("Returns 403 for a user without Edit Records", () => {
            cy.makePrivatePlainAuthAPICall("POST", "/api/map/geocode-all", {}, 403);
        });
    });
});
