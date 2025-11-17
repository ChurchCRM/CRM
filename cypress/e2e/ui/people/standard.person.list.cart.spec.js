/// <reference types="cypress" />

describe("People List & Carts", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Add All to Cart then Remove", () => {
        // Empty cart first to ensure clean state
        cy.visit("v2/cart");
        cy.get("body").then(($body) => {
            if ($body.text().includes("You have items in your cart")) {
                cy.get("#emptyCart").click();
                cy.get(".bootbox.modal .btn-danger").click();
                cy.contains("You have no items in your cart");
            }
        });
        
        // Add people to cart first
        cy.visit("v2/people");

        // Wait for table to load with member data
        cy.get("#members tbody").should("contain", "Female");
        
        // Click Add All to Cart
        cy.get("#AddAllToCart").click();
        
        // Verify cart has items by checking for cart content
        cy.visit("v2/cart");
        cy.contains("Cart Functions");
        cy.get("body").should("not.contain", "You have no items in your cart");
        
        // Go back and remove all
        cy.visit("v2/people");
        cy.get("#RemoveAllFromCart").click();
        
        // Handle confirmation dialog
        cy.get(".bootbox.modal").should("be.visible");
        cy.get(".bootbox.modal .btn-danger").click();
        
        // Verify cart is empty by visiting and checking for empty message
        cy.visit("v2/cart");
        cy.contains("You have no items in your cart");
    });


});
