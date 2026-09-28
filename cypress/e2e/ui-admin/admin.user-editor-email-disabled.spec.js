/// <reference types="cypress" />

/**
 * Regression test for the misleading create-user banner — issue #9696.
 *
 * With email disabled, UserService::createUser() generates a random password,
 * hashes it, and only reveals the plaintext inside the
 * `if (SystemConfig::isEmailEnabled())` branch. That branch never runs, so the
 * plaintext goes out of scope with no path back to it — yet the banner told the
 * admin to "Share the password with them manually", which is impossible. Every
 * account created while email was off was therefore unreachable, with the
 * banner pointing away from the one recovery path that exists
 * (System Users -> Change Password).
 *
 * The banner must not promise a password the software never surfaces.
 */
describe("Create-user banner with email disabled (#9696)", () => {
    before(() => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/bEnabledEmail", { value: "0" }, 200);
    });

    after(() => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/bEnabledEmail", { value: "1" }, 200);
    });

    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("/admin/system/users/new");
    });

    it("warns that email is disabled", () => {
        cy.contains("Email is disabled").should("exist");
    });

    it("does not tell the admin to share a password that is never shown", () => {
        cy.get("body")
            .invoke("text")
            .should("not.include", "Share the password with them manually");
    });

    it("points at Change Password, the path that actually works", () => {
        cy.get(".alert-warning")
            .invoke("text")
            .should("include", "Change Password");
    });

    it("still offers no password field on the create form", () => {
        // Nothing here should imply the admin can set a password inline; the
        // account is created first, then a password is set from System Users.
        cy.get("input[type=password], input[name*=assword]").should("have.length", 0);
    });
});
