/// <reference types="cypress" />

import { contrastRatio, effectiveBackground, relativeLuminance } from "../../support/colour";

const STYLE_URL = "/api/user/1/setting/ui.style";
const RECOVERY_CODE = "0a1b2c3d-4e5f6a7b";

function expectReadableInDarkMode($el, label) {
    const el = $el[0];
    const win = el.ownerDocument.defaultView;
    const text = win.getComputedStyle(el).color;
    const background = effectiveBackground(el);
    expect(text, `${label} text colour`).to.eq(win.getComputedStyle(win.document.body).color);
    expect(relativeLuminance(background), `${label} background luminance`).to.be.below(0.1);
    expect(contrastRatio(text, background), `${label} contrast`).to.be.at.least(4.5);
}

describe("Signed-in pages stay readable in dark mode (#10593)", () => {
    let savedStyle;

    before(() => {
        cy.setupAdminSession();
        cy.request(STYLE_URL).then(({ body }) => {
            savedStyle = body.value;
        });
    });

    beforeEach(() => {
        cy.setupAdminSession();
        cy.request("POST", STYLE_URL, { value: "dark" });
    });

    after(() => {
        cy.setupAdminSession();
        cy.request("POST", STYLE_URL, { value: savedStyle });
    });

    it("draws the deposit slip Funds header and payment count in the body colour on a dark surface", () => {
        cy.visit("/DepositSlipEditor.php?DepositSlipID=5");
        cy.get("html").should("have.attr", "data-bs-theme", "dark");
        cy.contains(".card-header", "Funds").should(($el) => expectReadableInDarkMode($el, "Funds header"));
        cy.contains(".stat-label", "Total Payments")
            .siblings(".stat-value")
            .should(($el) => expectReadableInDarkMode($el, "Total Payments count"));
    });

    it("draws the People Dashboard gender Total row on a dark surface", () => {
        cy.visit("/people/dashboard");
        cy.get("html").should("have.attr", "data-bs-theme", "dark");
        cy.contains(".card", "Gender Demographics")
            .contains("tr", "Total")
            .find("td")
            .first()
            .should(($el) => expectReadableInDarkMode($el, "Total row"));
    });

    it("draws the Localization preview on a dark surface and keeps it light in light mode", () => {
        cy.visit("/admin/system/localization");
        cy.get("html").should("have.attr", "data-bs-theme", "dark");
        cy.get("#locale-preview-name").should(($el) => expectReadableInDarkMode($el, "Selected Language preview"));

        cy.window().its("CRM.theme").invoke("setMode", "default");
        cy.get("html").should("not.have.attr", "data-bs-theme");
        cy.get("#locale-preview").should(($el) => {
            expect(relativeLuminance(effectiveBackground($el[0])), "light-mode preview background").to.be.above(0.9);
        });
    });

    it("draws the two-factor recovery codes box on a dark surface", () => {
        cy.intercept("GET", "**/api/user/current/2fa-status", { IsEnabled: false });
        cy.intercept("POST", "**/api/user/current/refresh2fasecret", { TwoFAQRCodeDataUri: "" });
        cy.intercept("POST", "**/api/user/current/test2FAEnrollmentCode", { IsEnrollmentCodeValid: true });
        cy.intercept("POST", "**/api/user/current/refresh2farecoverycodes", { TwoFARecoveryCodes: [RECOVERY_CODE] });

        cy.visit("/v2/user/current/manage2fa");
        cy.get("html").should("have.attr", "data-bs-theme", "dark");
        cy.get("#begin2faEnrollment").click();
        cy.get("#totp-input").type("123456");
        cy.contains("code", RECOVERY_CODE)
            .parent()
            .parent()
            .should(($el) => expectReadableInDarkMode($el, "Recovery codes box"));
    });
});
