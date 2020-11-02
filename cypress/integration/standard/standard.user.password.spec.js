/// <reference types="cypress" />

context('Standard User Password', () => {

    it('Change with invalid password', () => {
        cy.loginStandard('v2/user/current/changepassword');
        cy.get('#OldPassword').type('ILikePancakes');
        cy.get('#NewPassword1').type('changeyou');
        cy.get('#NewPassword2').type('changeyou');
        cy.get('#passwordChangeForm').submit();
        cy.url().should('contains', '/v2/user/current/changepassword');
        cy.contains("Incorrect password supplied for current user")
    });

    it('Change with simple password', () => {
        cy.loginStandard('v2/user/current/changepassword');
        cy.get('#OldPassword').type('abc123');
        cy.get('#NewPassword1').type('password');
        cy.get('#NewPassword2').type('password');
        cy.get('#passwordChangeForm').submit();
        cy.url().should('contains', '/v2/user/current/changepassword');
        cy.contains("Your password choice is too obvious. Please choose something else.")
    });


    it('Change with old password', () => {
        cy.loginStandard('v2/user/current/changepassword');
        cy.get('#OldPassword').type('abc123');
        cy.get('#NewPassword1').type('abc123');
        cy.get('#NewPassword2').type('abc123');
        cy.get('#passwordChangeForm').submit();
        cy.url().should('contains', '/v2/user/current/changepassword');
        cy.contains("Your new password must not match your old one")
    });

    it('Change with like old password', () => {
        cy.loginStandard('v2/user/current/changepassword');
        cy.get('#OldPassword').type('abc123');
        cy.get('#NewPassword1').type('abc1234');
        cy.get('#NewPassword2').type('abc1234');
        cy.get('#passwordChangeForm').submit();
        cy.url().should('contains', '/v2/user/current/changepassword');
        cy.contains("Your new password is too similar to your old one")
    });

    it('Change then back', () => {
        cy.loginStandard('v2/user/current/changepassword');
        cy.get('#OldPassword').type('abc123');
        cy.get('#NewPassword1').type('SomeThingsAreBetterLeftUnChangedJustKidding');
        cy.get('#NewPassword2').type('SomeThingsAreBetterLeftUnChangedJustKidding');
        cy.get('#passwordChangeForm').submit();
        cy.url().should('contains', '/v2/user/current/changepassword');
        cy.contains("Password Change Successful")

        cy.visit("/session/end");
        cy.login("tony.wade@example.com", "SomeThingsAreBetterLeftUnChangedJustKidding", "v2/user/current/changepassword");
        cy.get('#OldPassword').type('SomeThingsAreBetterLeftUnChangedJustKidding');
        cy.get('#NewPassword1').type('abc123');
        cy.get('#NewPassword2').type('abc123');
        cy.get('#passwordChangeForm').submit();
        cy.url().should('contains', '/v2/user/current/changepassword');
        cy.contains("Password Change Successful")
    });

});
