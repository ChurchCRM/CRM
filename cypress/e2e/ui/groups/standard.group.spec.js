/// <reference types="cypress" />

context("Standard Groups", () => {
    it("Add Group ", () => {
        const uniqueSeed = Date.now().toString();
        const newGroupName = "New Test Group " + uniqueSeed;

        cy.loginStandard("GroupList.php");
        cy.get("#groupName").type(newGroupName);
        cy.get("#addNewGroup").click();
        cy.get("label > input").type(newGroupName);
        cy.contains(newGroupName);
    });

    it("Filter Group ", () => {
        cy.loginStandard("GroupList.php");
        cy.contains("Clergy");
        cy.get("#table-filter").type("Scouts");
        cy.contains("Clergy").should("not.be.visible");
    });

    it("View Group ", () => {
        cy.loginStandard("GroupView.php?GroupID=9");
        cy.contains("Group View : Church Board");
        cy.get("#deleteSelectedRows").should("be.visible");
    });

    it("Group Report", () => {
        cy.loginStandard("GroupReports.php");
        cy.contains("Group reports");
        cy.contains("Select the group you would like to report");
        cy.get(".card-body > form").submit();
        cy.url().should("contains", "GroupReports.php");
        cy.contains("Select which information you want to include");
    });
});
