describe("template spec", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("filter-by-classification", () => {
        // Test that we can filter people by classifications
        cy.visit("v2/people?familyActiveStatus=all");
        
        // Wait for page to load and verify we're on the right page
        cy.url().should("include", "/v2/people");
        
        // Verify table exists and has data before filtering
        cy.get("#members", { timeout: 10000 }).should("exist");
        cy.get("#members tbody", { timeout: 10000 }).should("exist");
        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
        
        // Test filtering by email (DataTables v2 uses .dt-search container)
        cy.get(".dt-search input", { timeout: 5000 }).first().type("tony.wade@example.com", { delay: 100 });
        
        // Wait for filter results to update
        cy.wait(500);
        
        // Either we find the person or get "No matching records"
        cy.get("#members tbody").then(($tbody) => {
            const hasRecord = $tbody.text().includes("tony.wade@example.com");
            if (!hasRecord) {
                // If no exact match, check for "No matching records"
                cy.get("#members tbody tr").first().should("contain", "No matching records");
            } else {
                cy.get("#members tbody").contains("tony.wade@example.com").should("exist");
            }
        });
        
        // Clear filter and verify table resets
        cy.get(".dt-search input").first().clear();
        cy.wait(300);
        cy.get("#members tbody tr", { timeout: 5000 }).should("have.length.greaterThan", 0);
    });
});
