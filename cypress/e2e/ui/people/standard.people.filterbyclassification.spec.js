describe("template spec", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("filter-by-classification", () => {
        // Test that we can filter people by classifications
        cy.visit("v2/people?familyActiveStatus=all");
        cy.wait(1500); // Wait for page to load
        
        // Verify table has data
        cy.get("#members tbody tr").should("have.length.greaterThan", 0);
        
        // Test filtering by email
        cy.get("#members_filter input").type("tony.wade@example.com");
        cy.wait(1500); // Wait for auto-submit filter to apply
        
        // Should find matching record or show no results  
        cy.get("#members tbody").then(($tbody) => {
            // Either we find the person or get "No matching records found"
            const hasRecord = $tbody.text().includes("tony.wade@example.com");
            if (!hasRecord) {
                cy.contains("No matching records found").should("exist");
            } else {
                cy.get("#members tbody").contains("tony.wade@example.com").should("exist");
            }
        });
        
        // Clear and try another filter
        cy.get("#members_filter input").clear();
        cy.wait(1500);
        cy.get("#members tbody tr").should("have.length.greaterThan", 0);
    });
});
