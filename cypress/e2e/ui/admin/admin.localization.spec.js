/// <reference types="cypress" />

describe("Admin - Localization & Formats Page", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should display the localization page with all sections", () => {
        cy.visit("admin/system/localization");

        cy.contains("Localization & Formats", { timeout: 5000 }).should("be.visible");
        cy.contains("Language & Region").should("be.visible");
        cy.contains("Date & Time Formats").should("be.visible");
        cy.contains("Currency & Finance Formats").should("be.visible");
        cy.contains("Phone Number Formats").should("be.visible");
        cy.contains("Display Preview").should("be.visible");
    });

    it("should display language, timezone, and distance unit fields", () => {
        cy.visit("admin/system/localization");

        cy.get("#sLanguage").should("exist");
        cy.get("#sTimeZone").should("exist");
        cy.get("#sDistanceUnit").should("exist");
    });

    it("should display all date & time format fields", () => {
        cy.visit("admin/system/localization");

        cy.get("#sDateFormatLong").should("exist");
        cy.get("#sDateFormatNoYear").should("exist");
        cy.get("#sDateTimeFormat").should("exist");
        cy.get("#sDateFilenameFormat").should("exist");
        cy.get("#sDatePickerFormat").should("exist");
        cy.get("#sDatePickerPlaceHolder").should("exist");
    });

    it("should display all phone number format fields", () => {
        cy.visit("admin/system/localization");

        cy.get("#sPhoneFormat").should("exist");
        cy.get("#sPhoneFormatCell").should("exist");
        cy.get("#sPhoneFormatWithExt").should("exist");
    });

    it("should have TomSelect initialized for language and timezone", () => {
        cy.visit("admin/system/localization");

        cy.get("#sLanguage", { timeout: 8000 }).siblings(".ts-wrapper").should("exist");
        cy.get("#sTimeZone", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");
    });

    it("should populate language dropdown grouped by region with native names", () => {
        cy.visit("admin/system/localization");

        // Wait for JS to populate and TomSelect to init
        cy.get("#sLanguage", { timeout: 8000 }).siblings(".ts-wrapper").should("exist");

        // Underlying <select> must have region optgroups
        cy.get("#sLanguage optgroup").should("have.length.greaterThan", 2);
        cy.get("#sLanguage optgroup[label='Americas']").should("exist");
        cy.get("#sLanguage optgroup[label='Europe']").should("exist");

        // Options must include locale code in brackets
        cy.get("#sLanguage optgroup[label='Americas'] option").first()
            .invoke("text")
            .should("match", /\[.+\]$/);

        // English (US) base locale must be present
        cy.get("#sLanguage option[value='en_US']").should("exist");
    });

    it("should show a live date preview when a date format is entered", () => {
        cy.visit("admin/system/localization");

        cy.get("#sDateFormatLong", { timeout: 5000 }).clear().type("Y-m-d");
        // The consolidated Display Preview mirrors the input value.
        cy.get(".format-preview-value[data-source='sDateFormatLong']")
            .invoke("text")
            .should("match", /^\d{4}-\d{2}-\d{2}$/);
    });

    it("should show a live phone preview when a phone format is entered", () => {
        cy.visit("admin/system/localization");

        cy.get("#sPhoneFormat", { timeout: 5000 }).clear().type("(999) 999-9999");
        cy.get(".format-preview-value[data-source='sPhoneFormat']")
            .invoke("text")
            .should("match", /^\(\d{3}\) \d{3}-\d{4}$/);
    });

    it("should allow saving localization settings", () => {
        cy.visit("admin/system/localization");

        cy.get("#sDistanceUnit", { timeout: 5000 }).select("kilometers");
        cy.get("#localization-form").submit();

        cy.url({ timeout: 10000 }).should("include", "localization");
        cy.contains("Localization settings saved successfully", { timeout: 10000 }).should("be.visible");
    });

    // ── Currency & Finance Formats — read-only UI tests ───────────────────────

    it("should display all four currency fields with correct defaults", () => {
        cy.visit("admin/system/localization");

        cy.get("#sCurrencySymbol", { timeout: 5000 }).should("have.value", "$");
        cy.get("#sCurrencyPosition").should("have.value", "before");
        cy.get("#sThousandsSeparator").should("have.value", ",");
        cy.get("#sDecimalSeparator").should("have.value", ".");
    });

    it("should update the currency live preview when inputs change", () => {
        cy.visit("admin/system/localization");

        cy.get("#sCurrencySymbol", { timeout: 5000 }).clear().type("\u20ac");
        cy.get("#sCurrencyPosition").select("after");
        cy.get("#sThousandsSeparator").clear().type(".");
        cy.get("#sDecimalSeparator").clear().type(",");

        // Preview should now show "1.234,56 €" (NBSP between number and symbol)
        cy.get("#currency-format-preview")
            .invoke("text")
            .should("include", "1.234,56")
            .and("include", "\u20ac");
    });

    it("should render the six currency preset buttons injected by JS", () => {
        cy.visit("admin/system/localization");

        cy.get(".currency-preset-btn", { timeout: 5000 }).should("have.length", 6);
        cy.get(".currency-preset-btn[data-preset='usd']").should("be.visible");
        cy.get(".currency-preset-btn[data-preset='eur']").should("be.visible");
        cy.get(".currency-preset-btn[data-preset='gbp']").should("be.visible");
        cy.get(".currency-preset-btn[data-preset='chf']").should("be.visible");
        cy.get(".currency-preset-btn[data-preset='brl']").should("be.visible");
        cy.get(".currency-preset-btn[data-preset='inr']").should("be.visible");
    });

    it("should populate the four fields and live preview when Euro preset is clicked", () => {
        cy.visit("admin/system/localization");

        cy.get(".currency-preset-btn[data-preset='eur']", { timeout: 5000 }).click();

        cy.get("#sCurrencySymbol").should("have.value", "\u20ac");
        cy.get("#sCurrencyPosition").should("have.value", "after");
        cy.get("#sThousandsSeparator").should("have.value", ".");
        cy.get("#sDecimalSeparator").should("have.value", ",");

        // Live preview must update to European format
        cy.get("#currency-format-preview")
            .invoke("text")
            .should("include", "1.234,56")
            .and("include", "\u20ac");
    });

    it("should not auto-save when a preset is clicked (no success toast)", () => {
        cy.visit("admin/system/localization");

        cy.get(".currency-preset-btn[data-preset='eur']", { timeout: 5000 }).click();

        // Fields are now populated but no form submission should have occurred —
        // we are still on the page with the form visible and no success toast yet.
        cy.get("#localization-form").should("be.visible");
        cy.get("#sCurrencySymbol").should("have.value", "\u20ac");
        // Toast is NOT present (we did not submit)
        cy.contains("Localization settings saved successfully").should("not.exist");
    });
});

