/// <reference types="cypress" />

describe("People List & Carts", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Add All to Cart then Remove", () => {
        // Empty cart first to ensure clean state
        cy.visit("v2/cart");
        cy.get("body").then(($body) => {
            if ($body.text().includes("You have items in your cart")) {
                cy.get("#emptyCart", { timeout: 5000 }).click();
                cy.get(".bootbox.modal .btn-danger", { timeout: 5000 }).click();
                cy.contains("You have no items in your cart", { timeout: 10000 });
            }
        });
        
        // Add people to cart first
        cy.visit("v2/people");

        // Wait for operations to complete
        cy.wait(4000);
        // Verify table contains "Female" entries
        cy.get("#members tbody").should("contain", "Female");
        
        // Click Add All to Cart
        cy.get("#AddAllToCart").click();
        
        // Wait for operations to complete
        cy.wait(4000);
        
        // Verify cart has items
        cy.visit("v2/cart");
        cy.contains("Cart Functions", { timeout: 10000 });
        cy.get("body").should("not.contain", "You have no items in your cart");
        
        // Go back and remove all
        cy.visit("v2/people");
        cy.wait(4000);
        
        // Click Remove All from Cart
        cy.get("#RemoveAllFromCart").click();
        
        // Handle confirmation dialog (give animations enough time)
        cy.get(".bootbox.modal", { timeout: 10000 }).should("be.visible");
        cy.get(".bootbox.modal .btn-danger").click();
        
        // Wait for operations to complete
        cy.wait(2000);
        
        // Verify cart is empty
        cy.visit("v2/cart");
        cy.contains("You have no items in your cart", { timeout: 10000 });
    });


});
