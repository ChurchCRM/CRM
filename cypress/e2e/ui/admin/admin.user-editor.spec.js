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
        // makePrivateAdminAPICall resets the session — re-login before visiting.
        cy.setupAdminSession({ forceLogin: true });
        cy.intercept("POST", "**/UserEditor.php*").as("saveUser");
        cy.visit(`UserEditor.php?NewPersonID=${throwawayPersonId}`);
        cy.contains("User Editor");
        cy.get("#customPermissions").should("be.visible");
    }

    function deleteUser() {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId}`, null, [200, 204, 404]);
        cy.setupAdminSession({ forceLogin: true });
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
        cy.get("#AddRecords").check();
        cy.get("#EditRecords").check();
        cy.get("#Notes").check();
        cy.get("#SaveButton").click();
        cy.wait("@saveUser");

        cy.visit(`UserEditor.php?PersonID=${throwawayPersonId}`);
        cy.get("#AddRecords").should("be.checked");
        cy.get("#EditRecords").should("be.checked");
        cy.get("#Notes").should("be.checked");
        deleteUser();
    });

    it("Should update username via ORM", () => {
        // The username field is independent of access level, so exercising it on
        // the admin user (PersonID 1) is safe — its mode/permissions are untouched.
        cy.intercept("POST", "**/UserEditor.php*").as("saveUser");
        cy.visit("UserEditor.php?PersonID=1");
        cy.contains("User Editor");

        cy.get("#UserName").invoke("val").then((originalUsername) => {
            const newUsername = "admin_orm_test";

            cy.get("#UserName").clear().type(newUsername);
            cy.get("#SaveButton").click();
            cy.wait("@saveUser");

            cy.visit("UserEditor.php?PersonID=1");
            cy.get("#UserName").should("have.value", newUsername);

            cy.get("#UserName").clear().type(originalUsername);
            cy.get("#SaveButton").click();
            cy.wait("@saveUser");
        });
    });
});
