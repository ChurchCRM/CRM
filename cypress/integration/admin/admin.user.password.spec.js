
context('Admin User Password', () => {

    it('Admin Change password', () => {
        cy.loginAdmin("UserList.php");
        cy.get('.odd:nth-child(3) .fa-wrench').click();
        cy.url().should('contains', 'v2/user/76/changePassword');
        cy.contains("Change Password: Leroy Larson");

        cy.get('#NewPassword1').type('new-user-password');
        cy.get('#NewPassword2').type('new-user-password');
        cy.get('form:nth-child(2)').submit();
        cy.url().should('contains', 'v2/user/76/changePassword');
        cy.contains("Password Change Successful");

    });

});
