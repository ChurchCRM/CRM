/// <reference types="cypress" />

/**
 * Tests for adding a person to a group with multiple roles,
 * and for navigating to the GroupPropsEditor ("Update Properties")
 * on a group that has special properties enabled.
 *
 * Requires: Docker / local environment with seeded data.
 * - Group 1 (Angels class) must exist with roles Teacher + Student (role list 13)
 * - Group 23 must exist with grp_hasSpecialProps = 1 and a groupprop_master entry
 * - Person 2 must exist (standard test user)
 *
 * Design rule: API setup runs BEFORE freshAdminLogin(); all UI assertions
 * happen after login. Teardown runs in afterEach() to ensure cleanup even
 * when assertions fail mid-test.
 */

const personId = 2;
const multiRoleGroupId = 1; // Angels class — has Teacher (1) + Student (2)
const specialPropsGroupId = 23; // has grp_hasSpecialProps = 1

/**
 * Direct login — bypasses cy.session() cache so that earlier
 * cy.request() calls (which reset the PHP session) don't interfere.
 */
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(
        Cypress.env("admin.password") + "{enter}",
    );
    cy.url().should("not.include", "/session/begin");
}

/**
 * Remove person from a group via API (ignores 404 if not a member).
 */
function removePersonFromGroup(groupId) {
    cy.makePrivateAdminAPICall(
        "DELETE",
        `/api/groups/${groupId}/removeperson/${personId}`,
        null,
        [200, 404],
    );
}

// ------------------------------------------------------------------ //
// Add person to a group that has more than one role
// ------------------------------------------------------------------ //
describe("PersonView: Add to group with multiple roles", () => {
    beforeEach(() => {
        removePersonFromGroup(multiRoleGroupId);
    });

    afterEach(() => {
        removePersonFromGroup(multiRoleGroupId);
    });

    it("should show role picker when selecting a multi-role group and add successfully", () => {
        // Login after API setup
        freshAdminLogin();

        // Visit PersonView and open Add to Group modal
        cy.visit(`/people/view/${personId}`);
        cy.get("#person-actions-dropdown").click();
        cy.get("#addGroup").click();

        cy.get("#personGroupModal").should("be.visible");

        // Select the multi-role group ("Angels class") via TomSelect
        cy.get("#personGroupModal .ts-control").should("be.visible").click();
        cy.get("#personGroupModal .ts-dropdown .option")
            .contains("Angels class")
            .click();

        // Role picker should appear (group has Teacher + Student)
        cy.get("#pgm-role-wrapper").should("be.visible");
        cy.get("#pgm-role-wrapper .ts-control").should("be.visible");

        // Save button should be enabled
        cy.get("#personGroupConfirmBtn").should("not.be.disabled");

        // Click Save — the default role should already be selected
        cy.get("#personGroupConfirmBtn").click();

        // Page reloads — verify the group now appears in the Groups tab
        cy.url().should("include", `people/view/${personId}`);
        cy.get("#nav-item-groups").click();
        cy.get("#groups").should("be.visible");
        cy.get("#groups").contains("Angels class").should("be.visible");
    });

    it("should allow selecting a specific role before adding", () => {
        // Login after API setup
        freshAdminLogin();

        // Visit PersonView and open modal
        cy.visit(`/people/view/${personId}`);
        cy.get("#person-actions-dropdown").click();
        cy.get("#addGroup").click();

        cy.get("#personGroupModal").should("be.visible");

        // Select the multi-role group
        cy.get("#personGroupModal .ts-control").should("be.visible").click();
        cy.get("#personGroupModal .ts-dropdown .option")
            .contains("Angels class")
            .click();

        // Role picker visible — select "Teacher" explicitly
        cy.get("#pgm-role-wrapper").should("be.visible");
        cy.get("#pgm-role-wrapper .ts-control").click();
        cy.get("#pgm-role-wrapper .ts-dropdown .option")
            .contains("Teacher")
            .click();

        // Confirm
        cy.get("#personGroupConfirmBtn").should("not.be.disabled").click();

        // Verify group + role appear after reload
        cy.url().should("include", `people/view/${personId}`);
        cy.get("#nav-item-groups").click();
        cy.get("#groups").should("be.visible");
        cy.get("#groups").contains("Angels class").should("be.visible");
        cy.get("#groups").contains("Teacher").should("be.visible");
    });
});

// ------------------------------------------------------------------ //
// Update Properties link on a group with grp_hasSpecialProps = 1
// ------------------------------------------------------------------ //
describe("PersonView: Update Properties for group with special props", () => {
    afterEach(() => {
        removePersonFromGroup(specialPropsGroupId);
    });

    it("should navigate to GroupPropsEditor without error", () => {
        // API setup — add person to group 23 (has special props)
        cy.makePrivateAdminAPICall(
            "POST",
            `/api/groups/${specialPropsGroupId}/addperson/${personId}`,
            { RoleID: 2 },
            [200],
        );

        // Login after API setup
        freshAdminLogin();

        // Visit PersonView and go to Groups tab
        cy.visit(`/people/view/${personId}`);
        cy.get("#nav-item-groups").click();
        cy.get("#groups").should("be.visible");

        // Find the special-props group row by data-groupid and open its action menu
        cy.get(`.groupRemove[data-groupid="${specialPropsGroupId}"]`)
            .closest(".list-group-item")
            .within(() => {
                cy.get("[data-bs-toggle='dropdown']").click();
                cy.get(".dropdown-menu").should("be.visible");

                // "Update Properties" should exist (since hasSpecialProps=1)
                cy.contains("Update Properties").should("be.visible");
                cy.contains("Update Properties").click();
            });

        // Should land on GroupPropsEditor without fatal PHP error
        cy.url().should("include", "GroupPropsEditor.php");
        cy.url().should("include", `GroupID=${specialPropsGroupId}`);
        cy.url().should("include", `PersonID=${personId}`);

        // The page should NOT show a PHP error or blank page
        cy.get("body").should("not.contain.text", "Fatal error");
        cy.get("body").should("not.contain.text", "Warning:");
        cy.get("body").should("not.contain.text", "Notice:");

        // The property form should be visible (seed data defines a groupprop_master row for group 23)
        cy.get("form[name='GroupPropEditor']").should("exist").and("be.visible");
        cy.get("form[name='GroupPropEditor'] input[type='submit']").should(
            "exist",
        );
    });

    it("should display the property form with editable fields", () => {
        // API setup — add person to group 23
        cy.makePrivateAdminAPICall(
            "POST",
            `/api/groups/${specialPropsGroupId}/addperson/${personId}`,
            { RoleID: 2 },
            [200],
        );

        // Login
        freshAdminLogin();

        // Navigate directly to GroupPropsEditor
        cy.visit(
            `/GroupPropsEditor.php?GroupID=${specialPropsGroupId}&PersonID=${personId}`,
        );

        // Page should load without errors
        cy.get("body").should("not.contain.text", "Fatal error");
        cy.get("body").should("not.contain.text", "Warning:");

        // The groupprop_master has a "sdfsaf" property (Year type)
        // so the form must render with that field
        cy.get("form[name='GroupPropEditor']").should("exist").and("be.visible");
        cy.contains("sdfsaf").should("be.visible");

        // Cancel button (now an <a> tag — was converted from input to avoid inline onclick CSP issues)
        // Scope to the form to avoid matching the hidden IssueReportModal Cancel button
        cy.get("form[name='GroupPropEditor']").contains("a.btn", "Cancel").click();
        cy.url().should("include", `people/view/${personId}`);
    });
});
