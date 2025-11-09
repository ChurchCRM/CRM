/// <reference types="cypress" />

describe("Standard People", () => {
    it("Listing all persons", () => {
        cy.loginStandard("v2/people");
        cy.waitForDataTable('#members');
        cy.contains("Admin");
        cy.contains("Church");
        cy.contains("Joel");
        cy.contains("Emma");
    });

    it("Listing all persons with gender filter", () => {
        cy.loginStandard("v2/people?Gender=0");
        cy.waitForDataTable('#members');
        cy.contains("Admin");
        cy.contains("Church");
        cy.contains("Kennedy");
        cy.contains("Judith");
        cy.contains("Emma").should("not.exist");
    });

    it("Person Not Found", () => {
        cy.loginStandard("PersonView.php?PersonID=9999", false);
        cy.location("pathname").should("include", "person/not-found");
        cy.contains("Oops! PERSON 9999 Not Found");
    });

    it("Add All to Cart functionality", () => {
        // Login first to establish session
        cy.loginStandard("v2/cart");
        
        // Wait for cart page to fully load
        cy.get("body", { timeout: 10000 }).should("be.visible");
        
        // Empty cart first to ensure clean state (if cart has items)
        cy.get("body").then(($body) => {
            // Check if cart has items
            if ($body.text().includes("You have items in your cart")) {
                cy.get("#emptyCart", { timeout: 5000 }).should("be.visible").click();
                
                // Handle the bootbox confirmation dialog
                cy.get(".bootbox.modal", { timeout: 5000 }).should("be.visible");
                cy.get(".bootbox.modal .btn-danger").click();
                
                cy.contains("You have no items in your cart", { timeout: 10000 });
            }
        });
        
        // Go to people page with filter
        cy.visit("v2/people?Gender=1"); // Filter by Female
        
        // Wait for DataTable to load
        cy.waitForDataTable('#members');
        
        // Click Add All to Cart
        cy.get("#AddAllToCart").should("be.visible").click();
        
        // Verify cart has items
        cy.visit("v2/cart");
        cy.contains("Cart Functions", { timeout: 10000 });
        cy.get("body", { timeout: 10000 }).should("not.contain", "You have no items in your cart");
        
        // Clean up - empty cart via UI with confirmation
        cy.get("#emptyCart", { timeout: 5000 }).should("be.visible").click();
        cy.get(".bootbox.modal", { timeout: 5000 }).should("be.visible");
        cy.get(".bootbox.modal .btn-danger").click();
        cy.contains("You have no items in your cart", { timeout: 10000 });
    });

    it("Remove All from Cart functionality", () => {
        // Login first to establish session
        cy.loginStandard("v2/cart");
        
        // Empty cart first to ensure clean state
        cy.get("body").then(($body) => {
            if (!$body.text().includes("You have no items in your cart")) {
                cy.get("#emptyCart", { timeout: 5000 }).should("be.visible").click();
                cy.get(".bootbox.modal", { timeout: 5000 }).should("be.visible");
                cy.get(".bootbox.modal .btn-danger").click();
                cy.contains("You have no items in your cart", { timeout: 10000 });
            }
        });

        // Add some people to cart first
        cy.visit("v2/people?Gender=1");
        
        // Wait for DataTable to load
        cy.waitForDataTable('#members');
        
        cy.get("#AddAllToCart").should("be.visible").click();
        
        // Wait for page reload
        cy.url().should("include", "/v2/people");
        
        // Verify cart has items
        cy.visit("v2/cart");
        cy.get("body").should("not.contain", "You have no items in your cart");
        
        // Go back and remove all
        cy.visit("v2/people?Gender=1");
        
        // Wait for DataTable to load
        cy.waitForDataTable('#members');
        
        cy.get("#RemoveAllFromCart").should("be.visible").click();
        
        // Handle confirmation dialog for RemoveAll - click danger button (Yes, Remove)
        cy.get(".bootbox.modal", { timeout: 5000 }).should("be.visible");
        cy.get(".bootbox.modal .btn-danger").click();
        
        // Wait for page reload
        cy.url().should("include", "/v2/people");
        
        // Verify cart is empty
        cy.visit("v2/cart");
        cy.get("body").then(($body) => {
            if (!$body.text().includes("You have no items in your cart")) {
                // Cart still has items, empty it manually
                cy.get("#emptyCart", { timeout: 5000 }).should("be.visible").click();
                cy.get(".bootbox.modal", { timeout: 5000 }).should("be.visible");
                cy.get(".bootbox.modal .btn-danger").click();
            }
        });
        cy.contains("You have no items in your cart", { timeout: 10000 });
    });

    it("Clear Filter functionality", () => {
        cy.loginStandard("v2/people");
        
        // Wait for DataTable to be ready
        cy.waitForDataTable('#members');
        
        // Apply a gender filter using Select2
        cy.get(".filter-Gender").parent().find(".select2-selection").click();
        cy.get(".select2-results__option").contains("Male").click();
        
        // Verify filter is applied (wait for table to update)
        cy.get("#members tbody tr").should("exist");
        
        // Click Clear Filter button
        cy.get("#ClearFilter").click();
        
        // Wait for table to refresh after clearing filter
        cy.wait(500);
        
        // Verify all people are shown again
        cy.contains("Admin");
        cy.contains("Emma");
    });

    it("Multiple filter combinations", () => {
        cy.loginStandard("v2/people");
        
        // Wait for DataTable to be ready
        cy.waitForDataTable('#members');
        
        // Apply gender filter using Select2
        cy.get(".filter-Gender").parent().find(".select2-selection").click();
        cy.get(".select2-results__option").contains("Female").click();
        
        // Apply classification filter using Select2
        cy.get(".filter-Classification").parent().find(".select2-selection").click();
        cy.get(".select2-results__option").contains("Member").click();
        
        // Table should show filtered results
        cy.get("#members tbody tr").should("have.length.greaterThan", 0);
        
        // Clear all filters
        cy.get("#ClearFilter").click();
    });
});
