/// <reference types="cypress" />

describe("API Private Family Cart", () => {
    beforeEach(() => {
        // Empty cart before each test via API
        cy.setupStandardSession();
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/cart/",
            null,
            200,
        );
        
        // Suppress uncaught exceptions from promise rejections
        cy.on('uncaught:exception', (err, runnable) => {
            return false;
        });
    });

    it("Get families in cart - empty cart should return empty array", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/families/familiesInCart",
            null,
            200,
        ).then((response) => {
            expect(response.body).to.have.property("familiesInCart");
            expect(response.body.familiesInCart).to.be.an("array");
            expect(response.body.familiesInCart).to.have.lengthOf(0);
        });
    });

    it("Get families in cart - returns family ID when all members are added", () => {
        // Family 6 has members: person 28 and person 30
        // Add all members of family 6 to cart via API
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/",
            JSON.stringify({ Persons: [28, 30] }),
            200,
        );

        // Check that family 6 is in the familiesInCart array
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/families/familiesInCart",
            null,
            200,
        ).then((response) => {
            expect(response.body).to.have.property("familiesInCart");
            expect(response.body.familiesInCart).to.be.an("array");
            expect(response.body.familiesInCart).to.include(6);
        });
    });

    it("Get families in cart - does not return family if only some members are added", () => {
        // Add only one person to cart (person 28, part of family 6)
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/",
            JSON.stringify({ Persons: [28] }),
            200,
        );

        // Check that family 6 is NOT returned (since only one member is in cart)
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/families/familiesInCart",
            null,
            200,
        ).then((response) => {
            expect(response.body).to.have.property("familiesInCart");
            expect(response.body.familiesInCart).to.be.an("array");
            expect(response.body.familiesInCart).to.not.include(6);
        });
    });

    it("Get families in cart - removes family from response when member is removed", () => {
        // Add all members of family 6 to cart via API
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/",
            JSON.stringify({ Persons: [28, 30] }),
            200,
        );

        // Verify family is in cart
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/families/familiesInCart",
            null,
            200,
        ).then((response) => {
            expect(response.body.familiesInCart).to.include(6);
        });

        // Remove one family member from cart via API
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/cart/",
            JSON.stringify({ Persons: [28] }),
            200,
        );

        // Verify family is no longer in cart (since not all members are in cart anymore)
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/families/familiesInCart",
            null,
            200,
        ).then((response) => {
            expect(response.body.familiesInCart).to.not.include(6);
        });
    });
});
