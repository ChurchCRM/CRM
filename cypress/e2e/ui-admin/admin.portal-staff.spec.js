/// <reference types="cypress" />

/**
 * Member Portal (MP2, #9863) — staff in the portal.
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §2.3 / P10.
 *   - a staff login still lands on /v2/dashboard
 *   - the admin user menu offers "Member Portal"
 *   - inside the portal a staff session sees the fixed "viewing as yourself"
 *     bar, whose exit control returns to the admin dashboard
 */
describe("Member Portal — staff access", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("An administrator still lands on the admin dashboard", () => {
        cy.visit("/");
        cy.url({ timeout: 10000 }).should("include", "/v2/dashboard");
        cy.url().should("not.include", "/portal");
    });

    // Scoped to the user menu's dropdown: since #9864 the admin sidebar also
    // carries a "Member Portal" entry (Admin → Member Portal), so matching on
    // the label alone would find that collapsed sidebar link instead.
    const userMenuPortalLink = () => cy.get('.dropdown-menu a.dropdown-item[href$="/portal/"]');

    it("The user menu offers a Member Portal entry", () => {
        cy.visit("/v2/dashboard");
        cy.get('[aria-label="Open user menu"]').click();
        userMenuPortalLink().should("be.visible").and("contain.text", "Member Portal");
    });

    it("The Member Portal entry opens the portal", () => {
        cy.visit("/v2/dashboard");
        cy.get('[aria-label="Open user menu"]').click();
        userMenuPortalLink().click();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.get(".portal-home").should("exist");
    });

    it("Staff see the 'viewing as yourself' bar and no admin sidebar", () => {
        cy.visit("/portal/");
        cy.get(".portal-staff-bar", { timeout: 10000 }).should("be.visible");
        cy.contains("You are viewing the Member Portal as yourself.").should("exist");
        cy.get("#sidebar").should("not.exist");
    });

    it("The exit control returns to the admin dashboard", () => {
        cy.visit("/portal/");
        cy.get('.portal-staff-bar [aria-label="Exit to the admin area"]', { timeout: 10000 }).click();
        cy.url({ timeout: 10000 }).should("include", "/v2/dashboard");
    });
});

/**
 * Member Portal — a portal account with no family record.
 *
 * The default administrator is the seed's own case: user 1 resolves to person 1
 * ("Church Admin"), whose `per_fam_ID` is 0. `/portal/family` therefore has no
 * family to show, and it used to answer the portal's 404 page — "This page was
 * not found" — which reads like a broken link rather than a fact about the
 * member's record. It is now a normal portal page that names the situation and
 * hands the member the church office's contact details.
 *
 * Seed values (`config_cfg`): `sChurchEmail` = demo@churchcrm.io and
 * `sChurchPhone` = "555 123 4234", so the two contact lines are present without
 * the spec having to set them; the last test blanks both to cover the fallback
 * and restores them afterwards.
 */
describe("Member Portal — a member with no family", () => {
    const CHURCH_EMAIL = "demo@churchcrm.io";
    const CHURCH_PHONE = "555 123 4234";
    const CHURCH_PHONE_HREF = "tel:5551234234";

    const TITLE = "You are not currently associated with a family";
    const BODY = "Please contact the church office so we may correct our records.";

    // Every x-api-key request replaces the browser's session cookie, so a UI
    // step after one has to re-establish the session (cy.setupAdminSession()).
    const setConfig = (name, value) =>
        cy.request({
            method: "POST",
            url: `/admin/api/system/config/${name}`,
            headers: { "content-type": "application/json", "x-api-key": Cypress.env("admin.api.key") },
            body: { value },
            failOnStatusCode: false,
        });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("answers /portal/family with a page, not a 404", () => {
        cy.request("/portal/family").its("status").should("eq", 200);
    });

    it("says so on the page instead of showing the portal's 404", () => {
        cy.visit("/portal/family");
        cy.get("#portal-family-none", { timeout: 10000 }).should("exist");
        cy.contains(TITLE).should("be.visible");
        cy.contains(BODY).should("be.visible");
        cy.get(".portal-error-404").should("not.exist");
        cy.contains("This page was not found").should("not.exist");
    });

    it("offers the church email as a mailto: link and the phone as a tel: link", () => {
        cy.visit("/portal/family");
        cy.get("#portal-family-none-email", { timeout: 10000 })
            .should("have.attr", "href", `mailto:${CHURCH_EMAIL}`)
            .and("contain.text", CHURCH_EMAIL);
        // The href strips spaces and punctuation; the number is still displayed
        // exactly as the church configured it.
        cy.get("#portal-family-none-phone")
            .should("have.attr", "href", CHURCH_PHONE_HREF)
            .and("contain.text", CHURCH_PHONE);
    });

    it("keeps My Family as the active navigation entry", () => {
        cy.visit("/portal/family");
        cy.get('.portal-nav-link.is-active[aria-current="page"]', { timeout: 10000 }).should(
            "contain.text",
            "My Family"
        );
    });

    it("shows the same page for /portal/family/edit and /portal/family/confirm", () => {
        for (const path of ["/portal/family/edit", "/portal/family/confirm"]) {
            cy.visit(path);
            cy.get("#portal-family-none", { timeout: 10000 }).should("exist");
            cy.contains(TITLE).should("be.visible");
        }
    });

    it("still answers 404 for a genuinely unknown portal URL", () => {
        cy.request({ url: "/portal/no-such-page", failOnStatusCode: false })
            .its("status")
            .should("eq", 404);
        cy.visit("/portal/no-such-page", { failOnStatusCode: false });
        cy.contains("This page was not found").should("exist");
    });

    it("links to /portal/family from the home page's family card", () => {
        cy.visit("/portal/");
        cy.get("#portal-home-family-card", { timeout: 10000 })
            .should("contain.text", TITLE)
            .find('a[href$="/portal/family"]')
            .should("exist");
    });

    describe("when the church has configured neither an email nor a phone", () => {
        before(() => {
            cy.setupAdminSession();
            setConfig("sChurchEmail", "");
            setConfig("sChurchPhone", "");
        });

        after(() => {
            cy.setupAdminSession();
            setConfig("sChurchEmail", CHURCH_EMAIL);
            setConfig("sChurchPhone", CHURCH_PHONE);
        });

        it("falls back to the plain sentence and offers no links", () => {
            cy.visit("/portal/family");
            cy.get("#portal-family-none", { timeout: 10000 }).should("exist");
            cy.contains("Please contact the church office.").should("be.visible");
            cy.get("#portal-family-none-email").should("not.exist");
            cy.get("#portal-family-none-phone").should("not.exist");
        });
    });
});
