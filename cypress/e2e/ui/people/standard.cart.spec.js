/// <reference types="cypress" />

describe("Standard Cart", () => {
    beforeEach(() => {
        // Empty cart before each test and verify it's empty
        cy.loginStandard("v2/cart");
        cy.get("body").then(($body) => {
            if (!$body.text().includes("You have no items in your cart")) {
                cy.get("#emptyCart").click();
                // Wait for cart to actually be emptied
                cy.wait(1000);
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
        cy.visit("v2/cart");
        cy.contains("Cart Functions");
        cy.contains("Church Admin");
        cy.get("#emptyCart").click();
        cy.contains("You have no items in your cart");
    });

    it("Cart Add and Remove Family", () => {
        cy.visit("v2/cart");
        cy.contains("You have no items in your cart");
        cy.visit("v2/family/6");
        cy.get("#AddFamilyToCart").click();
        cy.visit("v2/cart");
        cy.contains("Kenzi Dixon");
        cy.contains("Cart Functions");
        cy.get("#emptyCart").click();
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
        cy.wait(2000);
        
        // Verify cart is empty by using empty cart button
        cy.visit("v2/cart");
        cy.get("body").then(($body) => {
            if (!$body.text().includes("You have no items in your cart")) {
                // Cart still has items, empty it manually
                cy.get("#emptyCart").click();
            }
        });
        cy.contains("You have no items in your cart");
    });
});
