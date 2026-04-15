/// <reference types="cypress" />

/**
 * API tests for DonationFund CRUD endpoints
 *
 * Covers:
 *   GET    /api/donation-funds
 *   POST   /api/donation-funds
 *   GET    /api/donation-funds/{id}
 *   PUT    /api/donation-funds/{id}
 *   DELETE /api/donation-funds/{id}
 */
describe("API Private Donation Funds", () => {
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

    describe("POST /api/donation-funds - Create fund", () => {
        it("Creates a fund and returns 201", () => {
            const name = `Cypress Fund ${Date.now()}`;
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/donation-funds",
                { name, description: "Test desc", active: true },
                201,
            ).then((response) => {
                const fund = response.body.fund;
                expect(fund.id).to.be.a("number");
                expect(fund.name).to.equal(name);
                expect(fund.description).to.equal("Test desc");
                expect(fund.active).to.equal(true);
                expect(fund.order).to.be.a("number");

                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/donation-funds/${fund.id}`,
                    null,
                    200,
                );
            });
        });

        it("Returns 400 when name is empty", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/donation-funds",
                { name: "", description: "x" },
                400,
            );
        });

        it("Returns 400 when name is a duplicate", () => {
            const name = `Cypress Dup Fund ${Date.now()}`;
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/donation-funds",
                { name },
                201,
            ).then((resp) => {
                const id = resp.body.fund.id;
                cy.makePrivateAdminAPICall(
                    "POST",
                    "/api/donation-funds",
                    { name },
                    400,
                );
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/donation-funds/${id}`,
                    null,
                    200,
                );
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

        it("Returns 200 with fund object for existing fund", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/donation-funds",
                { name: `Cypress GetFund ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.fund.id;
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/donation-funds/${id}`,
                    null,
                    200,
                ).then((getResp) => {
                    expect(getResp.body.fund.id).to.equal(id);
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/donation-funds/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("PUT /api/donation-funds/{id}", () => {
        it("Returns 404 for non-existent fund", () => {
            cy.makePrivateAdminAPICall(
                "PUT",
                "/api/donation-funds/999999",
                { name: "nope" },
                404,
            );
        });

        it("Updates name, description, and active", () => {
            const orig = `Cypress Orig ${Date.now()}`;
            const updated = `Cypress Upd ${Date.now()}`;
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/donation-funds",
                { name: orig, active: true },
                201,
            ).then((createResp) => {
                const id = createResp.body.fund.id;
                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/api/donation-funds/${id}`,
                    { name: updated, description: "desc2", active: false },
                    200,
                ).then((updateResp) => {
                    expect(updateResp.body.fund.name).to.equal(updated);
                    expect(updateResp.body.fund.description).to.equal("desc2");
                    expect(updateResp.body.fund.active).to.equal(false);
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/donation-funds/${id}`,
                    null,
                    200,
                );
            });
        });

        it("Allows keeping the same name without triggering duplicate check", () => {
            const name = `Cypress SameName ${Date.now()}`;
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/donation-funds",
                { name },
                201,
            ).then((createResp) => {
                const id = createResp.body.fund.id;
                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/api/donation-funds/${id}`,
                    { name, description: "updated" },
                    200,
                );
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/donation-funds/${id}`,
                    null,
                    200,
                );
            });
        });

        it("Returns 400 when name is blank", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/donation-funds",
                { name: `Cypress PutEmpty ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.fund.id;
                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/api/donation-funds/${id}`,
                    { name: "" },
                    400,
                );
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/donation-funds/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("DELETE /api/donation-funds/{id}", () => {
        it("Returns 404 for non-existent fund", () => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                "/api/donation-funds/999999",
                null,
                404,
            );
        });

        it("Returns 409 when deleting a fund that is still referenced by pledges", () => {
            // Seed data: fund id=1 ("Pledges") is referenced by rows in pledge_plg.
            // The DELETE route must block with 409 Conflict — it delegates to
            // DonationFundService::deleteFund, which throws RuntimeException.
            cy.makePrivateAdminAPICall(
                "DELETE",
                "/api/donation-funds/1",
                null,
                409,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", false);
                expect(resp.body).to.have.property("message");
                expect(resp.body.message).to.match(/pledge/i);
            });
        });

        it("Deletes an existing fund", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/donation-funds",
                { name: `Cypress Del ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.fund.id;
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/donation-funds/${id}`,
                    null,
                    200,
                ).then((delResp) => {
                    expect(delResp.body).to.have.property("success", true);
                });
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/donation-funds/${id}`,
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
                url: "/api/donation-funds",
                failOnStatusCode: false,
                headers: { "content-type": "application/json" },
            }).then((response) => {
                expect(response.status).to.equal(401);
            });
        });

        it("Returns 403 for user without Finance permission", () => {
            cy.makePrivateNoFinanceAPICall(
                "GET",
                "/api/donation-funds",
                null,
                403,
            );
        });
    });
});
