/// <reference types="cypress" />

/**
 * API tests for PropertyType CRUD endpoints
 *
 * Covers:
 *   GET    /api/property-types
 *   POST   /api/property-types
 *   GET    /api/property-types/{id}
 *   PUT    /api/property-types/{id}
 *   DELETE /api/property-types/{id}
 *
 * NOTE: These routes are gated by MenuOptionsRoleAuthMiddleware.
 * The Cypress seed's "admin" user (ID 1) has bMenuOptions=0, so happy-path
 * flows MUST use makePrivateUserAPICall (tony.wade, ID 3, bMenuOptions=1).
 * The admin key is only used in the 403 negative-path test below.
 */
describe("API Private Property Types", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /api/property-types - List property types", () => {
        it("Returns 200 with propertyTypes array", () => {
            cy.makePrivateUserAPICall(
                "GET",
                "/api/property-types",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("propertyTypes");
                expect(response.body.propertyTypes).to.be.an("array");
            });
        });

        it("Filters by class", () => {
            cy.makePrivateUserAPICall(
                "GET",
                "/api/property-types?class=p",
                null,
                200,
            ).then((response) => {
                response.body.propertyTypes.forEach((pt) => {
                    expect(pt.class).to.equal("p");
                });
            });
        });

        it("Rejects invalid class filter with 400", () => {
            cy.makePrivateUserAPICall(
                "GET",
                "/api/property-types?class=zz",
                null,
                400,
            );
        });
    });

    describe("POST /api/property-types - Create property type", () => {
        it("Creates a property type and returns 201", () => {
            const name = `Cypress PT ${Date.now()}`;
            cy.makePrivateUserAPICall(
                "POST",
                "/api/property-types",
                { class: "p", name, description: "Test description" },
                201,
            ).then((response) => {
                const pt = response.body.propertyType;
                expect(pt.id).to.be.a("number");
                expect(pt.class).to.equal("p");
                expect(pt.name).to.equal(name);
                expect(pt.description).to.equal("Test description");

                cy.makePrivateUserAPICall(
                    "DELETE",
                    `/api/property-types/${pt.id}`,
                    null,
                    200,
                );
            });
        });

        it("Returns 400 when class is invalid", () => {
            cy.makePrivateUserAPICall(
                "POST",
                "/api/property-types",
                { class: "x", name: "bad class" },
                400,
            );
        });

        it("Returns 400 when name is empty", () => {
            cy.makePrivateUserAPICall(
                "POST",
                "/api/property-types",
                { class: "f", name: "" },
                400,
            );
        });
    });

    describe("GET /api/property-types/{id}", () => {
        it("Returns 404 for non-existent property type", () => {
            cy.makePrivateUserAPICall(
                "GET",
                "/api/property-types/999999",
                null,
                404,
            );
        });

        it("Returns 200 with object for existing property type", () => {
            cy.makePrivateUserAPICall(
                "POST",
                "/api/property-types",
                { class: "g", name: `Cypress PT GET ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.propertyType.id;
                cy.makePrivateUserAPICall(
                    "GET",
                    `/api/property-types/${id}`,
                    null,
                    200,
                ).then((getResp) => {
                    expect(getResp.body.propertyType.id).to.equal(id);
                    expect(getResp.body.propertyType.class).to.equal("g");
                });
                cy.makePrivateUserAPICall(
                    "DELETE",
                    `/api/property-types/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("PUT /api/property-types/{id}", () => {
        it("Returns 404 for non-existent property type", () => {
            cy.makePrivateUserAPICall(
                "PUT",
                "/api/property-types/999999",
                { name: "nope" },
                404,
            );
        });

        it("Updates class, name, and description", () => {
            cy.makePrivateUserAPICall(
                "POST",
                "/api/property-types",
                { class: "p", name: `Cypress PT PUT ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.propertyType.id;
                cy.makePrivateUserAPICall(
                    "PUT",
                    `/api/property-types/${id}`,
                    {
                        class: "f",
                        name: "Updated PT Name",
                        description: "Updated desc",
                    },
                    200,
                ).then((updateResp) => {
                    expect(updateResp.body.propertyType.class).to.equal("f");
                    expect(updateResp.body.propertyType.name).to.equal(
                        "Updated PT Name",
                    );
                    expect(updateResp.body.propertyType.description).to.equal(
                        "Updated desc",
                    );
                });
                cy.makePrivateUserAPICall(
                    "DELETE",
                    `/api/property-types/${id}`,
                    null,
                    200,
                );
            });
        });

        it("Returns 400 when updating to invalid class", () => {
            cy.makePrivateUserAPICall(
                "POST",
                "/api/property-types",
                { class: "p", name: `Cypress PT BadClass ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.propertyType.id;
                cy.makePrivateUserAPICall(
                    "PUT",
                    `/api/property-types/${id}`,
                    { class: "zz" },
                    400,
                );
                cy.makePrivateUserAPICall(
                    "DELETE",
                    `/api/property-types/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("DELETE /api/property-types/{id}", () => {
        it("Returns 404 for non-existent property type", () => {
            cy.makePrivateUserAPICall(
                "DELETE",
                "/api/property-types/999999",
                null,
                404,
            );
        });

        it("Deletes an existing property type", () => {
            cy.makePrivateUserAPICall(
                "POST",
                "/api/property-types",
                { class: "p", name: `Cypress PT DEL ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.propertyType.id;
                cy.makePrivateUserAPICall(
                    "DELETE",
                    `/api/property-types/${id}`,
                    null,
                    200,
                ).then((delResp) => {
                    expect(delResp.body).to.have.property("success", true);
                });
                cy.makePrivateUserAPICall(
                    "GET",
                    `/api/property-types/${id}`,
                    null,
                    404,
                );
            });
        });

        it("Returns 409 when deleting a property type that is still in use", () => {
            // Seed data: property types 1/2/3 each have a child property_pro row.
            // The DELETE route must block with 409 Conflict rather than cascade-delete.
            cy.makePrivateUserAPICall(
                "DELETE",
                "/api/property-types/1",
                null,
                409,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", false);
                expect(resp.body).to.have.property("message");
                expect(resp.body.message).to.match(/in use|still reference/i);
            });
        });
    });

    describe("Access control", () => {
        it("Returns 401 when no API key is provided", () => {
            cy.request({
                method: "GET",
                url: "/api/property-types",
                failOnStatusCode: false,
                headers: { "content-type": "application/json" },
            }).then((response) => {
                expect(response.status).to.equal(401);
            });
        });

        // NOTE: a 403 test is intentionally omitted here.
        // `MenuOptionsRoleAuthMiddleware` calls `User::isMenuOptionsEnabled()`,
        // which has an admin bypass (`$this->isAdmin() || $this->isMenuOptions()`).
        // All three seeded API-key users (admin, tony.wade, limited.user) either
        // have `bAdmin=1` (→ bypass) or `bMenuOptions=1`, so none of them can
        // trigger the 403 path. Adding proper 403 coverage would require
        // introducing a new seed user and API key with both flags = 0.
    });
});
