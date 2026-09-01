/// <reference types="cypress" />

/**
 * FullCalendar v7 integration tests.
 *
 * v7 key changes vs v6:
 *  - Requires temporal-polyfill (globalThis.Temporal) loaded before the FC script.
 *  - No longer auto-injects CSS \u2014 skeleton.css + theme + palette must be loaded via <link>.
 *  - CSS class names are hashed; use data-date attributes and aria-label for stable selectors.
 *  - windowResize / windowResizeDelay options removed; replaced by native resize listener.
 *  - Per-event color properties: backgroundColor+textColor \u2192 color+contrastColor.
 */
describe("FullCalendar v7 Integration", () => {
    beforeEach(() => cy.setupStandardSession());

    it("temporal-polyfill is installed on globalThis and FC calendar instance is created", () => {
        cy.visit("event/calendars");
        cy.url().should("include", "event/calendars");

        // temporal-polyfill must expose globalThis.Temporal before FC module loads.
        // If missing, FC v7 throws at parse time and no calendar renders.
        cy.window().should("have.property", "Temporal");

        // FullCalendar is now bundled via webpack (no longer a global).
        // Verify the calendar instance is created instead.
        cy.window({ timeout: 15000 }).should((win) => {
            expect(win.CRM?.fullcalendar, "window.CRM.fullcalendar").to.exist;
        });
    });

    it("calendar grid renders with day cells identifiable via data-date attribute", () => {
        cy.visit("event/calendars");

        // Wait for FC initialization
        cy.window({ timeout: 15000 }).should((win) => {
            expect(win.CRM?.fullcalendar).to.exist;
        });

        cy.get("#calendar").should("be.visible");

        // v7 day cells carry a stable data-date="YYYY-MM-DD" attribute regardless of theme.
        // This is the recommended selector since v7 hashes all CSS class names.
        cy.get("#calendar [data-date]", { timeout: 10000 }).should("have.length.greaterThan", 0);
    });

    it("month navigation via JS API advances and reverses the displayed month", () => {
        cy.visit("event/calendars");

        cy.window({ timeout: 15000 }).should((win) => {
            expect(win.CRM?.fullcalendar).to.exist;
        });

        // FC v7 navigation (next/prev/today) triggers an async re-render.
        // We must NOT assert getDate() in the same .then() as the navigation call
        // because the internal date state may not be flushed yet.
        // Instead, split each navigation into its own .then() and assert in a
        // .should() block (which Cypress retries) so the test is resilient to the
        // async update.
        let startMonth;

        cy.window().then((win) => {
            const cal = win.CRM.fullcalendar;
            // Ensure we are in month view so next()/prev() move by one month.
            cal.changeView("dayGridMonth");
            startMonth = cal.getDate().getMonth(); // 0-based; capture before navigation
            cal.next();
        });

        // Retry until FC has updated its internal date after next()
        cy.window().should((win) => {
            expect(win.CRM.fullcalendar.getDate().getMonth(), "after next()").to.equal(
                (startMonth + 1) % 12,
            );
        });

        cy.window().then((win) => {
            // Step back two months from original
            win.CRM.fullcalendar.prev();
            win.CRM.fullcalendar.prev();
        });

        // Retry until FC has updated its internal date after two prev() calls
        cy.window().should((win) => {
            expect(win.CRM.fullcalendar.getDate().getMonth(), "after prev()").to.equal(
                (startMonth + 11) % 12, // -1 mod 12
            );
        });

        cy.window().then((win) => {
            // Return to original month
            win.CRM.fullcalendar.today();
        });
    });

    it("FullCalendar locale option is a string on the calendar instance", () => {
        cy.visit("event/calendars");

        cy.window({ timeout: 15000 }).should((win) => {
            expect(win.CRM?.fullcalendar).to.exist;
        });

        cy.window().then((win) => {
            const fcLocale = win.CRM.fullcalendar.getOption("locale");
            // Locale is always a string (either the CRM configured code or the
            // built-in FC default 'en'). After applyFcLocale(), locale is set
            // via setOption('locale', mod.default.code) — still a string.
            expect(fcLocale, "FC locale option").to.be.a("string").and.to.have.length.greaterThan(0);
        });
    });
});
