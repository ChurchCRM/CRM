describe("Admin User Password", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("List System Users", () => {
        cy.visit("admin/system/users");
        cy.contains("Church Admin");
    });

    it("Admin Change password", () => {
        cy.visit("admin/system/user/99/changePassword");
        cy.contains("Change Password");
        cy.contains("Amanda Black");
        cy.get("#NewPassword1").type("new-user-password");
        cy.get("#NewPassword2").type("new-user-password");
        cy.get("form").submit();
        cy.url().should("contain", "admin/system/user/99/changePassword");
        cy.contains("Password Change Successful");
    });

    it("Non-admin user denied access to change password page", () => {
        cy.setupStandardSession();
        cy.visit({ url: "admin/system/user/99/changePassword", failOnStatusCode: false });
        // Admin app redirects non-admins — should not see the password form
        cy.get("#NewPassword1").should("not.exist");
    });


    it("Create System Users", () => {
        // Ensure clean start: if Peyton Ray already exists as a user, remove that user first
        cy.makePrivateAdminAPICall("DELETE", "/admin/api/user/25", null, [200, 404]);
        // Re-login after API call to restore session (necessary after makePrivateAdminAPICall)
        cy.setupAdminSession({ forceLogin: true });
        
        // Go directly to UserEditor to create a user for PersonID=25
        cy.visit('UserEditor.php?NewPersonID=25');
        cy.contains("User Editor");
        
        // Check that the form has pre-populated the UserName field with a value
        cy.get('#UserName').invoke('val').should('not.be.empty');
        
        // Click Save button to submit the form
        cy.get('#SaveButton').click();
        
        // Navigate to the system users page to verify the user was created
        cy.visit('admin/system/users', { timeout: 10000 });
        
        // Verify the page loaded and has the user table
        cy.get('#user-listing-table').should('exist');
        
        // Verify Peyton was created as a user
        cy.get('body').should('contain.text', 'Peyton Ray');

        // Clean up: remove user status for PersonID=25 via API so test can be re-run
        cy.makePrivateAdminAPICall("DELETE", "/admin/api/user/25", null, [200, 204, 404]);
        // Re-login after API call to restore session
        cy.setupAdminSession({ forceLogin: true });
    });
});
