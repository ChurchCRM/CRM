/// <reference types="cypress" />

// Copy-to-clipboard consolidation (#11458): the shared helper behind the
// debug-page copy buttons, the email composer's Copy Addresses, the calendar
// properties modal, and 2FA recovery-code copy.

function stubClipboard(win, writeText) {
    Object.defineProperty(win.navigator, "clipboard", {
        configurable: true,
        value: { writeText },
    });
}

const addToCart = (personIds) =>
    cy.request({
        method: "POST",
        url: "/api/cart/",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({ Persons: personIds }),
        failOnStatusCode: false,
    }).then((resp) => expect(resp.status).to.equal(200));

const emptyCart = () =>
    cy.request({
        method: "DELETE",
        url: "/api/cart/",
        headers: { "content-type": "application/json" },
        body: {},
        failOnStatusCode: false,
    });

const FIRST_KEY = "JBSWY3DPEHPK3PXP";
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

describe("Copy to clipboard", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("copies a debug value and shows the success toast", () => {
        cy.visit("admin/system/debug#collapseEnvironment");
        cy.get("#env-app .copy-btn")
            .first()
            .invoke("attr", "data-copy")
            .then((expected) => {
                cy.window().then((win) => {
                    stubClipboard(win, cy.stub().resolves().as("writeText"));
                });
                cy.get("#env-app .copy-btn").first().click();
                cy.get("@writeText").should("have.been.calledOnceWithExactly", expected);
                cy.get("#crm-toasts").should("contain", "Copied to clipboard");
            });
    });

    it("falls back to execCommand when the Clipboard API refuses", () => {
        cy.visit("admin/system/debug#collapseEnvironment");
        cy.window().then((win) => {
            stubClipboard(win, cy.stub().rejects(new Error("denied")).as("writeText"));
            cy.stub(win.document, "execCommand").returns(true).as("execCopy");
        });
        cy.get("#env-app .copy-btn").first().click();
        cy.get("@execCopy").should("have.been.calledOnceWithExactly", "copy");
        cy.get("#crm-toasts").should("contain", "Copied to clipboard");
        cy.get(".bootbox").should("not.exist");
    });

    it("shows the manual-copy dialog when every copy mechanism fails", () => {
        cy.visit("admin/system/debug#collapseEnvironment");
        cy.get("#env-app .copy-btn")
            .first()
            .invoke("attr", "data-copy")
            .then((expected) => {
                cy.window().then((win) => {
                    stubClipboard(win, cy.stub().rejects(new Error("denied")).as("writeText"));
                    cy.stub(win.document, "execCommand").returns(false);
                });
                cy.get("#env-app .copy-btn").first().click();
                cy.get(".bootbox textarea").should("be.visible").and("have.value", expected);
                cy.focused().should("have.prop", "tagName", "TEXTAREA");
                cy.get(".bootbox textarea").then((el) => {
                    const textarea = el[0];
                    expect(textarea.selectionStart).to.equal(0);
                    expect(textarea.selectionEnd).to.equal(textarea.value.length);
                });
                cy.get(".bootbox .btn-primary").click();
                cy.get(".bootbox").should("not.exist");
            });
    });

    it("copies the cart recipients from the email composer", () => {
        emptyCart();
        addToCart([2]);
        cy.visit("/people/cart");
        cy.intercept("GET", "**/api/cart/emails").as("cartEmailsApi");
        cy.get("[data-email-composer][data-email-endpoint='cart/emails']").click();
        cy.wait("@cartEmailsApi").then((interception) => {
            const emails = interception.response.body.emails ?? [];
            expect(emails.length, "seed cart has an emailed person").to.be.at.least(1);
            cy.get("#crm-email-composer-modal").should("be.visible");
            cy.get("#crm-email-copy-btn").should("not.be.disabled");
            cy.window().then((win) => {
                stubClipboard(win, cy.stub().resolves().as("writeText"));
            });
            cy.get("#crm-email-copy-btn").click();
            cy.get("@writeText").should("have.been.calledOnceWithExactly", emails.join(", "));
            cy.get("#crm-email-copy-btn").should("contain", "Copied!");
        });
        emptyCart();
    });

    it("copies a calendar ICS URL from the properties modal", () => {
        cy.visit("/event/calendars");
        cy.window().its("calendarPropertiesModal.getBootboxContent").should("be.a", "function");
        cy.window().then((win) => {
            stubClipboard(win, cy.stub().resolves().as("writeText"));
            const calendar = {
                AccessToken: "cypress-fake-token",
                MinistryId: 1,
                ForegroundColor: "FFFFFF",
                BackgroundColor: "000000",
            };
            win.$("body").append(win.calendarPropertiesModal.getBootboxContent(calendar));
        });
        // The modal wires its copy buttons in a 50ms setTimeout.
        cy.wait(150);
        cy.get('input[value$="/ics"]')
            .invoke("val")
            .then((icsUrl) => {
                cy.get(`.copy-url-btn[data-url="${icsUrl}"]`).click();
                cy.get("@writeText").should("have.been.calledOnceWithExactly", icsUrl);
                cy.get(`.copy-url-btn[data-url="${icsUrl}"] i`).should("have.class", "fa-check");
            });
    });

    it("copies the 2FA recovery codes after enrollment", () => {
        visitEnrollment();
        cy.intercept("POST", "**/api/user/current/test2FAEnrollmentCode", { IsEnrollmentCodeValid: true });
        cy.intercept("POST", "**/api/user/current/refresh2farecoverycodes", {
            TwoFARecoveryCodes: ["AAAA-1111", "BBBB-2222"],
        });
        cy.get("#totp-input").type("123456");
        cy.get("#copyCodesBtn").should("be.visible");
        cy.window().then((win) => {
            stubClipboard(win, cy.stub().resolves().as("writeText"));
        });
        cy.get("#copyCodesBtn").click();
        cy.get("@writeText").should("have.been.calledOnceWithExactly", "01. AAAA-1111\n02. BBBB-2222");
        cy.get("#copyCodesBtn").should("contain", "Copied!");
    });
});
