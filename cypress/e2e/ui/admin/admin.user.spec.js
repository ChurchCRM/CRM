describe("Admin User Password", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("List System Users", () => {
        cy.visit("admin/system/users");
        cy.contains("Church Admin");
    });

    it("Admin Change password", () => {
        cy.visit("v2/user/99/changePassword");
        cy.contains("Change Password: Amanda Black");
        cy.get("#NewPassword1").type("new-user-password");
        cy.get("#NewPassword2").type("new-user-password");
        cy.get("form:nth-child(2)").submit();
        cy.url().should("contain", "v2/user/99/changePassword");
        cy.contains("Password Change Successful");
    });


    it("Create System Users", () => {
        cy.visit("admin/system/users");
        // Ensure clean start: if Peyton Ray already exists as a user, remove that user first
        cy.get('body').then(($body) => {
            if ($body.text().includes('Peyton Ray')) {
                cy.makePrivateAdminAPICall("DELETE", "/admin/api/user/25", null, [200, 404]);
                cy.visit('admin/system/users');
                cy.get('body').should('not.contain', 'Peyton Ray');
            }
        });
        cy.visit("PersonView.php?PersonID=25");
        cy.contains("Peyton Ray");
        cy.contains("Edit User").should('not.exist');
        cy.contains("Make User");
        cy.visit('UserEditor.php?NewPersonID=25');
        cy.contains("User Editor");
        cy.get('.TextColumnWithBottomBorder > select').select('skin-yellow');
        cy.get('#SaveButton').click();
        cy.url().should('contain', 'admin/system/users');
        // Wait for the table to load
        cy.get('#user-listing-table').should('exist');
        cy.contains('Peyton Ray');

        // Clean up: remove user status for PersonID=25 via API so test can be re-run
        cy.makePrivateAdminAPICall("DELETE", "/admin/api/user/25", null, [200, 204, 404]);

        // Verify user no longer appears in the listing
        cy.visit('admin/system/users');
        cy.contains('Peyton Ray').should('not.exist');
    });
});
