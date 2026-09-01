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

        // FC v7 getDate() returns the first VISIBLE grid date, which may be from the
        // prior month when the displayed month starts mid-week (e.g. October 2026 starts
        // on Thursday, so the grid's first cell is September 27 — still month 8).
        // Asserting on getDate().getMonth() after navigation is therefore unreliable.
        //
        // Correct approach: assert on data-date DOM attributes, which are stable in FC v7
        // (v7 hashes CSS class names; data-date is the recommended stable selector).
        // The 1st of every month is always rendered in that month's dayGridMonth view.
        //
        // Expected dates are computed from the wall-clock date at test runtime — the
        // calendar always starts at the current month after a fresh page load.
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth(); // 0-based

        // First day of next month
        const nextMonth = (month + 1) % 12;
        const nextYear = nextMonth === 0 ? year + 1 : year;
        const nextMonthFirst = `${nextYear}-${String(nextMonth + 1).padStart(2, "0")}-01`;

        // First day of previous month
        const prevMonth = (month - 1 + 12) % 12;
        const prevYear = prevMonth === 11 ? year - 1 : year;
        const prevMonthFirst = `${prevYear}-${String(prevMonth + 1).padStart(2, "0")}-01`;

        // Ensure we are in month view; wait for at least one day cell before navigating.
        cy.window().then((win) => {
            win.CRM.fullcalendar.changeView("dayGridMonth");
        });
        cy.get("#calendar [data-date]", { timeout: 10000 }).should("have.length.greaterThan", 0);

        // Advance one month; the 1st of next month must appear in the grid.
        cy.window().then((win) => {
            win.CRM.fullcalendar.next();
        });
        cy.get(`#calendar [data-date="${nextMonthFirst}"]`, { timeout: 5000 }).should("exist");

        // Step back two months from the original; the 1st of the previous month must appear.
        cy.window().then((win) => {
            win.CRM.fullcalendar.prev();
            win.CRM.fullcalendar.prev();
        });
        cy.get(`#calendar [data-date="${prevMonthFirst}"]`, { timeout: 5000 }).should("exist");

        // Return to the current month.
        cy.window().then((win) => {
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
