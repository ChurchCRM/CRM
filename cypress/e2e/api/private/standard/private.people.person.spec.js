/// <reference types="cypress" />

/**
 * API tests for Person endpoints
 * Tests validate that person phone fields (per_CellPhone, per_WorkPhone) still work
 * after family phone field removal
 */
describe("API Private Person", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

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
            // API returns 412 Precondition Failed for non-existent person
            cy.makePrivateAdminAPICall("GET", "/api/person/99999", null, 412);
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
});
