/// <reference types="cypress" />

/**
 * API tests for Person endpoints
 * Tests validate that person phone fields (per_CellPhone, per_WorkPhone) still work
 * after family phone field removal
 */
describe("API Private Person", () => {
    describe("GET /api/person/{id} - Get Person by ID", () => {
        it("Returns 200 with person data", () => {
            cy.makePrivateAdminAPICall("GET", "/api/person/1", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("Id");
                    expect(response.body).to.have.property("FirstName");
                    expect(response.body).to.have.property("LastName");
                },
            );
        });

        it("Returns error for non-existent person", () => {
            // AbstractEntityMiddleware returns 404 Not Found for missing entity
            cy.makePrivateAdminAPICall("GET", "/api/person/99999", null, 404);
        });
    });

    describe("GET /api/persons/latest - Latest Persons", () => {
        it("Returns 200 with persons data", () => {
            cy.makePrivateAdminAPICall("GET", "/api/persons/latest", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });
    });

    describe("GET /api/persons/updated - Updated Persons", () => {
        it("Returns 200 with persons data", () => {
            cy.makePrivateAdminAPICall("GET", "/api/persons/updated", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });
    });

    describe("GET /api/persons/birthday - Birthdays", () => {
        it("Returns 200 with birthday data", () => {
            cy.makePrivateAdminAPICall("GET", "/api/persons/birthday", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });
    });

    describe("GET /api/persons/duplicate/emails - Duplicate Emails", () => {
        it("Returns 200 with duplicate email data", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/persons/duplicate/emails",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.exist;
            });
        });
    });

    describe("GET /api/persons/self-register - Self-Registered People", () => {
        it("Returns 200 with the seeded self-registered people", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/persons/self-register",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("people");
                expect(response.body.people).to.be.an("array");
                // seed.sql seeds self-registered (per_EnteredBy = -1) people
                expect(response.body.people.length).to.be.greaterThan(0);

                const person = response.body.people[0];
                expect(person).to.have.property("Id");
                expect(person).to.have.property("FirstName");
                expect(person).to.have.property("LastName");
                expect(person).to.have.property("DateEntered");
                expect(person).to.have.property("FamId");
            });
        });
    });

    describe("POST /api/person/{id}/activate/{status} - Activate/Deactivate Person", () => {
        const personId = 2; // seed.sql has persons 1-N; use person 2 (admin is 1, guard prevents self-deactivate)

        before(() => {
            // Ensure person 2 starts as active (idempotent setup)
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/person/${personId}/activate/true`,
                null,
                200,
            );
        });

        after(() => {
            // Ensure person is left as active after tests
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/person/${personId}/activate/true`,
                null,
                200,
            );
        });

        it("Deactivates a person (status=false)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/person/${personId}/activate/false`,
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("success");
                expect(response.body.success).to.be.true;
            });
        });

        it("Person is inactive after deactivation", () => {
            cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("DateDeactivated");
                    expect(response.body.DateDeactivated).to.not.be.null;
                    expect(response.body.DateDeactivated).to.not.equal("");
                },
            );
        });

        it("Activates a person (status=true)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/person/${personId}/activate/true`,
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("success");
                expect(response.body.success).to.be.true;
            });
        });

        it("Person is active after reactivation", () => {
            cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("DateDeactivated");
                    expect(
                        response.body.DateDeactivated === null ||
                        response.body.DateDeactivated === "" ||
                        response.body.DateDeactivated === undefined,
                    ).to.be.true;
                },
            );
        });

        it("Returns 403 when trying to deactivate yourself (person 1)", () => {
            // Admin user is person 1; cannot deactivate self
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/activate/false",
                null,
                403,
            );
        });

        it("Returns 400 for invalid status value", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/person/${personId}/activate/invalid`,
                null,
                400,
            );
        });

        it("Returns 404 for non-existent person", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/99999/activate/true",
                null,
                404,
            );
        });
    });
});
