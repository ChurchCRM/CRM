/// <reference types="cypress" />

/**
 * Member Portal — change-password page strength meter (#11816 / PR #11817).
 *
 * The portal change-password page is at /portal/profile/password and is
 * accessible to staff sessions (admin and standard user with a CRM login).
 * Members who sign in only via the email-code flow get a 404 from
 * PortalAccountPages::renderPasswordForm, because they have no password to
 * change. The spec therefore drives the page as an administrator
 * (cy.setupAdminSession), which is the same approach used in
 * cypress/e2e/ui-admin/admin.portal-staff.spec.js.
 *
 * Strength-meter contract (PasswordChange.js / UpdatePasswordStrength):
 *   - container hidden when #NewPassword1 is empty
 *   - container visible once any character is typed
 *   - #PasswordStrengthBar[aria-valuenow] = level * 25  (25/50/75/100)
 *   - #PasswordStrengthLabel text = Weak / Fair / Good / Strong
 *   - data-min-length on #PasswordStrengthContainer comes from minPasswordLength
 *   - a password shorter than the minimum is capped at Weak (level 1 / 25)
 *     regardless of its character-class mix
 */

describe("Member Portal — change-password strength meter", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("/portal/profile/password");
    });

    it("renders the change-password form in the portal shell", () => {
        cy.get(".portal-shell").should("exist");
        cy.get("#passwordChangeForm").should("exist");
        cy.get("#NewPassword1").should("exist");
    });

    it("hides the strength container until the user starts typing", () => {
        cy.get("#PasswordStrengthContainer").should("not.be.visible");
        cy.get("#NewPassword1").type("a");
        cy.get("#PasswordStrengthContainer").should("be.visible");
    });

    it("shows Weak for a short password", () => {
        cy.get("#NewPassword1").type("abc");
        cy.get("#PasswordStrengthContainer").should("be.visible");
        cy.get("#PasswordStrengthLabel").should("have.text", "Weak");
        cy.get("#PasswordStrengthBar").should("have.attr", "aria-valuenow", "25");
        cy.get("#PasswordStrengthHint").should("not.be.empty");
    });

    it("caps at Weak when all character classes are present but length is below the minimum", () => {
        // 7 chars, all character classes — still below the default minimum of 8
        cy.get("#NewPassword1").type("Aa1!567");
        cy.get("#PasswordStrengthLabel").should("have.text", "Weak");
        cy.get("#PasswordStrengthBar").should("have.attr", "aria-valuenow", "25");
    });

    it("rates a strong password as Strong", () => {
        cy.get("#NewPassword1").type("P@ssw0rd!2026");
        cy.get("#PasswordStrengthLabel").should("have.text", "Strong");
        cy.get("#PasswordStrengthBar").should("have.attr", "aria-valuenow", "100");
        cy.get("#PasswordStrengthHint").should("not.be.empty");
    });

    it("hides the container again when the field is cleared", () => {
        cy.get("#NewPassword1").type("abc");
        cy.get("#PasswordStrengthContainer").should("be.visible");
        cy.get("#NewPassword1").clear();
        cy.get("#PasswordStrengthContainer").should("not.be.visible");
    });

    it("exposes data-min-length on the container", () => {
        // The attribute must be a positive integer; the exact value depends on
        // iMinPasswordLength (default 8). Assert it is present and numeric.
        cy.get("#PasswordStrengthContainer")
            .invoke("attr", "data-min-length")
            .then((raw) => {
                const value = Number.parseInt(raw, 10);
                expect(Number.isInteger(value) && value > 0, `data-min-length "${raw}" is a positive integer`).to.be.true;
            });
    });
});

describe("Member Portal — change-password strength meter respects iMinPasswordLength", () => {
    let originalMinPasswordLength;

    before(() => {
        cy.getSystemConfig("iMinPasswordLength").then((value) => {
            originalMinPasswordLength = value;
        });
    });

    after(() => {
        cy.restoreSystemConfig("iMinPasswordLength", originalMinPasswordLength);
    });

    it("reflects a raised minimum in data-min-length and caps ratings below it at Weak", () => {
        cy.setSystemConfig("iMinPasswordLength", 12);
        cy.setupAdminSession();
        cy.visit("/portal/profile/password");

        cy.get("#PasswordStrengthContainer").should("have.attr", "data-min-length", "12");

        // 10 chars, all character classes — below the raised minimum (12) → Weak
        cy.get("#NewPassword1").type("P@ssw0rd!2");
        cy.get("#PasswordStrengthContainer").should("be.visible");
        cy.get("#PasswordStrengthLabel").should("have.text", "Weak");
        cy.get("#PasswordStrengthBar").should("have.attr", "aria-valuenow", "25");

        // 12 chars, mixed case + digit, no symbol — hits first length point but not second → Good
        cy.get("#NewPassword1").clear().type("Abcdefghijk1");
        cy.get("#PasswordStrengthLabel").should("have.text", "Good");
        cy.get("#PasswordStrengthBar").should("have.attr", "aria-valuenow", "75");

        // 16 chars (12 + 4), mixed case + digit, no symbol — hits both length points → Strong
        cy.get("#NewPassword1").clear().type("Abcdefghijk12345");
        cy.get("#PasswordStrengthLabel").should("have.text", "Strong");
        cy.get("#PasswordStrengthBar").should("have.attr", "aria-valuenow", "100");
    });
});
