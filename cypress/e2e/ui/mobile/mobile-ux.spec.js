/// <reference types="cypress" />

/**
 * Mobile UX Regression Tests
 *
 * Verifies that the mobile UX cleanup for logged-out pages, family
 * registration, dashboards, profile views, and editors holds up on a
 * 375×812 (iPhone X) viewport — no horizontal scroll, form fields stack,
 * buttons wrap, touch targets are large enough.
 */

const MOBILE_VIEWPORT = [375, 812];

/** Assert the document has no horizontal overflow at the current viewport. */
function assertNoHorizontalOverflow() {
    cy.document().then((doc) => {
        const scrollWidth = doc.documentElement.scrollWidth;
        const clientWidth = doc.documentElement.clientWidth;
        // Allow 2px tolerance for rounding / scrollbar
        expect(scrollWidth, "document scrollWidth").to.be.lessThan(clientWidth + 3);
    });
}

describe("Mobile UX — Logged-Out Pages", () => {
    beforeEach(() => {
        cy.viewport(...MOBILE_VIEWPORT);
    });

    it("login page fits mobile viewport without horizontal scroll", () => {
        cy.visit("/session/begin");
        cy.get("body").should("have.class", "page-auth");
        cy.get(".login-container").should("be.visible");
        cy.get("input[name='User']").should("be.visible");
        cy.get("input[name='Password']").should("be.visible");
        cy.get(".btn-sign-in").should("be.visible");
        assertNoHorizontalOverflow();
    });

    it("login inputs use 16px font to prevent iOS auto-zoom", () => {
        cy.visit("/session/begin");
        cy.get("input[name='User']").then(($el) => {
            const fontSize = parseFloat($el.css("font-size"));
            expect(fontSize, "input font-size").to.be.gte(16);
        });
    });

    it("auth footer social icons meet 44px minimum touch target", () => {
        cy.visit("/session/begin");
        cy.get(".auth-footer-social a").first().then(($el) => {
            const width = parseFloat($el.css("width"));
            const height = parseFloat($el.css("height"));
            expect(width, "social link width").to.be.gte(44);
            expect(height, "social link height").to.be.gte(44);
        });
    });

    it("password reset page fits mobile viewport", () => {
        cy.visit("/session/forgot-password/reset-request");
        cy.get(".forgot-password-card").should("be.visible");
        cy.get("input[name='username']").should("be.visible");
        cy.get(".btn-reset").should("be.visible");
        assertNoHorizontalOverflow();
    });

    it("password reset error page fits mobile viewport", () => {
        cy.visit("/session/forgot-password/set/invalid-token-mobile-test");
        cy.get(".alert.alert-danger").should("be.visible");
        cy.get(".alert-buttons a, .alert-buttons button")
            .should("have.length.at.least", 1);
        assertNoHorizontalOverflow();
    });
});

describe("Mobile UX — Family Registration", () => {
    beforeEach(() => {
        cy.viewport(...MOBILE_VIEWPORT);
    });

    it("family register page fits mobile viewport and fields stack", () => {
        cy.visit("/external/register/");
        cy.get("#registration-stepper").should("be.visible");
        cy.get("#familyName").should("be.visible");
        cy.get("#familyAddress1").should("be.visible");
        assertNoHorizontalOverflow();
    });

    it("family register form inputs use 16px font (iOS zoom prevention)", () => {
        cy.visit("/external/register/");
        cy.get("#familyName").then(($el) => {
            const fontSize = parseFloat($el.css("font-size"));
            expect(fontSize, "input font-size").to.be.gte(16);
        });
    });

    it("family register step nav buttons take full width on mobile", () => {
        cy.visit("/external/register/");
        cy.get("#family-info-next").then(($btn) => {
            const btnWidth = $btn.outerWidth();
            const parentWidth = $btn.parent().innerWidth();
            // Stacked buttons should be at least ~90% of parent width
            expect(btnWidth / parentWidth, "button width ratio").to.be.gte(0.9);
        });
    });
});

describe("Mobile UX — Authenticated Pages", () => {
    beforeEach(() => {
        cy.viewport(...MOBILE_VIEWPORT);
        cy.setupStandardSession();
    });

    it("root dashboard fits mobile viewport without horizontal scroll", () => {
        cy.visit("/v2/dashboard");
        cy.get(".page-body").should("be.visible");
        assertNoHorizontalOverflow();
    });

    it("root dashboard member tables are wrapped in table-responsive", () => {
        cy.visit("/v2/dashboard");
        cy.get("#latestFamiliesDashboardItem")
            .parents(".table-responsive")
            .should("exist");
        cy.get("#PersonBirthdayDashboardItem")
            .parents(".table-responsive")
            .should("exist");
    });

    it("groups dashboard fits mobile viewport", () => {
        cy.visit("/v2/groups");
        cy.get("#groupsTable")
            .parents(".table-responsive")
            .should("exist");
        assertNoHorizontalOverflow();
    });

    it("family view stacks columns on mobile", () => {
        cy.visit("/v2/family/1");
        // Both main and sidebar columns should be visible (stacked on mobile)
        cy.get(".col-12.col-lg-8").should("be.visible");
        cy.get(".col-12.col-lg-4").should("be.visible");
        assertNoHorizontalOverflow();
    });

    it("PersonEditor form fields stack full-width on mobile", () => {
        cy.visit("/PersonEditor.php");
        cy.get("#FirstName").should("be.visible").then(($el) => {
            // First name field should take most of the viewport width (col-12 on mobile)
            const fieldWidth = $el.closest(".mb-3").outerWidth();
            const containerWidth = $el.closest(".row").innerWidth();
            expect(fieldWidth / containerWidth, "first name column ratio")
                .to.be.gte(0.9);
        });
        assertNoHorizontalOverflow();
    });

    it("FamilyEditor form fields stack full-width on mobile", () => {
        cy.visit("/FamilyEditor.php");
        cy.get("#FamilyName").should("be.visible").then(($el) => {
            const fieldWidth = $el.closest(".mb-3").outerWidth();
            const containerWidth = $el.closest(".row").innerWidth();
            expect(fieldWidth / containerWidth, "family name column ratio")
                .to.be.gte(0.9);
        });
        assertNoHorizontalOverflow();
    });
});
