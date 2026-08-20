describe("Admin User Password", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("List System Users", () => {
        cy.visit("admin/system/users");
        cy.contains("Church Admin");
    });

    it("Shows Status column header in the users table", () => {
        cy.visit("admin/system/users");
        cy.get("#user-listing-table thead th").contains("Status").should("exist");
    });

    it("Shows em-dash in Status column for a normal active user", () => {
        cy.visit("admin/system/users");
        // Status is the 6th column (Name, Login Name, Access, Last Login, Failed Logins, Status).
        // Target it by position to avoid matching the pre-existing em-dash in Failed Logins (col 5).
        cy.contains("#user-listing-table tbody tr", "Church Admin")
            .find("td:nth-child(6)")
            .should("contain.text", "—");
    });

    it("Admin Change password", () => {
        cy.visit("admin/system/user/99/changePassword");
        cy.contains("Change Password");
        cy.contains("Amanda Black");
        cy.get("#NewPassword1").type("new-user-password");
        cy.get("#NewPassword2").type("new-user-password");
        cy.get("#NewPassword1").closest("form").submit();
        cy.url().should("contain", "admin/system/user/99/changePassword");
        cy.contains("Password Change Successful");
    });

    it("Non-admin user denied access to change password page", () => {
        cy.setupStandardSession();
        cy.visit({ url: "admin/system/user/99/changePassword", failOnStatusCode: false });
        // AdminRoleAuthMiddleware redirects non-admins to access-denied page
        cy.url().should("include", "access-denied");
        cy.get("#NewPassword1").should("not.exist");
    });


    it("Create System Users", () => {
        // Ensure clean start: if Peyton Ray already exists as a user, remove that user first
        cy.makePrivateAdminAPICall("DELETE", "/admin/api/user/25", null, [200, 404]);
        // Re-login after API call to restore session (necessary after makePrivateAdminAPICall)
        cy.setupAdminSession({ forceLogin: true });
        
        // Go directly to user editor to create a user for PersonID=25
        cy.visit('admin/system/users/new?personId=25');
        cy.contains("User Editor");
        
        // Check that the form has pre-populated the UserName field with a value
        cy.get('#UserName').invoke('val').should('not.be.empty');
        
        // Click Save button to submit the form
        cy.get('#SaveButton').click();
        
        // Navigate to the system users page to verify the user was created
        cy.visit('admin/system/users', { timeout: 10000 });
        
        // Verify the page loaded and has the user table
        cy.get('#user-listing-table').should('exist');
        
        // DataTables v2 uses class .dt-search (not #table_id_filter) for the search wrapper.
        // Search so Peyton Ray is visible regardless of pagination page.
        cy.get('.dt-search input').type('Peyton Ray');
        cy.get('#user-listing-table tbody').should('contain.text', 'Peyton Ray');

        // Clean up: remove user status for PersonID=25 via API so test can be re-run
        cy.makePrivateAdminAPICall("DELETE", "/admin/api/user/25", null, [200, 204, 404]);
        // Re-login after API call to restore session
        cy.setupAdminSession({ forceLogin: true });
    });

    // GHSA-4qpj-3hw2-52g8: Stored XSS via Person Name in Admin Users page.
    // Verify that action menu items use safe data-* attributes instead of
    // inline onclick handlers, eliminating the JS-string-in-HTML-attribute
    // context that allowed arbitrary script injection.
    it("GHSA-4qpj-3hw2-52g8: user action menu uses safe data-* attributes, not inline onclick", () => {
        // The previous test (Create System Users) ends with forceLogin, which
        // switches the browser to a new uniquely-named session. The shared
        // beforeEach then restores the older 'admin-session' from Cypress cache,
        // but that PHP session can be stale after 21+ minutes of CI run time.
        // Force a fresh login here to guarantee a valid server-side session.
        cy.setupAdminSession({ forceLogin: true });

        cy.visit("admin/system/users");
        cy.get("#user-listing-table").should("exist");

        // The logged-in admin's own row has no delete button, but every other
        // user row does. With the test DB having many users, page 1 always
        // contains at least one non-self user — no DataTable search or
        // pagination needed.
        cy.get("#user-listing-table tbody .js-delete-user")
            .should("have.length.at.least", 1)
            .first()
            .as("deleteLink");

        // Must use safe data-* attributes, not inline onclick.
        // This is the structural guard against regression to the XSS-vulnerable
        // pattern of embedding JS strings directly in HTML attribute context.
        cy.get("@deleteLink").should("have.attr", "data-user_id");
        cy.get("@deleteLink").invoke("attr", "data-user_id").should("match", /^\d+$/);
        cy.get("@deleteLink").should("have.attr", "data-user_name");
        cy.get("@deleteLink").should("not.have.attr", "onclick");
    });
});
