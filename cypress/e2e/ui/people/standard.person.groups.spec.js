/// <reference types="cypress" />

/**
 * UI tests for Person Group Interactions on PersonView.php.
 *
 * Uses API calls to ensure person 2 is in a known group before each test.
 * Depends on seed-data group "Church Board" (ID 9) existing.
 *
 * Design: API setup runs via admin API key (makePrivateAdminAPICall) BEFORE
 * the browser session is established. The standard session is then set up
 * for UI interaction.
 */

describe("Person Group Interactions", () => {
    const personId = 2;
    const testGroupId = 9; // "Church Board" — exists in seed data

    /**
     * Ensure person is a member of the test group via API.
     * The addperson endpoint silently succeeds if already a member (returns 200 either way).
     */
    function ensurePersonInGroup() {
        cy.makePrivateAdminAPICall(
            "POST",
            `/api/groups/${testGroupId}/addperson/${personId}`,
            { RoleID: 1 },
            [200],
        );
    }

    describe("Groups tab with existing membership", () => {
        beforeEach(() => {
            ensurePersonInGroup();
            cy.setupStandardSession();
        });

        it("should display Groups tab with assigned groups", () => {
            cy.visit(`PersonView.php?PersonID=${personId}`);

            // Click Groups tab
            cy.get("#nav-item-groups").click();
            cy.get("#groups").should("be.visible");

            // The specific group we ensured membership in should be listed
            cy.get("#groups .list-group-item")
                .contains("Church Board")
                .should("exist");
        });

        it("should show group action menu with View, Change Role, Remove", () => {
            cy.visit(`PersonView.php?PersonID=${personId}`);
            cy.get("#nav-item-groups").click();

            // Target the specific "Church Board" group row
            cy.get("#groups .list-group-item")
                .contains("Church Board")
                .closest(".list-group-item")
                .as("groupRow");

            // Open the group's action dropdown
            cy.get("@groupRow")
                .find("[data-bs-toggle='dropdown']")
                .click();
            cy.get("@groupRow")
                .find(".dropdown-menu")
                .should("be.visible");

            // Verify menu items exist
            cy.get("@groupRow")
                .find(".dropdown-menu")
                .within(() => {
                    cy.contains("View Group");
                    cy.contains("Change Role");
                    cy.contains("Remove");
                });
        });

        it("should show remove confirmation when clicking Remove", () => {
            cy.visit(`PersonView.php?PersonID=${personId}`);
            cy.get("#nav-item-groups").click();

            // Target the specific "Church Board" group row
            cy.get("#groups .list-group-item")
                .contains("Church Board")
                .closest(".list-group-item")
                .as("groupRow");

            // Open the group's action dropdown and click Remove
            cy.get("@groupRow")
                .find("[data-bs-toggle='dropdown']")
                .click();
            cy.get("@groupRow").find(".groupRemove").click();

            // Bootbox confirmation should appear
            cy.get(".bootbox").should("be.visible");
            cy.get(".bootbox").should("contain", "remove");

            // Cancel the removal
            cy.get(".bootbox .btn-ghost-secondary").click();
        });
    });

    describe("Add to Group modal", () => {
        beforeEach(() => {
            cy.setupStandardSession();
        });

        it("should open Add to Group modal from Actions dropdown", () => {
            cy.visit(`PersonView.php?PersonID=${personId}`);

            // Open Actions dropdown and click Assign New Group
            cy.get("#person-actions-dropdown").click();
            cy.get("#addGroup").click();

            // Modal should appear with group selector
            cy.get("#personGroupModal").should("be.visible");
            cy.get("#personGroupModal .modal-title").should(
                "contain",
                "Add to Group",
            );

            // TomSelect should be initialized with groups loaded
            cy.get("#personGroupModal .ts-control").should("be.visible");

            // Close modal
            cy.get("#personGroupModal [data-bs-dismiss='modal']")
                .first()
                .click();
        });
    });
});
