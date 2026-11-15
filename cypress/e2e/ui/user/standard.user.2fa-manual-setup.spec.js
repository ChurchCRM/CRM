/// <reference types="cypress" />

// API responses are intercepted so no real user's 2FA enrollment state is modified.
const FIRST_KEY = "JBSWY3DPEHPK3PXP";
const SECOND_KEY = "KRUGS4ZANFZSAYJA";
const qr = "data:image/png;base64,iVBORw0KGgo=";

function visitEnrollment() {
    cy.intercept("GET", "**/api/user/current/2fa-status", { IsEnabled: false });
    cy.intercept("POST", "**/api/user/current/refresh2fasecret", {
        statusCode: 200,
        headers: { "cache-control": "no-store, private" },
        body: { TwoFAQRCodeDataUri: qr, TwoFASecret: FIRST_KEY },
    }).as("newSecret");
    cy.visit("/account/2fa");
    cy.get("#begin2faEnrollment").click();
    cy.wait("@newSecret");
}

describe("2FA manual setup key", () => {
    beforeEach(() => cy.setupStandardSession());

    it("renders translated labels after a direct hard reload", () => {
        cy.intercept("GET", "**/api/user/current/2fa-status", { IsEnabled: false });
        cy.visit("/account/2fa");
        cy.get("#begin2faEnrollment").should("be.visible");
        cy.reload();
        cy.get("#begin2faEnrollment", { timeout: 10000 }).should("be.visible");
        cy.get("#two-factor-enrollment-app").should("not.contain", "undefined");
        cy.window().its("CRM.localesLoaded").should("eq", true);
    });

    it("falls back to readable labels when i18next exists but has not initialized", () => {
        visitEnrollment();
        cy.window().then((win) => cy.stub(win.i18next, "t").returns(undefined));
        cy.get("#showManual2FAKeyBtn").click();
        cy.get("#two-factor-enrollment-app").should("not.contain", "undefined");
        cy.get("#showManual2FAKeyBtn").should("contain", "Hide setup key");
    });

    it("keeps the Base32 key hidden until requested, then allows it to be hidden again", () => {
        visitEnrollment();
        cy.get("#2faQrCodeDataUri").should("have.attr", "src", qr);
        cy.get("#manual2FAKeyPanel").should("not.be.visible");
        cy.get("#manual2FAKey").should("have.value", "");
        cy.get("#showManual2FAKeyBtn").should("have.attr", "aria-expanded", "false").click();
        cy.get("#manual2FAKeyPanel").should("be.visible");
        cy.get("#manual2FAKey").should("have.value", FIRST_KEY).and("have.attr", "readonly");
        cy.get("#showManual2FAKeyBtn").should("have.attr", "aria-expanded", "true").click();
        cy.get("#manual2FAKeyPanel").should("not.be.visible");
        cy.get("#manual2FAKey").should("have.value", "");
    });

    it("copies the exact key when the Clipboard API is available", () => {
        visitEnrollment();
        cy.get("#showManual2FAKeyBtn").click();
        cy.window().then((win) => {
            const writeText = cy.stub().resolves().as("writeKey");
            Object.defineProperty(win.navigator, "clipboard", {
                configurable: true,
                value: { writeText },
            });
        });
        cy.get("#copyManual2FAKeyBtn").click();
        cy.get("@writeKey").should("have.been.calledOnceWithExactly", FIRST_KEY);
    });

    it("clears the revealed key when a new QR code is generated", () => {
        visitEnrollment();
        cy.get("#showManual2FAKeyBtn").click();
        cy.get("#manual2FAKey").should("have.value", FIRST_KEY);
        cy.intercept("POST", "**/api/user/current/refresh2fasecret", {
            TwoFAQRCodeDataUri: qr,
            TwoFASecret: SECOND_KEY,
        }).as("regenerate");
        cy.get("#newQRCodeBtn").click();
        cy.wait("@regenerate");
        cy.get("#manual2FAKeyPanel").should("not.be.visible");
        cy.get("#manual2FAKey").should("have.value", "");
        cy.get("#showManual2FAKeyBtn").click();
        cy.get("#manual2FAKey").should("have.value", SECOND_KEY);
    });

    it("removes the key after completing enrollment", () => {
        visitEnrollment();
        cy.get("#showManual2FAKeyBtn").click();
        cy.intercept("POST", "**/api/user/current/test2FAEnrollmentCode", { IsEnrollmentCodeValid: true });
        cy.intercept("POST", "**/api/user/current/refresh2farecoverycodes", { TwoFARecoveryCodes: ["test-recovery"] });
        cy.get("#totp-input").type("123456");
        cy.get("#manual2FAKey").should("not.exist");
        cy.get("#two-factor-enrollment-app").should("not.contain", FIRST_KEY);
    });
});
