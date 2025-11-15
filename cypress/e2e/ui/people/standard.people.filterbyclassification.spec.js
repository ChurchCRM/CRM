describe("template spec", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("filter-by-classification", () => {
        // Setup: Ensure all inactive filters are off
        cy.visit("OptionManager.php?mode=classes");
        cy.get("#inactive4").uncheck();
        cy.get("#inactive5").uncheck();
        
        // Test initial state - person should appear in active/all but not inactive
        cy.visit("v2/people?familyActiveStatus=inactive");
        cy.get("#members_filter input").type("edwin.adams@example.com");
        cy.contains("No matching records found");
        
        cy.visit("v2/people?familyActiveStatus=all");
        cy.get("#members_filter input").clear().type("edwin.adams@example.com");
        cy.contains("(564)-714-4633");

        // Enable inactive4 and test
        cy.visit("OptionManager.php?mode=classes");
        cy.get("#inactive4").check();
        
        cy.visit("v2/people?familyActiveStatus=inactive");
        cy.get("#members_filter input").type("edwin.adams@example.com");
        cy.contains("No matching records found");

        // Enable inactive5 and test - person should now appear in inactive
        cy.visit("OptionManager.php?mode=classes");
        cy.get("#inactive5").check();
        
        cy.visit("v2/people?familyActiveStatus=inactive");
        cy.get("#members_filter input").type("edwin.adams@example.com");
        cy.contains("(564)-714-4633");

        cy.visit("v2/people");
        cy.get("#members_filter input").clear().type("edwin.adams@example.com");
        cy.contains("No matching records found");

        // Cleanup: Reset to original state
        cy.visit("OptionManager.php?mode=classes");
        cy.get("#inactive4").uncheck();
        cy.get("#inactive5").uncheck();
    });
});
