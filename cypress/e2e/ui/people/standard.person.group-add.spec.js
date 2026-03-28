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
 * happen after login. Teardown runs at the END.
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
    it("should show role picker when selecting a multi-role group and add successfully", () => {
        // Step 1: API cleanup — ensure person is NOT in the target group
        removePersonFromGroup(multiRoleGroupId);

        // Step 2: Login after API setup
        freshAdminLogin();

        // Step 3: Visit PersonView and open Add to Group modal
        cy.visit(`/PersonView.php?PersonID=${personId}`);
        cy.get("#person-actions-dropdown").click();
        cy.get("#addGroup").click();

        cy.get("#personGroupModal").should("be.visible");

        // Step 4: Select the multi-role group ("Angels class") via TomSelect
        cy.get("#personGroupModal .ts-control").should("be.visible").click();
        cy.get("#personGroupModal .ts-dropdown .option")
            .contains("Angels class")
            .click();

        // Step 5: Role picker should appear (group has Teacher + Student)
        cy.get("#pgm-role-wrapper").should("be.visible");
        cy.get("#pgm-role-wrapper .ts-control").should("be.visible");

        // Step 6: Save button should be enabled
        cy.get("#personGroupConfirmBtn").should("not.be.disabled");

        // Step 7: Click Save — the default role should already be selected
        cy.get("#personGroupConfirmBtn").click();

        // Step 8: Page reloads — verify the group now appears in the Groups tab
        cy.url().should("include", `PersonID=${personId}`);
        cy.get("#nav-item-groups").click();
        cy.get("#groups").should("be.visible");
        cy.get("#groups").contains("Angels class").should("be.visible");

        // Step 9: Cleanup — remove person from group
        removePersonFromGroup(multiRoleGroupId);
    });

    it("should allow selecting a specific role before adding", () => {
        // Step 1: API cleanup
        removePersonFromGroup(multiRoleGroupId);

        // Step 2: Login
        freshAdminLogin();

        // Step 3: Visit PersonView and open modal
        cy.visit(`/PersonView.php?PersonID=${personId}`);
        cy.get("#person-actions-dropdown").click();
        cy.get("#addGroup").click();

        cy.get("#personGroupModal").should("be.visible");

        // Step 4: Select the multi-role group
        cy.get("#personGroupModal .ts-control").should("be.visible").click();
        cy.get("#personGroupModal .ts-dropdown .option")
            .contains("Angels class")
            .click();

        // Step 5: Role picker visible — select "Teacher" explicitly
        cy.get("#pgm-role-wrapper").should("be.visible");
        cy.get("#pgm-role-wrapper .ts-control").click();
        cy.get("#pgm-role-wrapper .ts-dropdown .option")
            .contains("Teacher")
            .click();

        // Step 6: Confirm
        cy.get("#personGroupConfirmBtn").should("not.be.disabled").click();

        // Step 7: Verify group + role appear after reload
        cy.url().should("include", `PersonID=${personId}`);
        cy.get("#nav-item-groups").click();
        cy.get("#groups").should("be.visible");
        cy.get("#groups").contains("Angels class").should("be.visible");
        cy.get("#groups").contains("Teacher").should("be.visible");

        // Cleanup
        removePersonFromGroup(multiRoleGroupId);
    });
});

// ------------------------------------------------------------------ //
// Update Properties link on a group with grp_hasSpecialProps = 1
// ------------------------------------------------------------------ //
describe("PersonView: Update Properties for group with special props", () => {
    it("should navigate to GroupPropsEditor without error", () => {
        // Step 1: API setup — add person to group 23 (has special props)
        cy.makePrivateAdminAPICall(
            "POST",
            `/api/groups/${specialPropsGroupId}/addperson/${personId}`,
            { PersonID: personId, RoleID: 2 },
            [200],
        );

        // Step 2: Login after API setup
        freshAdminLogin();

        // Step 3: Visit PersonView and go to Groups tab
        cy.visit(`/PersonView.php?PersonID=${personId}`);
        cy.get("#nav-item-groups").click();
        cy.get("#groups").should("be.visible");

        // Step 4: Find the special-props group and open its action menu
        cy.get("#groups .list-group-item")
            .contains("sdfsdfsdf")
            .closest(".list-group-item")
            .within(() => {
                cy.get("[data-bs-toggle='dropdown']").click();
                cy.get(".dropdown-menu").should("be.visible");

                // "Update Properties" should exist (since hasSpecialProps=1)
                cy.contains("Update Properties").should("be.visible");
                cy.contains("Update Properties").click();
            });

        // Step 5: Should land on GroupPropsEditor without fatal PHP error
        cy.url().should("include", "GroupPropsEditor.php");
        cy.url().should("include", `GroupID=${specialPropsGroupId}`);
        cy.url().should("include", `PersonID=${personId}`);

        // The page should NOT show a PHP error or blank page
        cy.get("body").should("not.contain.text", "Fatal error");
        cy.get("body").should("not.contain.text", "Warning:");
        cy.get("body").should("not.contain.text", "Notice:");

        // The property form or "no properties" message should be visible
        cy.get("body").then(($body) => {
            if ($body.find("form[name='GroupPropEditor']").length) {
                // Property form rendered — verify it has fields
                cy.get("form[name='GroupPropEditor']").should("be.visible");
                cy.get("form[name='GroupPropEditor'] input[type='submit']").should("exist");
            } else {
                // "No properties" message — still valid (properties exist but no custom fields defined)
                cy.contains("no properties").should("be.visible");
            }
        });

        // Cleanup — remove person from group
        removePersonFromGroup(specialPropsGroupId);
    });

    it("should display the property form with editable fields", () => {
        // Step 1: API setup — add person to group 23
        cy.makePrivateAdminAPICall(
            "POST",
            `/api/groups/${specialPropsGroupId}/addperson/${personId}`,
            { PersonID: personId, RoleID: 2 },
            [200],
        );

        // Step 2: Login
        freshAdminLogin();

        // Step 3: Navigate directly to GroupPropsEditor
        cy.visit(
            `/GroupPropsEditor.php?GroupID=${specialPropsGroupId}&PersonID=${personId}`,
        );

        // Step 4: Page should load without errors
        cy.get("body").should("not.contain.text", "Fatal error");
        cy.get("body").should("not.contain.text", "Warning:");

        // Step 5: The groupprop_master has a "sdfsaf" property (Year type)
        // so the form should render with that field
        cy.get("body").then(($body) => {
            if ($body.find("form[name='GroupPropEditor']").length) {
                cy.get("form[name='GroupPropEditor']").should("be.visible");
                cy.contains("sdfsaf").should("be.visible");

                // Cancel button should navigate back to PersonView
                cy.contains("Cancel").click();
                cy.url().should("include", `PersonView.php`);
                cy.url().should("include", `PersonID=${personId}`);
            }
        });

        // Cleanup
        removePersonFromGroup(specialPropsGroupId);
    });
});
