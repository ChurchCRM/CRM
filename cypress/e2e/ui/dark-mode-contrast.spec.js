/// <reference types="cypress" />

const STYLE_URL = "/api/user/1/setting/ui.style";
const RECOVERY_CODE = "0a1b2c3d-4e5f6a7b";

function parseColour(colour) {
    const srgb = colour.match(/^color\(srgb ([^)]+)\)$/);
    const body = srgb ? srgb[1] : colour.match(/^rgba?\(([^)]+)\)$/)[1];
    const parts = body.split(/[\s,/]+/).filter(Boolean).map(Number);
    const scale = srgb ? 255 : 1;
    return { rgb: parts.slice(0, 3).map((v) => v * scale), alpha: parts[3] ?? 1 };
}

function luminance({ rgb }) {
    const [r, g, b] = rgb.map((v) => {
        const c = v / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast(a, b) {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (hi + 0.05) / (lo + 0.05);
}

function backgroundBehind(el) {
    const win = el.ownerDocument.defaultView;
    for (let node = el; node; node = node.parentElement) {
        const bg = parseColour(win.getComputedStyle(node).backgroundColor);
        if (bg.alpha > 0.5) {
            return bg;
        }
    }
    return parseColour(win.getComputedStyle(el.ownerDocument.body).backgroundColor);
}

function expectReadableInDarkMode($el, label) {
    const el = $el[0];
    const win = el.ownerDocument.defaultView;
    const text = win.getComputedStyle(el).color;
    const background = backgroundBehind(el);
    expect(text, `${label} text colour`).to.eq(win.getComputedStyle(win.document.body).color);
    expect(luminance(background), `${label} background luminance`).to.be.below(0.1);
    expect(contrast(parseColour(text), background), `${label} contrast`).to.be.at.least(4.5);
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
            expect(luminance(backgroundBehind($el[0])), "light-mode preview background").to.be.above(0.9);
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
