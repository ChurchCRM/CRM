/// <reference types="cypress" />

describe("People List & Carts", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Add individual person to cart", () => {
        // Empty cart first to ensure clean state
        cy.visit("v2/cart");
        cy.get("body").then(($body) => {
            if ($body.text().includes("You have items in your cart")) {
                cy.get(".emptyCart", { timeout: 5000 }).click();
                cy.get(".bootbox.modal .btn-danger", { timeout: 5000 }).click();
                cy.contains("You have no items in your cart", { timeout: 10000 });
            }
        });
        
        // Go to people list
        cy.visit("v2/people");

        // Wait for page to load
        cy.wait(2000);
        // Verify table has rows
        cy.get("#members tbody tr").should("have.length.greaterThan", 0);
        
        // Click Add to Cart button on first row
        cy.get("#members tbody tr:first .AddToCart").click();
        
        // Wait for operations to complete
        cy.wait(1000);
        
        // Verify button changed to Remove from Cart
        cy.get("#members tbody tr:first .RemoveFromCart").should("be.visible");
        
        // Verify cart has items
        cy.visit("v2/cart");
        cy.contains("Cart Functions", { timeout: 10000 });
        cy.get("body").should("not.contain", "You have no items in your cart");
    });


});
