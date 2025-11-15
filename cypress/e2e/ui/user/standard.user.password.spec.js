/// <reference types="cypress" />

describe("User 2FA", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Ensure QR code displays", () => {
        cy.visit("v2/user/current/enroll2fa");
        cy.get("#begin2faEnrollment")
            .should("exist")
            .should("be.visible")
            .should("be.enabled")
            .click();
        cy.get("#2faQrCodeDataUri")
            .should("exist")
            .should("be.visible")
            .should("have.attr", "src");
    });
});

describe("Standard User Password", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Change with invalid password", () => {
        cy.visit("v2/user/current/changepassword");
        cy.get("#OldPassword").type("ILikePancakes");
        cy.get("#NewPassword1").type("changeyou");
        cy.get("#NewPassword2").type("changeyou");
        cy.get("#passwordChangeForm").submit();
        cy.url().should("contain", "/v2/user/current/changepassword");
        cy.contains("Incorrect password supplied for current user");
    });

    it("Change with simple password", () => {
        cy.visit("v2/user/current/changepassword");
        cy.get("#OldPassword").type("basicjoe");
        cy.get("#NewPassword1").type("password");
        cy.get("#NewPassword2").type("password");
        cy.get("#passwordChangeForm").submit();
        cy.url().should("contain", "/v2/user/current/changepassword");
        cy.contains(
            "Your password choice is too obvious. Please choose something else.",
        );
    });

    it("Change with old password", () => {
        cy.visit("v2/user/current/changepassword");
        cy.get("#OldPassword").type("basicjoe");
        cy.get("#NewPassword1").type("basicjoe");
        cy.get("#NewPassword2").type("basicjoe");
        cy.get("#passwordChangeForm").submit();
        cy.url().should("contain", "/v2/user/current/changepassword");
        cy.contains("Your new password must not match your old one");
    });

    it("Change with like old password", () => {
        cy.visit("v2/user/current/changepassword");
        cy.get("#OldPassword").type("basicjoe");
        cy.get("#NewPassword1").type("basicjoe2");
        cy.get("#NewPassword2").type("basicjoe2");
        cy.get("#passwordChangeForm").submit();
        cy.url().should("contain", "/v2/user/current/changepassword");
        cy.contains("Your new password is too similar to your old one");
    });

    it("Change then back", () => {
        cy.visit("v2/user/current/changepassword");
        cy.get("#OldPassword").type("basicjoe");
        cy.get("#NewPassword1").type(
            "SomeThingsAreBetterLeftUnChangedJustKidding",
        );
        cy.get("#NewPassword2").type(
            "SomeThingsAreBetterLeftUnChangedJustKidding",
        );
        cy.get("#passwordChangeForm").submit();
        cy.url().should("contain", "/v2/user/current/changepassword");
        cy.contains("Password Change Successful");

        cy.visit("/session/end");
        cy.loginWithCredentials("tony.wade@example.com", "SomeThingsAreBetterLeftUnChangedJustKidding", "temp-session");
        cy.visit("v2/user/current/changepassword");
        cy.get("#OldPassword").type(
            "SomeThingsAreBetterLeftUnChangedJustKidding",
        );
        cy.get("#NewPassword1").type("basicjoe");
        cy.get("#NewPassword2").type("basicjoe");
        cy.get("#passwordChangeForm").submit();
        cy.url().should("contain", "/v2/user/current/changepassword");
        cy.contains("Password Change Successful");
    });
});
