/// <reference types="cypress" />

describe("Standard Groups", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Add Group ", () => {
        const uniqueSeed = Date.now().toString();
        const newGroupName = "New Test Group " + uniqueSeed;

        cy.visit("groups/dashboard");
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
        cy.visit("groups/dashboard");

        // Try to submit with empty group name
        cy.get("#addNewGroup").click();

        // Input should receive the is-invalid class and focus
        cy.get("#groupName")
            .should("have.class", "is-invalid")
            .and("have.focus");

        // Should remain on the groups dashboard
        cy.url().should("contain", "groups/dashboard");
    });

    it("View Group ", () => {
        cy.visit("groups/view/9");
        cy.contains("Group View : Church Board");
        // Two-column layout with members card and properties sidebar
        cy.get("#membersTable").should("exist");
        cy.get("#role-pills").should("exist");
    });

    it("Group View members table has action menus", () => {
        cy.visit("groups/view/9");
        cy.get("#membersTable", { timeout: 10000 }).should("exist");
        cy.get("#membersTable tbody tr", { timeout: 10000 }).then(($rows) => {
            if ($rows.length > 0) {
                cy.get("#membersTable tbody tr:first").within(() => {
                    cy.get('[data-bs-toggle="dropdown"]').first().click();
                });
                cy.get(".dropdown-menu.show").within(() => {
                    cy.contains("View").should("exist");
                    cy.contains("Change Role").should("exist");
                    cy.get(".AddToCart, .RemoveFromCart").should("exist");
                    cy.contains("Remove").should("exist");
                });
            }
        });
    });

    it("Groups dashboard table has action menus", () => {
        cy.visit("groups/dashboard");
        cy.get("#groupsTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);
        cy.get("#groupsTable tbody tr:first").within(() => {
            cy.get('[data-bs-toggle="dropdown"], .dropdown-toggle, button[aria-expanded]').first().click();
        });
        cy.get(".dropdown-menu.show").within(() => {
            cy.contains("View").should("exist");
            cy.contains("Edit").should("exist");
            cy.contains("Delete").should("exist");
        });
    });

    it("Groups dashboard table has action menus", () => {
        cy.visit("groups/dashboard");
        cy.get("#groupsTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 1);
        cy.get("#groupsTable tbody tr:first").within(() => {
            cy.get('[data-bs-toggle="dropdown"], .dropdown-toggle, button[aria-expanded]').first().click();
        });
        cy.get(".dropdown-menu.show").within(() => {
            cy.contains("View").should("exist");
            cy.contains("Edit").should("exist");
            cy.contains("Delete").should("exist");
        });
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