// ── Currency settings — save round-trip (admin-session) ─────────────────────
//
// These tests mutate global SystemConfig. An after() hook resets all four
// currency keys back to seed defaults via the admin config API so later
// specs are not affected.

describe("Admin - Currency settings save round-trip", () => {
    after(() => {
        // Reset all four keys to seed defaults regardless of test outcomes.
        cy.setupAdminSession();
        const defaults = [
            ["sCurrencySymbol", "$"],
            ["sCurrencyPosition", "before"],
            ["sThousandsSeparator", ","],
            ["sDecimalSeparator", "."],
        ];
        for (const [name, value] of defaults) {
            cy.request({
                method: "POST",
                url: `/admin/api/system/config/${name}`,
                body: { value },
                headers: { "Content-Type": "application/json" },
            });
        }
    });

    it("persists currency settings after save and page reload", () => {
        cy.setupAdminSession();

        cy.intercept("POST", "**/admin/system/localization").as("saveLocalization");

        cy.visit("admin/system/localization");

        // Set Euro settings (separators differ — uniqueness guard passes)
        cy.get("#sCurrencySymbol", { timeout: 5000 }).clear().type("\u20ac");
        cy.get("#sCurrencyPosition").select("after");
        cy.get("#sThousandsSeparator").clear().type(".");
        cy.get("#sDecimalSeparator").clear().type(",");

        cy.get("#localization-form").submit();
        cy.wait("@saveLocalization").its("response.statusCode").should("be.oneOf", [200, 302, 303]);

        // After redirect the page reloads — visit again and verify persisted values
        cy.visit("admin/system/localization");
        cy.get("#sCurrencySymbol", { timeout: 5000 }).should("have.value", "\u20ac");
        cy.get("#sCurrencyPosition").should("have.value", "after");
        cy.get("#sThousandsSeparator").should("have.value", ".");
        cy.get("#sDecimalSeparator").should("have.value", ",");
    });

    it("rejects matching thousands and decimal separators with a danger flash", () => {
        cy.setupAdminSession();

        cy.visit("admin/system/localization");

        // Set both separators to "," — server must reject this
        cy.get("#sThousandsSeparator", { timeout: 5000 }).clear().type(",");
        cy.get("#sDecimalSeparator").clear().type(",");

        cy.get("#localization-form").submit();

        // Flash message appears via window.CRM.notify (auto-dismisses in ~5s)
        cy.contains("must be different characters", { timeout: 8000 }).should("be.visible");
    });
});
