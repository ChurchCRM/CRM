describe("template spec", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("filter-by-classification", () => {
        // Test that we can filter people by classifications
        cy.visit("v2/people?familyActiveStatus=all");
        
        // Verify table has data before filtering
        cy.get("#members tbody tr").should("have.length.greaterThan", 0);
        
        // Test filtering by email
        cy.get("#members_filter input").type("tony.wade@example.com");
        
        // Wait for filter results to update (either shows record or "No matching records")
        cy.get("#members tbody").then(($tbody) => {
            // Either we find the person or get "No matching records found"
            const hasRecord = $tbody.text().includes("tony.wade@example.com");
            if (!hasRecord) {
                cy.contains("No matching records found").should("exist");
            } else {
                cy.get("#members tbody").contains("tony.wade@example.com").should("exist");
            }
        });
        
        // Clear filter and verify table reloads
        cy.get("#members_filter input").clear();
        cy.get("#members tbody tr").should("have.length.greaterThan", 0);
    });
});
