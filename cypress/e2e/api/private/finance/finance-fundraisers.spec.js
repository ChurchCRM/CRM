/// <reference types="cypress" />

/**
 * API tests for Fundraiser CRUD endpoints
 *
 * Covers:
 *   GET    /api/fundraisers
 *   POST   /api/fundraisers
 *   GET    /api/fundraisers/{id}
 *   PUT    /api/fundraisers/{id}
 *   DELETE /api/fundraisers/{id}
 */
describe("API Private Fundraisers", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /api/fundraisers - List fundraisers", () => {
        it("Returns 200 with fundraisers array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/fundraisers",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("fundraisers");
                expect(response.body.fundraisers).to.be.an("array");
            });
        });
    });

    describe("POST /api/fundraisers - Create fundraiser", () => {
        it("Creates a fundraiser and returns 201 with fundraiser object", () => {
            const title = `Cypress Test Fundraiser ${Date.now()}`;
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                {
                    title,
                    description: "Created by Cypress",
                    date: "2026-06-15",
                },
                201,
            ).then((response) => {
                expect(response.body).to.have.property("fundraiser");
                const fr = response.body.fundraiser;
                expect(fr.id).to.be.a("number");
                expect(fr.title).to.equal(title);
                expect(fr.description).to.equal("Created by Cypress");
                expect(fr.date).to.equal("2026-06-15");
                expect(fr.enteredBy).to.be.a("number");

                // Clean up
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/fundraisers/${fr.id}`,
                    null,
                    200,
                );
            });
        });

        it("Defaults date to today when omitted", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR no-date ${Date.now()}` },
                201,
            ).then((response) => {
                const fr = response.body.fundraiser;
                expect(fr.date).to.match(/^\d{4}-\d{2}-\d{2}$/);
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/fundraisers/${fr.id}`,
                    null,
                    200,
                );
            });
        });

        it("Returns 400 when title is empty", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: "", description: "x" },
                400,
            );
        });

        it("Returns 400 for invalid date", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: "Bad date", date: "2026-13-40" },
                400,
            );
        });
    });

    describe("GET /api/fundraisers/{id} - Get fundraiser", () => {
        it("Returns 404 for non-existent fundraiser", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/fundraisers/999999",
                null,
                404,
            );
        });

        it("Round-trips a created fundraiser via GET", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR GET ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.fundraiser.id;
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/fundraisers/${id}`,
                    null,
                    200,
                ).then((getResp) => {
                    expect(getResp.body.fundraiser.id).to.equal(id);
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/fundraisers/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("PUT /api/fundraisers/{id} - Update fundraiser", () => {
        it("Returns 404 when updating a non-existent fundraiser", () => {
            cy.makePrivateAdminAPICall(
                "PUT",
                "/api/fundraisers/999999",
                { title: "nope" },
                404,
            );
        });

        it("Updates title, description, and date", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR PUT ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.fundraiser.id;
                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/api/fundraisers/${id}`,
                    {
                        title: "Updated Title",
                        description: "Updated Description",
                        date: "2026-12-31",
                    },
                    200,
                ).then((updateResp) => {
                    expect(updateResp.body.fundraiser.title).to.equal(
                        "Updated Title",
                    );
                    expect(updateResp.body.fundraiser.description).to.equal(
                        "Updated Description",
                    );
                    expect(updateResp.body.fundraiser.date).to.equal(
                        "2026-12-31",
                    );
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/fundraisers/${id}`,
                    null,
                    200,
                );
            });
        });

        it("Returns 400 when clearing title", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR empty-title ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.fundraiser.id;
                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/api/fundraisers/${id}`,
                    { title: "" },
                    400,
                );
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/fundraisers/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("DELETE /api/fundraisers/{id} - Delete fundraiser", () => {
        // TODO: add 409 coverage for "fundraiser has donated items" once a
        // DonatedItem REST API exists to seed the dependency. Seed data has
        // fundraiser id=1 but zero donated items, so a 409 test against seed
        // data is not reliable. The PHP route already blocks via
        // DonatedItemQuery::filterByFrId()->count().

        it("Returns 404 for non-existent fundraiser", () => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                "/api/fundraisers/999999",
                null,
                404,
            );
        });

        it("Deletes an existing fundraiser", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR DELETE ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.fundraiser.id;
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/fundraisers/${id}`,
                    null,
                    200,
                ).then((delResp) => {
                    expect(delResp.body).to.have.property("success", true);
                });
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/fundraisers/${id}`,
                    null,
                    404,
                );
            });
        });
    });

    describe("Access control", () => {
        it("Returns 401 when no API key is provided", () => {
            cy.request({
                method: "GET",
                url: "/api/fundraisers",
                failOnStatusCode: false,
                headers: { "content-type": "application/json" },
            }).then((response) => {
                expect(response.status).to.equal(401);
            });
        });

        it("Denies a caller without Finance permission", () => {
            // The no-finance API key may resolve to a user that lacks the
            // Finance role (403) or to a key that isn't seeded at all (401).
            // Matches the existing finance-deposits.spec.js convention.
            cy.makePrivateNoFinanceAPICall(
                "GET",
                "/api/fundraisers",
                null,
                [401, 403],
            );
        });
    });
});
