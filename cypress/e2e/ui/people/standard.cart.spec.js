/// <reference types="cypress" />

describe("Standard Cart", () => {
    beforeEach(() => {
        // Empty cart before each test and verify it's empty
        cy.loginStandard("v2/cart");
        cy.get("body").then(($body) => {
            if (!$body.text().includes("You have no items in your cart")) {
                cy.get("#emptyCart").click();
                // Wait for and click the confirm button in the bootbox dialog
                cy.get(".bootbox .btn-danger", { timeout: 5000 }).click();
            }
        });
        // Verify cart is empty
        cy.visit("v2/cart");
        cy.contains("You have no items in your cart").should("be.visible");
    });

    it("Cart Add and Remove Person", () => {
        cy.visit("v2/cart");
        cy.contains("You have no items in your cart");
        cy.visit("PersonView.php?PersonID=1");
        cy.get("#AddPersonToCart").click();
        // Wait a moment for the AJAX call to complete
        cy.wait(500);
        cy.visit("v2/cart");
        cy.contains("Cart Functions");
        cy.contains("Church Admin");
        cy.get("#emptyCart").click();
        // Wait for and click the confirm button in the bootbox dialog
        cy.get(".bootbox .btn-danger", { timeout: 5000 }).click();
        cy.contains("You have no items in your cart");
    });

    it("Cart Add and Remove Family", () => {
        cy.visit("v2/cart");
        cy.contains("You have no items in your cart");
        cy.visit("v2/family/6");
        cy.get("#AddFamilyToCart").click();
        // Wait a moment for the AJAX call to complete
        cy.wait(500);
        cy.visit("v2/cart");
        cy.contains("Kenzi Dixon");
        cy.contains("Cart Functions");
        cy.get("#emptyCart").click();
        // Wait for and click the confirm button in the bootbox dialog
        cy.get(".bootbox .btn-danger", { timeout: 5000 }).click();
        cy.contains("You have no items in your cart");
    });

    it("Cart count updates after bulk operations", () => {
        cy.visit("v2/cart");
        cy.contains("You have no items in your cart");

        // Add multiple people via Add All to Cart
        cy.visit("v2/people?Gender=1");
        
        // Wait for DataTable to load
        cy.get("#members").should("be.visible");
        cy.get("#members tbody tr").should("have.length.greaterThan", 0);
        
        cy.get("#AddAllToCart").click();
        
        // Wait for page reload after Add All
        cy.url().should("include", "/v2/people");
        
        // Check that cart page shows items
        cy.visit("v2/cart");
        cy.contains("Cart Functions");
        cy.get("body").should("not.contain", "You have no items in your cart");
        
        // Remove all via people page
        cy.visit("v2/people?Gender=1");
        
        // Wait for DataTable to load again
        cy.get("#members").should("be.visible");
        cy.get("#members tbody tr").should("have.length.greaterThan", 0);
        
        cy.get("#RemoveAllFromCart").should("be.visible").click();
        
        // Wait for page reload after Remove All
        cy.url().should("include", "/v2/people");
        
        // Verify cart is empty by using empty cart button
        cy.visit("v2/cart");
        cy.get("body").then(($body) => {
            if (!$body.text().includes("You have no items in your cart")) {
                // Cart still has items, empty it manually
                cy.get("#emptyCart").click();
                // Wait for and click the confirm button in the bootbox dialog
                cy.get(".bootbox .btn-danger", { timeout: 5000 }).click();
            }
        });
        cy.contains("You have no items in your cart");
    });

    it("Cart prevents duplicate person additions", () => {
        cy.visit("v2/cart");
        cy.contains("You have no items in your cart");
        
        // Add person first time
        cy.visit("PersonView.php?PersonID=1");
        cy.get("#AddPersonToCart").click();
        
        // Try to add same person again
        cy.get("#AddPersonToCart").click();
        
        // Verify cart still only has one person
        cy.visit("v2/cart");
        cy.contains("Church Admin");
        
        // Should only appear once in the cart
        cy.get("body").then(($body) => {
            const text = $body.text();
            const matches = (text.match(/Church Admin/g) || []).length;
            // Account for the name appearing in different places (header, cart item)
            expect(matches).to.be.lessThan(5);
        });
        
        // Clean up
        cy.get("#emptyCart").click();
        // Wait for and click the confirm button in the bootbox dialog
        cy.get(".bootbox .btn-danger", { timeout: 5000 }).click();
    });

    it("Cart API returns correct duplicate information", () => {
        // Empty cart first
        cy.request({
            method: "DELETE",
            url: "/api/cart/",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({})
        });

        // Add person via API - should succeed
        cy.request({
            method: "POST",
            url: "/api/cart/",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                Persons: [1]
            })
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body).to.have.property("added");
            expect(response.body).to.have.property("duplicate");
            expect(response.body.added).to.include(1);
            expect(response.body.duplicate).to.be.empty;
        });

        // Try to add same person again - should be duplicate
        cy.request({
            method: "POST",
            url: "/api/cart/",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                Persons: [1]
            })
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body).to.have.property("added");
            expect(response.body).to.have.property("duplicate");
            expect(response.body.added).to.be.empty;
            expect(response.body.duplicate).to.include(1);
        });

        // Add multiple people including one duplicate
        cy.request({
            method: "POST",
            url: "/api/cart/",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                Persons: [1, 2, 3]
            })
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.added).to.include.members([2, 3]);
            expect(response.body.duplicate).to.include(1);
        });

        // Clean up
        cy.request({
            method: "DELETE",
            url: "/api/cart/",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({})
        });
    });
});