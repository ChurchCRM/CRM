/// <reference types="cypress" />

/**
 * Regression tests for the People Dashboard email-recipient-list assembly
 * introduced in chore/remove-email-delimiter-user-settings (#9250).
 *
 * Covers:
 *  - Email All / Email BCC dropdowns render when person emails exist.
 *  - "All People" mailto: / mailto:?bcc= hrefs are well-formed (no
 *    double-comma, no leading/trailing comma after decoding).
 *  - sToEmailAddress is appended to the recipient list exactly once.
 *  - Case-insensitive dedup: sToEmailAddress whose case differs from a
 *    person's email is still recognised as a duplicate and not added twice.
 */

/** Toggle a system config value via the admin config API.
 *  Requires an active admin session cookie (call cy.setupAdminSession() first).
 *  Pattern matches external.calendar.spec.js. */
function setSystemConfig(name, value) {
    cy.request({
        method: "POST",
        url: `/admin/api/system/config/${name}`,
        body: { value },
        headers: { "Content-Type": "application/json" },
    });
}

/**
 * Decode a rawurlencode()-produced mailto: href attribute into the plain
 * comma-separated recipient string for assertion.
 *  - "mailto:a%40x.com%2Cb%40x.com"  → "a@x.com,b@x.com"
 *  - "mailto:?bcc=a%40x.com%2Cb%40x.com" → "a@x.com,b@x.com"
 */
function decodeMailtoHref(href) {
    return decodeURIComponent(
        href.replace(/^mailto:(\?bcc=)?/, ""),
    );
}

describe("People Dashboard — email recipient list", () => {
    beforeEach(() => cy.setupAdminSession());

    after(() => {
        // Restore sToEmailAddress so other specs are not affected.
        cy.setupAdminSession();
        setSystemConfig("sToEmailAddress", "");
    });

    // ── Basic rendering ───────────────────────────────────────────────────

    it("shows Email All and Email BCC dropdowns when person emails exist", () => {
        cy.visit("people/dashboard");
        cy.contains(".dropdown-toggle", "Email All").should("be.visible");
        cy.contains(".dropdown-toggle", "Email BCC").should("be.visible");
    });

    // ── Href structure: Email All ─────────────────────────────────────────

    it("Email All — All People link is a well-formed mailto: with comma-separated addresses", () => {
        cy.visit("people/dashboard");

        cy.contains(".dropdown-toggle", "Email All").click();

        cy.contains(".dropdown-toggle", "Email All")
            .closest(".dropdown")
            .contains("a.dropdown-item", "All People")
            .invoke("attr", "href")
            .then((href) => {
                expect(href, "starts with mailto:").to.match(/^mailto:/);
                expect(href, "is not a bcc link").not.to.include("?bcc=");

                const decoded = decodeMailtoHref(href);
                expect(decoded, "contains at least one @").to.match(/@/);
                expect(decoded, "no double-comma (empty slot)").not.to.include(",,");
                expect(decoded, "no leading comma").not.to.match(/^,/);
                expect(decoded, "no trailing comma").not.to.match(/,$/);
            });
    });

    // ── Href structure: Email BCC ─────────────────────────────────────────

    it("Email BCC — All People link is a well-formed mailto:?bcc= with comma-separated addresses", () => {
        cy.visit("people/dashboard");

        cy.contains(".dropdown-toggle", "Email BCC").click();

        cy.contains(".dropdown-toggle", "Email BCC")
            .closest(".dropdown")
            .contains("a.dropdown-item", "All People")
            .invoke("attr", "href")
            .then((href) => {
                expect(href, "starts with mailto:?bcc=").to.match(/^mailto:\?bcc=/);

                const decoded = decodeMailtoHref(href);
                expect(decoded, "contains at least one @").to.match(/@/);
                expect(decoded, "no double-comma (empty slot)").not.to.include(",,");
                expect(decoded, "no leading comma").not.to.match(/^,/);
                expect(decoded, "no trailing comma").not.to.match(/,$/);
            });
    });

    // ── sToEmailAddress dedup ─────────────────────────────────────────────

    it("sToEmailAddress is appended to Email All recipients exactly once", () => {
        const defaultTo = "default-to@cypress.example";
        setSystemConfig("sToEmailAddress", defaultTo);

        cy.visit("people/dashboard");
        cy.contains(".dropdown-toggle", "Email All").click();

        cy.contains(".dropdown-toggle", "Email All")
            .closest(".dropdown")
            .contains("a.dropdown-item", "All People")
            .invoke("attr", "href")
            .then((href) => {
                const decoded = decodeMailtoHref(href);
                const occurrences = decoded.toLowerCase().split(defaultTo.toLowerCase()).length - 1;
                expect(occurrences, `'${defaultTo}' appears exactly once in: ${decoded}`)
                    .to.equal(1);
                expect(decoded, "no double-comma").not.to.include(",,");
            });

        setSystemConfig("sToEmailAddress", "");
    });

    it("sToEmailAddress is not duplicated when it matches a person email (case-insensitive)", () => {
        // "lady@nower.com" is a known person email in the seed fixture.
        // Use a differently-cased variant to verify the case-insensitive
        // dedup path in $joinEmails (in_array + strtolower).
        const knownPersonEmail = "lady@nower.com";
        const caseVariant = "Lady@Nower.Com";
        setSystemConfig("sToEmailAddress", caseVariant);

        cy.visit("people/dashboard");
        cy.contains(".dropdown-toggle", "Email All").click();

        cy.contains(".dropdown-toggle", "Email All")
            .closest(".dropdown")
            .contains("a.dropdown-item", "All People")
            .invoke("attr", "href")
            .then((href) => {
                const decoded = decodeMailtoHref(href).toLowerCase();
                const occurrences = decoded.split(knownPersonEmail).length - 1;
                expect(occurrences, `'${knownPersonEmail}' should appear exactly once (case-insensitive dedup)`)
                    .to.equal(1);
                expect(decoded, "no double-comma").not.to.include(",,");
            });

        setSystemConfig("sToEmailAddress", "");
    });
});
