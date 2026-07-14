describe("User Editor - ORM Migration Tests", () => {
    // Throwaway persons — not seeded users, safe to create/delete here.
    // Each test uses a DIFFERENT person ID so parallel CI workers (test-root
    // and test-subdir) never race on the same DB row.
    // Test 1: PersonID=6 (Constance Hart, constance.hart@example.com, family 2)
    // Test 2: PersonID=5 (Albert Campbell, albert.garcia@example.com, family 1)
    // Test 3: PersonID=7 (Manage Events test — distinct ID to avoid CI races)
    const throwawayPersonId = 6;
    const throwawayPersonId2 = 5;
    const throwawayPersonId3 = 7;

    beforeEach(() => {
        cy.setupAdminSession();
    });

    function createCustomUser() {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId}`, null, [200, 204, 404]);
        // The API call's response Set-Cookie will overwrite the browser's session cookie
        // and contaminate the Cypress 'admin-session' cache when cy.session() implicitly
        // saves the current browser state. Clear all saved sessions then force a fresh
        // admin login to rebuild the 'admin-session' cache with a valid admin cookie.
        cy.then(() => Cypress.session.clearAllSavedSessions());
        cy.setupAdminSession();
        cy.intercept("POST", `**/admin/system/users/new*`).as("saveUser");
        cy.visit(`admin/system/users/new?personId=${throwawayPersonId}`);
        cy.contains("User Editor");
        cy.get("#customPermissions").should("be.visible");
    }

    function deleteUser() {
        // withCredentials:false prevents sending the session cookie on the request
        // but the response Set-Cookie still updates the browser jar, contaminating
        // the 'admin-session' Cypress cache. Clear all sessions after cleanup so
        // the next test's beforeEach re-establishes a clean admin session.
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId}`, null, [200, 204, 404]);
        cy.then(() => Cypress.session.clearAllSavedSessions());
    }

    function createCustomUser2() {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId2}`, null, [200, 204, 404]);
        cy.then(() => Cypress.session.clearAllSavedSessions());
        cy.setupAdminSession();
        cy.intercept("POST", `**/admin/system/users/new*`).as("saveUser");
        cy.visit(`admin/system/users/new?personId=${throwawayPersonId2}`);
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

        // Verify the user was created and Finance flag persisted via edit form
        cy.visit(`admin/system/users/${throwawayPersonId}/edit`);
        cy.contains("User Editor");
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

        cy.visit(`admin/system/users/${throwawayPersonId2}/edit`);
        cy.contains("User Editor");
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
        cy.intercept("POST", "**/admin/system/users/1/edit*").as("resetIfNeeded");
        cy.visit("admin/system/users/1/edit");
        cy.contains("User Editor");
        cy.get("#UserName").then(($input) => {
            if ($input.val() !== "Admin") {
                cy.get("#UserName").clear().type("Admin");
                cy.get("#SaveButton").click();
                cy.wait("@resetIfNeeded");
            }
        });

        const newUsername = "admin_orm_test";
        cy.intercept("POST", "**/admin/system/users/1/edit*").as("saveUser");
        cy.get("#UserName").clear().type(newUsername);
        cy.get("#SaveButton").click();
        cy.wait("@saveUser");

        cy.visit("admin/system/users/1/edit");
        cy.get("#UserName").should("have.value", newUsername);

        // Restore to canonical seed username
        cy.intercept("POST", "**/admin/system/users/1/edit*").as("restoreUser");
        cy.get("#UserName").clear().type("Admin");
        cy.get("#SaveButton").click();
        cy.wait("@restoreUser");
    });

    it("Should persist Manage Events permission for new Custom user", () => {
        // bEnabledEvents defaults to '1' in the seed — the #AddEvent toggle is present.
        // This test covers both states:
        //   - new user WITHOUT Manage Events → AddEvent unchecked after load (explicit FALSE row)
        //   - edit to enable Manage Events → AddEvent checked after reload (TRUE row)
        //   - switching to self-service mode clears the toggle (JS exclusivity)
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId3}`, null, [200, 204, 404]);
        cy.then(() => Cypress.session.clearAllSavedSessions());
        cy.setupAdminSession();

        // --- Part 1: Create user WITHOUT Manage Events (AddEvent unchecked) ---
        cy.intercept("POST", "**/admin/system/users/new*").as("saveUser");
        cy.visit(`admin/system/users/new?personId=${throwawayPersonId3}`);
        cy.contains("User Editor");
        cy.get("#customPermissions").should("be.visible");
        // Manage Events toggle is present and unchecked by default
        cy.get("#AddEvent").should("exist").should("not.be.checked");
        // Save without checking AddEvent
        cy.get("#SaveButton").click();
        cy.wait("@saveUser");

        // Reload edit — AddEvent must remain unchecked (explicit FALSE row was written)
        cy.visit(`admin/system/users/${throwawayPersonId3}/edit`);
        cy.contains("User Editor");
        cy.get("#customPermissions").should("be.visible");
        cy.get("#AddEvent").should("not.be.checked");

        // --- Part 2: Edit to grant Manage Events (AddEvent checked) ---
        cy.intercept("POST", `**/admin/system/users/${throwawayPersonId3}/edit*`).as("editUser");
        cy.get("#AddEvent").check();
        cy.get("#SaveButton").click();
        cy.wait("@editUser");

        // Reload edit — AddEvent must now be checked (TRUE row was written)
        cy.visit(`admin/system/users/${throwawayPersonId3}/edit`);
        cy.contains("User Editor");
        cy.get("#customPermissions").should("be.visible");
        cy.get("#AddEvent").should("be.checked");

        // --- Part 3: JS exclusivity — switching to self-service must clear AddEvent ---
        cy.get('input[name="accessMode"][value="self"]').check();
        cy.get("#AddEvent").should("not.be.checked");

        // Cleanup
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId3}`, null, [200, 204, 404]);
        cy.then(() => Cypress.session.clearAllSavedSessions());
    });
});

describe("User Editor - Person picker (no ?personId)", () => {
    before(() => {
        cy.setupAdminSession();
    });

    it("Shows the person-picker dropdown when no personId is given", () => {
        cy.visit("admin/system/users/new");
        cy.contains("User Editor");
        // The native <select> stays in DOM after TomSelect hides it
        cy.get("#personSelect").should("exist");
        // Username field present
        cy.get("#UserName").should("exist");
        // Access level radios present
        cy.get('input[name="accessMode"]').should("have.length", 3);
        // Save button present
        cy.get("#SaveButton").should("exist");
    });
});
