/// <reference types="cypress" />

/**
 * API tests for Search endpoint
 * Tests validate that search works correctly after family phone field removal
 */
describe("API Private Search", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /api/search/{query} - Global Search", () => {
        it("Returns 200 for text query", () => {
            cy.makePrivateAdminAPICall("GET", "/api/search/Smith", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });

        it("Returns 200 for phone number search", () => {
            // Search by phone number - should search person phone fields, not family
            cy.makePrivateAdminAPICall("GET", "/api/search/555", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });

        it("Returns 200 for email search", () => {
            cy.makePrivateAdminAPICall("GET", "/api/search/@example", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });

        it("Returns 200 for no matches", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/search/ZZZZNONEXISTENT12345",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.exist;
            });
        });

        it("Handles special characters in search query", () => {
            cy.makePrivateAdminAPICall("GET", "/api/search/test%40email", null, 200);
        });
    });
});
