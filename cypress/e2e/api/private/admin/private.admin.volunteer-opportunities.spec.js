/// <reference types="cypress" />

/**
 * API tests for VolunteerOpportunity CRUD endpoints
 *
 * Covers:
 *   GET    /api/volunteer-opportunities
 *   POST   /api/volunteer-opportunities
 *   GET    /api/volunteer-opportunities/{id}
 *   PUT    /api/volunteer-opportunities/{id}
 *   DELETE /api/volunteer-opportunities/{id}
 */
describe("API Private Volunteer Opportunities", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /api/volunteer-opportunities", () => {
        it("Returns 200 with volunteerOpportunities array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/volunteer-opportunities",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property(
                    "volunteerOpportunities",
                );
                expect(response.body.volunteerOpportunities).to.be.an("array");
            });
        });

        it("Filters by activeOnly", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/volunteer-opportunities?activeOnly=true",
                null,
                200,
            ).then((response) => {
                response.body.volunteerOpportunities.forEach((opp) => {
                    expect(opp.active).to.equal(true);
                });
            });
        });
    });

    describe("POST /api/volunteer-opportunities", () => {
        it("Creates a volunteer opportunity and returns 201", () => {
            const name = `Cypress Vol ${Date.now()}`;
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/volunteer-opportunities",
                { name, description: "Test opp", active: true },
                201,
            ).then((response) => {
                const opp = response.body.volunteerOpportunity;
                expect(opp.id).to.be.a("number");
                expect(opp.name).to.equal(name);
                expect(opp.active).to.equal(true);
                expect(opp.order).to.be.a("number");

                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/volunteer-opportunities/${opp.id}`,
                    null,
                    200,
                );
            });
        });

        it("Returns 400 when name is empty", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/volunteer-opportunities",
                { name: "" },
                400,
            );
        });

        it("Returns 400 when name is a duplicate", () => {
            const name = `Cypress Dup Vol ${Date.now()}`;
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/volunteer-opportunities",
                { name },
                201,
            ).then((createResp) => {
                const id = createResp.body.volunteerOpportunity.id;
                cy.makePrivateAdminAPICall(
                    "POST",
                    "/api/volunteer-opportunities",
                    { name },
                    400,
                );
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/volunteer-opportunities/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("GET /api/volunteer-opportunities/{id}", () => {
        it("Returns 404 for non-existent opportunity", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/volunteer-opportunities/999999",
                null,
                404,
            );
        });

        it("Returns 200 with object for existing opportunity", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/volunteer-opportunities",
                { name: `Cypress Vol GET ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.volunteerOpportunity.id;
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/volunteer-opportunities/${id}`,
                    null,
                    200,
                ).then((getResp) => {
                    expect(getResp.body.volunteerOpportunity.id).to.equal(id);
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/volunteer-opportunities/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("PUT /api/volunteer-opportunities/{id}", () => {
        it("Returns 404 for non-existent opportunity", () => {
            cy.makePrivateAdminAPICall(
                "PUT",
                "/api/volunteer-opportunities/999999",
                { name: "nope" },
                404,
            );
        });

        it("Updates name, description, and active", () => {
            const orig = `Cypress Vol Orig ${Date.now()}`;
            const updated = `Cypress Vol Upd ${Date.now()}`;
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/volunteer-opportunities",
                { name: orig, active: true },
                201,
            ).then((createResp) => {
                const id = createResp.body.volunteerOpportunity.id;
                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/api/volunteer-opportunities/${id}`,
                    { name: updated, description: "desc2", active: false },
                    200,
                ).then((updateResp) => {
                    expect(updateResp.body.volunteerOpportunity.name).to.equal(
                        updated,
                    );
                    expect(
                        updateResp.body.volunteerOpportunity.description,
                    ).to.equal("desc2");
                    expect(
                        updateResp.body.volunteerOpportunity.active,
                    ).to.equal(false);
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/volunteer-opportunities/${id}`,
                    null,
                    200,
                );
            });
        });

        it("Returns 400 when name is blank", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/volunteer-opportunities",
                { name: `Cypress Vol Blank ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.volunteerOpportunity.id;
                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/api/volunteer-opportunities/${id}`,
                    { name: "" },
                    400,
                );
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/volunteer-opportunities/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("DELETE /api/volunteer-opportunities/{id}", () => {
        // TODO: add 409 coverage for "opportunity has person assignments" once
        // a PersonVolunteerOpportunity REST API exists to seed the dependency.
        // Seed data has zero rows in both volunteeropportunity_vol and
        // person2volunteeropp_p2vo, so a 409 test against seed data is not
        // reliable. The PHP route already blocks via
        // PersonVolunteerOpportunityQuery::filterByVolunteerOpportunityId()->count().

        it("Returns 404 for non-existent opportunity", () => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                "/api/volunteer-opportunities/999999",
                null,
                404,
            );
        });

        it("Deletes an existing opportunity", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/volunteer-opportunities",
                { name: `Cypress Vol Del ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.volunteerOpportunity.id;
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/volunteer-opportunities/${id}`,
                    null,
                    200,
                ).then((delResp) => {
                    expect(delResp.body).to.have.property("success", true);
                });
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/volunteer-opportunities/${id}`,
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
                url: "/api/volunteer-opportunities",
                failOnStatusCode: false,
                headers: { "content-type": "application/json" },
            }).then((response) => {
                expect(response.status).to.equal(401);
            });
        });

        it("Returns 403 for non-admin user", () => {
            cy.makePrivateUserAPICall(
                "GET",
                "/api/volunteer-opportunities",
                null,
                403,
            );
        });
    });
});
