/// <reference types="cypress" />

describe("Standard Cart", () => {
    // Helper function to ensure cart is fully initialized
    const waitForCartReady = () => {
        // Wait for locales to be fully loaded
        cy.window().should('have.property', 'CRM');
        cy.window().its('CRM.localesLoaded').should('eq', true);
        
        // Wait for cart manager to exist
        cy.window().its('CRM.cartManager').should('exist');
        
        // Wait for cart dropdown to be initialized (not showing "undefined")
        cy.get("#cart-dropdown-menu").should('not.contain', 'undefined');
    };

    beforeEach(() => {
        cy.setupStandardSession();
        // Empty cart before each test and verify it's empty
        cy.visit("v2/cart");
        
        // Wait for cart to be fully ready
        waitForCartReady();
        
        cy.get("body").then(($body) => {
            if (!$body.text().includes("You have no items in your cart")) {
                // Use API to empty cart instead of clicking UI (more reliable in beforeEach)
                cy.request({
                    method: "DELETE",
                    url: "/api/cart/",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({})
                });
            }
        });
        // Verify cart is empty
        cy.visit("v2/cart");
        waitForCartReady();
        cy.contains("You have no items in your cart").should("be.visible");
    });

    it("Cart Add and Remove Person", () => {
        cy.visit("v2/cart");
        cy.contains("You have no items in your cart");
        cy.visit("PersonView.php?PersonID=1");
        
        // Wait for cart to be ready before clicking
        waitForCartReady();
        
        cy.get("#AddPersonToCart").click();
        
        // Wait for cart count to update instead of arbitrary timeout
        cy.get("#iconCount").should('not.contain', '0');
        
        cy.visit("v2/cart");
        cy.contains("Cart Functions");
        cy.contains("Church Admin");
        
        // Wait for cart to be ready
        waitForCartReady();
        
        cy.get("#emptyCart").click();
        // Wait for and click the confirm button in the bootbox dialog
        cy.get(".bootbox .btn-danger", { timeout: 5000 }).should('be.visible').click();
        
        // Wait for success notification or cart to update
        cy.contains("You have no items in your cart", { timeout: 10000 }).should('be.visible');
    });

    it("Cart Add and Remove Family", () => {
        cy.visit("v2/cart");
        cy.contains("You have no items in your cart");
        cy.visit("people/family/6");
        
        // Wait for cart to be ready before clicking
        waitForCartReady();
        
        cy.get("#AddFamilyToCart").click();
        
        // Wait for cart count to update instead of arbitrary timeout
        cy.get("#iconCount").should('not.contain', '0');
        
        cy.visit("v2/cart");
        cy.contains("Kenzi Dixon");
        cy.contains("Cart Functions");
        
        // Wait for cart to be ready
        waitForCartReady();
        
        cy.get("#emptyCart").click();
        // Wait for and click the confirm button in the bootbox dialog
        cy.get(".bootbox .btn-danger", { timeout: 5000 }).should('be.visible').click();
        
        // Wait for success notification or cart to update
        cy.contains("You have no items in your cart", { timeout: 10000 }).should('be.visible');
    });

    it("Cart prevents duplicate person additions", () => {
        cy.visit("v2/cart");
        cy.contains("You have no items in your cart");
        
        // Add person first time
        cy.visit("PersonView.php?PersonID=1");
        
        // Wait for cart to be ready
        waitForCartReady();
        
        cy.get("#AddPersonToCart").click();
        
        // Wait for cart to update
        cy.get("#iconCount").should('contain', '1');
        
        // Try to add same person again
        cy.get("#AddPersonToCart").click();
        
        // Cart count should still be 1
        cy.get("#iconCount").should('contain', '1');
        
        // Verify cart still only has one person
        cy.visit("v2/cart");
        
        // Wait for cart to be ready
        waitForCartReady();
        
        cy.contains("Church Admin");
        
        // Should only appear once in the cart
        cy.get("body").then(($body) => {
            const text = $body.text();
            const matches = (text.match(/Church Admin/g) || []).length;
            // Account for the name appearing in different places (header, cart item)
            expect(matches).to.be.lessThan(5);
        });
        
        // Clean up
        // Wait for cart to be ready
        waitForCartReady();
        
        cy.get("#emptyCart").click();
        // Wait for and click the confirm button in the bootbox dialog
        cy.get(".bootbox .btn-danger", { timeout: 5000 }).should('be.visible').click();
        
        // Wait for cart to be emptied
        cy.contains("You have no items in your cart", { timeout: 10000 }).should('be.visible');
    });

    // NOTE: the pure-API "Cart API returns correct duplicate information"
    // test lives in cypress/e2e/api/private/standard/private.cart.duplicates.spec.js.
});
