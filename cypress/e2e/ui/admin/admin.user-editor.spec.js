describe("User Editor - ORM Migration Tests", () => {
    // Throwaway person (Constance Hart, family 2) — not a seeded user, so it's
    // safe to create/mutate/delete here without polluting other suites. Module
    // permissions only render under the "Custom" access level, and a brand-new
    // user defaults to Custom (no Admin/EditSelf), so the panel is visible.
    const throwawayPersonId = 6;

    beforeEach(() => {
        cy.setupAdminSession();
    });

    function createCustomUser() {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId}`, null, [200, 204, 404]);
        // The API call sends the session cookie alongside x-api-key, causing PHP to
        // overwrite $_SESSION['AuthenticationProvider'] with APITokenAuthentication.
        // Clear all cy.session() caches so that setupAdminSession() is forced to
        // run the full setup function (fresh PHP login), creating a clean session.
        cy.then(() => Cypress.session.clearAllSavedSessions());
        cy.setupAdminSession();
        // Small wait to allow the fresh PHP session to fully propagate before visiting.
        cy.wait(1000);
        cy.intercept("POST", "**/UserEditor.php*").as("saveUser");
        cy.visit(`UserEditor.php?NewPersonID=${throwawayPersonId}`);
        cy.contains("User Editor");
        cy.get("#customPermissions").should("be.visible");
    }

    function deleteUser() {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId}`, null, [200, 204, 404]);
        // Same: clear all session caches so the next test's beforeEach re-logs in fresh.
        cy.then(() => Cypress.session.clearAllSavedSessions());
    }

    it("Should persist a single Custom permission via ORM", () => {
        createCustomUser();
        cy.get("#Finance").check();
        cy.get("#SaveButton").click();
        cy.wait("@saveUser");

        cy.visit(`UserEditor.php?PersonID=${throwawayPersonId}`);
        cy.get("#customPermissions").should("be.visible");
        cy.get("#Finance").should("be.checked");
        deleteUser();
    });

    it("Should persist multiple Custom permission changes via ORM", () => {
        createCustomUser();
        cy.get("#AddRecords").should("be.visible").check();
        cy.get("#EditRecords").should("be.visible").check();
        cy.get("#Notes").should("be.visible").check();
        cy.get("#SaveButton").click();
        cy.wait("@saveUser");
        cy.wait(500);

        cy.visit(`UserEditor.php?PersonID=${throwawayPersonId}`);
        cy.get("#AddRecords").should("be.checked");
        cy.get("#EditRecords").should("be.checked");
        cy.get("#Notes").should("be.checked");
        deleteUser();
    });

    it("Should update username via ORM", () => {
        // The username field is independent of access level, so exercising it on
        // the admin user (PersonID 1) is safe — its mode/permissions are untouched.
        //
        // Reset to canonical username first in case a prior run was interrupted
        // after mutating to 'admin_orm_test' but before restoring.
        cy.intercept("POST", "**/UserEditor.php*").as("resetIfNeeded");
        cy.visit("UserEditor.php?PersonID=1");
        cy.contains("User Editor");
        cy.get("#UserName").then(($input) => {
            if ($input.val() !== "Admin") {
                cy.get("#UserName").clear().type("Admin");
                cy.get("#SaveButton").click();
                cy.wait("@resetIfNeeded");
            }
        });

        const newUsername = "admin_orm_test";
        cy.intercept("POST", "**/UserEditor.php*").as("saveUser");
        cy.get("#UserName").clear().type(newUsername);
        cy.get("#SaveButton").click();
        cy.wait("@saveUser");

        cy.visit("UserEditor.php?PersonID=1");
        cy.get("#UserName").should("have.value", newUsername);

        // Restore to canonical seed username
        cy.intercept("POST", "**/UserEditor.php*").as("restoreUser");
        cy.get("#UserName").clear().type("Admin");
        cy.get("#SaveButton").click();
        cy.wait("@restoreUser");
    });
});
