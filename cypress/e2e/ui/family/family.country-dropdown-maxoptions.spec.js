/// <reference types="cypress" />

/**
 * Regression tests for the TomSelect `maxOptions` fix — issue #9677.
 *
 * TomSelect v2 defaults to `maxOptions: 50` and applies it to the rendered
 * result set, so long fixed lists were silently truncated with no scrollbar
 * and no "more results" hint:
 *
 *   - Country (256 entries) stopped at "China (中国)" — United States was
 *     unreachable by scrolling.
 *   - US State/Province (59 entries) stopped at "Tennessee" — Texas through
 *     Wyoming were unreachable.
 *
 * `TomSelect.defaults` is module-private in v2, so the cap has to be lifted
 * per call site. These tests assert the rendered option count, not just that
 * the dropdown opens — a count of exactly 50 is the signature of the bug.
 */
describe("Country/State TomSelect renders the full list (#9677)", () => {
    beforeEach(() => {
        cy.setupStandardSession();
    });

    it("Family Editor country dropdown renders all countries, not just the first 50", () => {
        cy.visit("/FamilyEditor.php");

        cy.window().should("have.property", "CRM");
        cy.window().its("CRM.localesLoaded").should("eq", true);

        // DropdownManager populates #Country from /api/public/data/countries and
        // then wraps it with TomSelect, which adds 'tomselected' to the <select>.
        cy.get("select#Country", { timeout: 10000 }).should("have.class", "tomselected");

        // The API is the source of truth for how many options there should be.
        cy.request("/api/public/data/countries").then((resp) => {
            const expected = resp.body.length;
            expect(expected, "country list is long enough to trip the 50-option cap").to.be.greaterThan(50);

            // TomSelect inserts .ts-wrapper as a NEXT SIBLING of the <select>, and
            // (with no dropdownParent set) nests its .ts-dropdown inside that wrapper.
            // Scope to the wrapper so the state dropdown's options are not counted too.
            cy.get("select#Country").next(".ts-wrapper").find(".ts-control").click();

            cy.get("select#Country")
                .next(".ts-wrapper")
                .find(".ts-dropdown .option", { timeout: 5000 })
                .should("have.length", expected);

            // The specific regression: United States must be reachable by scrolling.
            cy.get("select#Country").next(".ts-wrapper").find(".ts-dropdown .option")
                .last()
                .should("contain.text", "Zimbabwe");
            cy.get("select#Country").next(".ts-wrapper").find(".ts-dropdown")
                .should("contain.text", "United States");
        });

        cy.get("body").type("{esc}");
    });

    it("Family Editor state dropdown renders all US states, not just the first 50", () => {
        cy.visit("/FamilyEditor.php");

        cy.window().should("have.property", "CRM");
        cy.window().its("CRM.localesLoaded").should("eq", true);

        cy.get("select#Country", { timeout: 10000 }).should("have.class", "tomselected");

        // Selecting the US cascades DropdownManager.initializeState() into #State.
        cy.get("select#Country").then(($sel) => {
            $sel[0].tomselect.setValue("US");
        });

        cy.request("/api/public/data/countries/us/states").then((resp) => {
            const expected = Object.keys(resp.body).length;
            expect(expected, "US state list is long enough to trip the 50-option cap").to.be.greaterThan(50);

            // Wait for the states cascade to fully settle before touching the widget:
            // setValue() can fire the change event more than once, so assert on the
            // rebuilt <select> reaching its final option count rather than racing it.
            cy.get("select#State option", { timeout: 10000 }).should("have.length", expected);
            cy.get("select#State").should("have.class", "tomselected");

            cy.get("select#State").next(".ts-wrapper").find(".ts-control").click();

            cy.get("select#State")
                .next(".ts-wrapper")
                .find(".ts-dropdown .option", { timeout: 5000 })
                .should("have.length", expected);

            // Texas through Wyoming were the casualties of the 50-option cap.
            cy.get("select#State").next(".ts-wrapper").find(".ts-dropdown")
                .should("contain.text", "Wyoming");
        });

        cy.get("body").type("{esc}");
    });
});
