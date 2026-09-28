    /// <reference types="cypress" />

describe("User 2FA", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Ensure QR code displays", () => {
        cy.visit("v2/user/current/manage2fa");
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

});

/**
 * The only test here that actually succeeds in changing a password, and so the
 * only one with a side effect.
 *
 * User::updatePassword() rotates usr_apiKey (GHSA-f2fq-4rmp-9x8c). This test
 * used to run against tony.wade (user 3), whose seeded key is the
 * `user.api.key` fixture that cypress/configs/*.ts hand to
 * cy.makePrivateUserAPICall — so a single run destroyed it and every later run
 * against the same database failed with 401 until the database was reseeded
 * (issue #9778). Restoring the seeded value afterwards is not possible from a
 * spec: no route sets an API key to a chosen value, /user/{id}/apikey/regen
 * only generates a random one and the field on the user settings page is
 * readonly.
 *
 * So the test creates its own user, does the round trip against that, and
 * deletes it again. Nothing seeded is touched.
 */
describe("Standard User Password - successful change", () => {
    // Person 24 (Clyde Ray) is seeded, has no user account, and is not
    // referenced by any other spec. Person 25 is deliberately avoided:
    // ui-admin/admin.user.spec.js creates and deletes a user for it.
    const PERSON_ID = 24;
    const FIRST_PASSWORD = "TheFirstPasswordForTheThrowawayUser";
    const SECOND_PASSWORD = "SomeThingsAreBetterLeftUnChangedJustKidding";

    let loginName;

    // The delete route is declared as $group->delete('/') inside the
    // /api/user/{userId} group, so the trailing slash is load-bearing:
    // /admin/api/user/24 is a 404, /admin/api/user/24/ is the route.
    const deleteTestUser = () =>
        cy.makePrivateAdminAPICall(
            "DELETE",
            `/admin/api/user/${PERSON_ID}/`,
            null,
            // 404 when a previous run already cleaned up.
            [200, 404],
        );

    before(() => {
        cy.setupAdminSession({ forceLogin: true });
        deleteTestUser();
        // makePrivateAdminAPICall replaces the browser session, so log in again
        // before driving the admin UI.
        cy.setupAdminSession({ forceLogin: true });

        cy.visit(`admin/system/users/new?personId=${PERSON_ID}`);
        cy.get("#UserName")
            .invoke("val")
            .then((value) => {
                expect(value, "auto-populated login name").to.not.be.empty;
                loginName = value;
            });
        cy.get("#SaveButton").click();
        // createUser() failures re-render the editor rather than redirecting, and
        // the editor URL also contains "admin/system/users" — so assert we left
        // the /new form, not merely that the path looks right.
        cy.url().should("include", "admin/system/users").and("not.include", "/new");

        // createUser() assigns a random password and sets NeedPasswordChange.
        // adminSetUserPassword() gives us a password we know and clears the
        // must-change flag, so the user can log in normally.
        cy.visit(`admin/system/user/${PERSON_ID}/changePassword`);
        cy.get("#NewPassword1").type(FIRST_PASSWORD);
        cy.get("#NewPassword2").type(FIRST_PASSWORD);
        cy.get("#NewPassword1").closest("form").submit();
        cy.contains("Password Change Successful");
    });

    after(() => {
        cy.setupAdminSession({ forceLogin: true });
        deleteTestUser();
    });

    it("Change then back", () => {
        cy.visit("/session/end");
        cy.loginWithCredentials(loginName, FIRST_PASSWORD, "pw-round-trip-1");
        cy.visit("v2/user/current/changepassword");
        cy.get("#OldPassword").type(FIRST_PASSWORD);
        cy.get("#NewPassword1").type(SECOND_PASSWORD);
        cy.get("#NewPassword2").type(SECOND_PASSWORD);
        cy.get("#passwordChangeForm").submit();
        cy.url().should("contain", "/v2/user/current/changepassword");
        cy.contains("Password Change Successful");

        cy.visit("/session/end");
        cy.loginWithCredentials(loginName, SECOND_PASSWORD, "pw-round-trip-2");
        cy.visit("v2/user/current/changepassword");
        cy.get("#OldPassword").type(SECOND_PASSWORD);
        cy.get("#NewPassword1").type(FIRST_PASSWORD);
        cy.get("#NewPassword2").type(FIRST_PASSWORD);
        cy.get("#passwordChangeForm").submit();
        cy.url().should("contain", "/v2/user/current/changepassword");
        cy.contains("Password Change Successful");
    });
});
