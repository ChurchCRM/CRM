describe("Admin User Password", () => {
    it("List System Users", () => {
        cy.loginAdmin("UserList.php");
        cy.contains("Church Admin");
    });

    it("Admin Change password", () => {
        cy.loginAdmin("v2/user/95/changePassword");
        cy.contains("Change Password: Judith Kennedy");
        cy.get("#NewPassword1").type("new-user-password");
        cy.get("#NewPassword2").type("new-user-password");
        cy.get("form:nth-child(2)").submit();
        cy.url().should("contain", "v2/user/95/changePassword");
        cy.contains("Password Change Successful");
    });


    it("Create System Users", () => {
        cy.loginAdmin("UserList.php");
        cy.contains("Peyton Ray").should('not.exist');
        cy.visit("PersonView.php?PersonID=25");
        cy.contains("Peyton Ray");
        cy.contains("Edit User").should('not.exist');
        cy.contains("Make User");
        cy.visit('UserEditor.php?NewPersonID=25');
        cy.contains("User Editor");
        cy.get('.TextColumnWithBottomBorder > select').type('skin-yellow');
        cy.get('tr:nth-child(14) .btn-primary').click();
        cy.url().should('contains', 'UserList.php');
        cy.contains("Peyton Ray");

    });
});
