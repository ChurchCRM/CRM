describe("User Editor - ORM Migration Tests", () => {
    // Throwaway persons — not seeded users, safe to create/delete here.
    // Each test uses a DIFFERENT person ID so parallel CI workers (test-root
    // and test-subdir) never race on the same DB row.
    // Test 1: PersonID=6 (Constance Hart, constance.hart@example.com, family 2)
    // Test 2: PersonID=5 (Albert Campbell, albert.garcia@example.com, family 1)
    const throwawayPersonId = 6;
    const throwawayPersonId2 = 5;

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

    function createCustomUser2() {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId2}`, null, [200, 204, 404]);
        cy.then(() => Cypress.session.clearAllSavedSessions());
        cy.setupAdminSession();
        cy.wait(1000);
        cy.intercept("POST", "**/UserEditor.php*").as("saveUser");
        cy.visit(`UserEditor.php?NewPersonID=${throwawayPersonId2}`);
        cy.contains("User Editor");
        cy.get("#customPermissions").should("be.visible");
    }

    function deleteUser2() {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId2}`, null, [200, 204, 404]);
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
        createCustomUser2();
        cy.get("#EditRecords").should("be.visible").check();
        cy.get("#SaveButton").click();
        cy.wait("@saveUser");
        cy.wait(500);

        cy.visit(`UserEditor.php?PersonID=${throwawayPersonId2}`);
        cy.get("#customPermissions").should("be.visible");
        cy.get("#EditRecords").should("be.checked");
        deleteUser2();
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
