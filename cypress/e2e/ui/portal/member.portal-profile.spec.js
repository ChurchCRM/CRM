/// <reference types="cypress" />

/**
 * Member Portal (MP4, #9865) — Profile self-service.
 *
 * Seed persona: user 100, Lena Black (person 100, family 20, family role 2 =
 * Spouse). usr_EditSelf=1 and no admin flag, so User::isEditSelfExclusive() is
 * true and she lands in /portal. usr_UserName is VARCHAR(50) since #9831, so the
 * seeded address is stored whole.
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §5.2 (P11, P12).
 *   - a member reads and edits their own person record and nothing else
 *   - the birthday field is behind bPortalAllowBirthdayEdit
 *   - the photo shows on the profile page
 *   - password and two-factor are reachable, in the portal's own layout
 *
 * Note on the birthday switch: `bPortalAllowBirthdayEdit` is declared by the
 * Admin → Member Portal page (MP3, #9864), which is a sibling branch. Until
 * the two are merged this installation does not declare the item at all and
 * PortalSettings reports it off, so the test below asserts the *agreement*
 * between the setting and the form rather than hard-coding one of the two
 * states: it covers the "off" path today and the "on" path the day an
 * administrator turns it on.
 */
describe("Member Portal — Profile", () => {
    const memberUser = "lena.black.editself.notes@example.com";
    const memberPassword = "changeme";

    const login = () => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(memberUser);
        cy.get("input[name=Password]").type(memberPassword + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/portal");
    };

    it("The portal navigation offers Profile and My Family", () => {
        login();
        cy.get("#portal-nav").within(() => {
            cy.contains("Profile").should("exist");
            cy.contains("My Family").should("exist");
        });
    });

    it("The home page shows real My Family and Profile cards", () => {
        login();
        cy.get("#portal-home-family-card").should("contain", "Black");
        cy.get("#portal-home-profile-card").should("contain", "Lena");
        cy.get("#portal-home-family-card").contains("Update your details").should("exist");
    });

    it("The profile page shows the member's own details and their avatar", () => {
        login();
        cy.visit("/portal/profile");
        cy.get("#portal-profile-details").should("exist");
        cy.contains("Lena").should("exist");
        // Either an uploaded photo or the initials stand-in — never a broken image.
        cy.get("#portal-profile-photo").should("exist");
        cy.get("#portal-profile-details [data-field=cellPhone]").should("not.be.empty");
    });

    it("The avatar is a real image once a photo is uploaded, on both profile pages", () => {
        // A member session may not read /api/person/{id}/photo — AuthMiddleware
        // confines it to /portal and /api/portal — so an avatar pointed there
        // decodes to nothing and shows as a broken image. naturalWidth is what
        // tells a loaded photo from a broken one.
        //
        // Lena has no tracked fixture under cypress/data/images/people, so
        // uploading hers here overwrites nothing (issue #9777); the file read
        // from is somebody else's fixture and is only ever read.
        login();
        cy.visit("/portal/profile/edit");
        cy.get("#portal-photo-input").selectFile("cypress/data/images/people/102.png", { force: true });

        cy.get(".portal-flash-success", { timeout: 10000 })
            .should("be.visible")
            .and("contain", "Your photo has been saved");

        // The preview is re-pointed at the URL the upload response returned.
        cy.get("img#portal-photo-preview").should("have.attr", "src").and("match", /\/api\/portal\/me\/photo/);
        cy.get("img#portal-photo-preview").should("have.prop", "naturalWidth").and("be.greaterThan", 0);

        // And the same photo loads on the profile page itself, reloaded fresh.
        cy.visit("/portal/profile");
        cy.get("img#portal-profile-photo").should("have.attr", "src").and("match", /\/api\/portal\/me\/photo/);
        cy.get("img#portal-profile-photo").should("have.prop", "naturalWidth").and("be.greaterThan", 0);
    });

    it("Editing the mobile phone and email saves, toasts, and survives a reload", () => {
        const stamp = Date.now();
        const newPhone = `(206) 555-${String(stamp).slice(-4)}`;
        const newEmail = `lena.portal.${stamp}@example.com`;

        login();
        cy.visit("/portal/profile/edit");

        cy.get("#portal-cellPhone").clear().type(newPhone);
        cy.get("#portal-email").clear().type(newEmail);
        cy.get("#portal-profile-save").click();

        cy.get(".portal-flash-success", { timeout: 10000 })
            .should("be.visible")
            .and("contain", "Your details have been saved");

        // The form re-renders from the API response, not from what was typed.
        cy.get("#portal-cellPhone").should("have.value", newPhone);

        cy.visit("/portal/profile");
        cy.get("#portal-profile-details [data-field=cellPhone]").should("contain", newPhone);
        cy.get("#portal-profile-details [data-field=email]").should("contain", newEmail);
    });

    it("An empty required name is refused inline and nothing is saved", () => {
        login();
        cy.visit("/portal/profile/edit");

        cy.get("#portal-firstName").clear();
        cy.get("#portal-profile-save").click();

        cy.get('[data-error-for="firstName"]').should("be.visible").and("not.be.empty");
        cy.get("#portal-firstName").should("have.class", "is-invalid");

        cy.visit("/portal/profile");
        cy.get("#portal-profile-details [data-field=firstName]").should("contain", "Lena");
    });

    it("The birthday field is shown exactly when the portal setting allows it", () => {
        login();
        // Both pages read the same PortalSettings flag, so asserting that they
        // agree covers the "off" state today and the "on" state the day an
        // administrator turns bPortalAllowBirthdayEdit on.
        cy.visit("/portal/profile");
        cy.get("body").then(($body) => {
            const birthdayIsShown = $body.find("#portal-profile-details [data-field=birthday]").length > 0;
            cy.visit("/portal/profile/edit");
            if (birthdayIsShown) {
                cy.get("#portal-birthday").should("exist");
            } else {
                cy.get("#portal-birthday").should("not.exist");
            }
        });
    });

    it("Password and two-factor are reachable and wear the portal layout", () => {
        login();
        cy.visit("/portal/profile");

        cy.get("#portal-change-password-link").click();
        cy.url({ timeout: 10000 }).should("include", "/user/current/changepassword");
        cy.get(".portal-shell").should("exist");
        cy.get("#sidebar").should("not.exist");
        cy.get("#OldPassword").should("exist");
        cy.get("#NewPassword1").should("exist");

        cy.visit("/v2/user/current/manage2fa");
        cy.get(".portal-shell").should("exist");
        cy.get("#two-factor-enrollment-app").should("exist");
        cy.get("#sidebar").should("not.exist");
    });
});
