/// <reference types="cypress" />

describe("Standard Groups", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Add Group ", () => {
        const uniqueSeed = Date.now().toString();
        const newGroupName = "New Test Group " + uniqueSeed;

        cy.visit("GroupList.php");
        cy.get("#groupName").type(newGroupName);
        cy.get("#addNewGroup").click();
        
        // Should redirect to GroupEditor page
        cy.url().should("contain", "GroupEditor.php");
        cy.url().should("contain", "GroupID=");
        
        // Verify we're on the editor page with the new group name
        // Using a more flexible selector that works with both Name and name attributes
        cy.get("input[type='text'].form-control").first().should("have.value", newGroupName);
    });

    it("Add Group - Empty Name Validation", () => {
        cy.visit("GroupList.php");
        
        // Try to submit with empty group name
        cy.get("#addNewGroup").click();
        
        // Should show error notification and remain on GroupList page
        // Notyf creates notification containers with class 'notyf'
        cy.get(".notyf__toast", { timeout: 3000 })
            .should("be.visible")
            .and("contain", "Please enter a group name");
        cy.url().should("contain", "GroupList.php");
        
        // Input field should have focus
        cy.get("#groupName").should("have.focus");
    });

    it("View Group ", () => {
        cy.visit("GroupView.php?GroupID=9");
        cy.contains("Group View : Church Board");
        cy.get("#deleteSelectedRows").should("be.visible");
    });

    it("Group Report", () => {
        cy.visit("GroupReports.php");
        cy.contains("Group reports");
        cy.contains("Select the group you would like to report");
        cy.get(".card-body > form").submit();
        cy.url().should("contain", "GroupReports.php");
        cy.contains("Select which information you want to include");
    });
});
