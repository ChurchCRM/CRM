/// <reference types="cypress" />

describe("Admin - Localization & Formats Page", () => {
    // Reset the four currency keys to seed defaults before any test in this suite
    // runs, so the "correct defaults" test is not polluted by a prior CI run.
    before(() => {
        cy.setupAdminSession();
        for (const [name, value] of [
            ["sCurrencySymbol", "$"],
            ["sCurrencyPosition", "before"],
            ["sThousandsSeparator", ","],
            ["sDecimalSeparator", "."],
        ]) {
            cy.request({
                method: "POST",
                url: `/admin/api/system/config/${name}`,
                body: { value },
                headers: { "Content-Type": "application/json" },
            });
        }
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should display the localization page with all sections", () => {
        cy.visit("/admin/system/localization");

        cy.contains("Localization & Formats", { timeout: 5000 }).should("be.visible");
        cy.contains("Language & Region").should("be.visible");
        cy.contains("Date & Time Formats").should("be.visible");
        cy.contains("Currency & Finance Formats").should("be.visible");
        cy.contains("Phone Number Formats").should("be.visible");
        cy.contains("Display Preview").should("be.visible");
    });

    it("should display language, timezone, and distance unit fields", () => {
        cy.visit("/admin/system/localization");

        cy.get("#sLanguage").should("exist");
        cy.get("#sTimeZone").should("exist");
        cy.get("#sDistanceUnit").should("exist");
    });

    it("should display all date & time format fields", () => {
        cy.visit("/admin/system/localization");

        cy.get("#sDateFormatLong").should("exist");
        cy.get("#sDateFormatNoYear").should("exist");
        cy.get("#sDateTimeFormat").should("exist");
        cy.get("#sDateFilenameFormat").should("exist");
        cy.get("#sDatePickerFormat").should("exist");
        cy.get("#sDatePickerPlaceHolder").should("exist");
    });

    it("should display all phone number format fields", () => {
        cy.visit("/admin/system/localization");

        cy.get("#sPhoneFormat").should("exist");
        cy.get("#sPhoneFormatCell").should("exist");
        cy.get("#sPhoneFormatWithExt").should("exist");
    });

    it("should have TomSelect initialized for language and timezone", () => {
        cy.visit("/admin/system/localization");

        cy.get("#sLanguage", { timeout: 8000 }).siblings(".ts-wrapper").should("exist");
        cy.get("#sTimeZone", { timeout: 5000 }).siblings(".ts-wrapper").should("exist");
    });

    // Regression for #9677: TomSelect's default maxOptions:50 truncated the
    // ~419-entry timezone list at "America/*". Assert the rendered option count
    // matches the <select>'s own option count — a rendered count of exactly 50
    // is the signature of the cap still being in place.
    it("should render every timezone option, not just the first 50 (#9677)", () => {
        cy.visit("/admin/system/localization");

        cy.get("#sTimeZone", { timeout: 8000 }).siblings(".ts-wrapper").should("exist");

        cy.get("#sTimeZone option").then(($opts) => {
            const expected = $opts.length;
            expect(expected, "timezone list is long enough to trip the 50-option cap").to.be.greaterThan(50);

            cy.get("#sTimeZone").siblings(".ts-wrapper").find(".ts-control").click();
            cy.get("#sTimeZone").siblings(".ts-wrapper").find(".ts-dropdown .option", { timeout: 5000 })
                .should("have.length", expected);

            // The specific casualty of the cap: Pacific/* sorts last and was unreachable.
            cy.get("#sTimeZone").siblings(".ts-wrapper").find(".ts-dropdown")
                .should("contain.text", "Pacific/");
        });

        cy.get("body").type("{esc}");
    });

    it("should populate language dropdown grouped by region with native names", () => {
        cy.visit("/admin/system/localization");

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
        cy.visit("/admin/system/localization");

        cy.get("#sDateFormatLong", { timeout: 5000 }).clear().type("Y-m-d");
        // The consolidated Display Preview mirrors the input value.
        cy.get(".format-preview-value[data-source='sDateFormatLong']")
            .invoke("text")
            .should("match", /^\d{4}-\d{2}-\d{2}$/);
    });

    it("should show a live phone preview when a phone format is entered", () => {
        cy.visit("/admin/system/localization");

        cy.get("#sPhoneFormat", { timeout: 5000 }).clear().type("(999) 999-9999");
        cy.get(".format-preview-value[data-source='sPhoneFormat']")
            .invoke("text")
            .should("match", /^\(\d{3}\) \d{3}-\d{4}$/);
    });

    it("should allow saving localization settings", () => {
        cy.visit("/admin/system/localization");

        cy.get("#sDistanceUnit", { timeout: 5000 }).select("kilometers");
        cy.get("#localization-form").submit();

        cy.url({ timeout: 10000 }).should("include", "localization");
        cy.contains("Localization settings saved successfully", { timeout: 10000 }).should("be.visible");
    });

    // ── Currency & Finance Formats — read-only UI tests ───────────────────────

    it("should display all four currency fields with correct defaults", () => {
        cy.visit("/admin/system/localization");

        cy.get("#sCurrencySymbol", { timeout: 5000 }).should("have.value", "$");
        cy.get("#sCurrencyPosition").should("have.value", "before");
        cy.get("#sThousandsSeparator").should("have.value", ",");
        cy.get("#sDecimalSeparator").should("have.value", ".");
    });

    it("should update the currency live preview when inputs change", () => {
        cy.visit("/admin/system/localization");

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
        cy.visit("/admin/system/localization");

        cy.get(".currency-preset-btn", { timeout: 5000 }).should("have.length", 6);
        cy.get(".currency-preset-btn[data-preset='usd']").should("be.visible");
        cy.get(".currency-preset-btn[data-preset='eur']").should("be.visible");
        cy.get(".currency-preset-btn[data-preset='gbp']").should("be.visible");
        cy.get(".currency-preset-btn[data-preset='chf']").should("be.visible");
        cy.get(".currency-preset-btn[data-preset='brl']").should("be.visible");
        cy.get(".currency-preset-btn[data-preset='inr']").should("be.visible");
    });

    it("should populate the four fields and live preview when Euro preset is clicked", () => {
        cy.visit("/admin/system/localization");

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

    it("should mirror the currency sample in the top Display Preview", () => {
        cy.visit("/admin/system/localization");

        // Two render targets: the Display Preview column and the inline Currency card.
        cy.get(".currency-preview-target", { timeout: 5000 }).should("have.length", 2);
        cy.get(".currency-preview-target").first().invoke("text").should("include", "1,234.56");

        // Changing the symbol updates BOTH targets.
        cy.get("#sCurrencySymbol").clear().type("£");
        cy.get(".currency-preview-target").each(($el) => {
            expect($el.text()).to.include("£");
        });
    });

    it("should provide Edit quick links that jump to each settings section", () => {
        cy.visit("/admin/system/localization");

        for (const id of ["section-language", "section-datetime", "section-currency", "section-phone"]) {
            cy.get(`a[href='#${id}']`, { timeout: 5000 }).should("be.visible");
            cy.get(`#${id}`).should("exist");
        }

        cy.get("a[href='#section-currency']").click();
        cy.location("hash").should("eq", "#section-currency");
        cy.get("#section-currency").should("be.visible");
    });

    it("should not auto-save when a preset is clicked (no success toast)", () => {
        cy.visit("/admin/system/localization");

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

        cy.visit("/admin/system/localization");

        // Set Euro settings (separators differ — uniqueness guard passes)
        cy.get("#sCurrencySymbol", { timeout: 5000 }).clear().type("\u20ac");
        cy.get("#sCurrencyPosition").select("after");
        cy.get("#sThousandsSeparator").clear().type(".");
        cy.get("#sDecimalSeparator").clear().type(",");

        cy.get("#localization-form").submit();
        cy.wait("@saveLocalization").its("response.statusCode").should("be.oneOf", [200, 302, 303]);

        // After redirect the page reloads — visit again and verify persisted values
        cy.visit("/admin/system/localization");
        cy.get("#sCurrencySymbol", { timeout: 5000 }).should("have.value", "\u20ac");
        cy.get("#sCurrencyPosition").should("have.value", "after");
        cy.get("#sThousandsSeparator").should("have.value", ".");
        cy.get("#sDecimalSeparator").should("have.value", ",");
    });

    it("rejects matching thousands and decimal separators with a danger flash", () => {
        cy.setupAdminSession();

        cy.visit("/admin/system/localization");

        // Set both separators to "," — server must reject this
        cy.get("#sThousandsSeparator", { timeout: 5000 }).clear().type(",");
        cy.get("#sDecimalSeparator").clear().type(",");

        cy.get("#localization-form").submit();

        // Flash message appears via window.CRM.notify (auto-dismisses in ~5s)
        cy.contains("must be different characters", { timeout: 8000 }).should("be.visible");
    });
});

// ── Language selection save round-trip — regression test for #9656 ───────────
//
// Before the fix, the POST /localization handler validated the submitted locale
// code against display-name keys of locales.json (e.g. "Spanish - Spain") rather
// than locale codes (e.g. es_ES). in_array() always failed → silently fell back
// to en_US. This test proves the language selection is now persisted correctly.
//
// Cleanup resets sLanguage to en_US via the admin config API in an after() hook
// so subsequent specs start with a known locale.

describe("Admin - Language settings save round-trip (#9656)", () => {
    after(() => {
        // Reset language to en_US regardless of test outcome.
        cy.setupAdminSession();
        cy.request({
            method: "POST",
            url: `/admin/api/system/config/sLanguage`,
            body: { value: "en_US" },
            headers: { "Content-Type": "application/json" },
        });
    });

    it("persists a non-English language selection after save and page reload", () => {
        cy.setupAdminSession();

        cy.intercept("POST", "**/admin/system/localization").as("saveLocalization");

        cy.visit("/admin/system/localization");

        // Wait for TomSelect to initialise on #sLanguage before interacting.
        cy.get("#sLanguage", { timeout: 8000 }).siblings(".ts-wrapper").should("exist");

        // Set the language to Spanish - Spain (es_ES) via the TomSelect API.
        // Using the native select with {force:true} would bypass TomSelect's
        // internal state; setValue() keeps both the widget and the underlying
        // <select> in sync so the submitted form value is correct.
        cy.get("#sLanguage").then(($el) => {
            $el[0].tomselect.setValue("es_ES");
        });

        cy.get("#localization-form").submit();
        cy.wait("@saveLocalization").its("response.statusCode").should("be.oneOf", [200, 302, 303]);

        // Reload the page and verify the persisted value.
        // data-selected-locale is a server-rendered attribute whose value comes
        // directly from SystemConfig::getValue('sLanguage'). Asserting it equals
        // es_ES proves the backend stored the locale code, not en_US.
        cy.visit("/admin/system/localization");
        cy.get("#sLanguage", { timeout: 8000 }).should("have.attr", "data-selected-locale", "es_ES");
    });
});
