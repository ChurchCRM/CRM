context("Admin User Password", () => {
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
        cy.url().should("contains", "v2/user/95/changePassword");
        cy.contains("Password Change Successful");
    });
});
