/// <reference types="cypress" />

describe("Person Group Interactions", () => {
    const personId = 2;

    beforeEach(() => cy.setupStandardSession());

    it("should display Groups tab with assigned groups", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);

        // Click Groups tab
        cy.get("#nav-item-groups").click();
        cy.get("#groups").should("be.visible");

        // Should have at least one group listed
        cy.get("#groups .list-group-item").should("have.length.gte", 1);
    });

    it("should show group action menu with View, Change Role, Remove", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);
        cy.get("#nav-item-groups").click();

        // Open the first group's action dropdown
        cy.get("#groups .list-group-item").first().find("[data-bs-toggle='dropdown']").click();
        cy.get("#groups .list-group-item").first().find(".dropdown-menu").should("be.visible");

        // Verify menu items exist
        cy.get("#groups .list-group-item").first().find(".dropdown-menu").within(() => {
            cy.contains("View Group");
            cy.contains("Change Role");
            cy.contains("Remove");
        });
    });

    it("should open Add to Group modal from Actions dropdown", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);

        // Open Actions dropdown and click Assign New Group
        cy.get("#person-actions-dropdown").click();
        cy.get("#addGroup").click();

        // Modal should appear with group selector
        cy.get("#personGroupModal").should("be.visible");
        cy.get("#personGroupModal .modal-title").should("contain", "Add to Group");

        // TomSelect should be initialized with groups loaded
        cy.get("#personGroupModal .ts-control").should("be.visible");

        // Close modal
        cy.get("#personGroupModal [data-bs-dismiss='modal']").first().click();
    });

    it("should show remove confirmation when clicking Remove", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);
        cy.get("#nav-item-groups").click();

        // Open the first group's action dropdown and click Remove
        cy.get("#groups .list-group-item").first().find("[data-bs-toggle='dropdown']").click();
        cy.get("#groups .list-group-item").first().find(".groupRemove").click();

        // Bootbox confirmation should appear
        cy.get(".bootbox").should("be.visible");
        cy.get(".bootbox").should("contain", "remove");

        // Cancel the removal
        cy.get(".bootbox .btn-ghost-secondary").click();
    });
});
