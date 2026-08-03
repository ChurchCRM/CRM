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

    it("temporal-polyfill and FullCalendar v7 globals are available after page load", () => {
        cy.visit("event/calendars");
        cy.url().should("include", "event/calendars");

        // temporal-polyfill must expose globalThis.Temporal before FC script runs.
        // If missing, FC v7 throws at parse time and no calendar renders.
        cy.window().should("have.property", "Temporal");

        // FullCalendar global must be defined (loaded from all/global.js).
        cy.window().should("have.property", "FullCalendar");

        // CRM's fullcalendar instance must be created after onLocalesReady fires.
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

        cy.window().then((win) => {
            const cal = win.CRM.fullcalendar;

            // Ensure we are in month view so next()/prev() move by one month.
            cal.changeView("dayGridMonth");

            const startMonth = cal.getDate().getMonth(); // 0-based

            // Advance one month
            cal.next();
            const expectedNext = (startMonth + 1) % 12;
            expect(cal.getDate().getMonth(), "after next()").to.equal(expectedNext);

            // Step back two months from original
            cal.prev();
            cal.prev();
            const expectedPrev = (startMonth + 11) % 12; // -1 mod 12
            expect(cal.getDate().getMonth(), "after prev()").to.equal(expectedPrev);

            // Return to original month
            cal.today();
        });
    });

    it("FullCalendar locale option is populated on the calendar instance", () => {
        cy.visit("event/calendars");

        cy.window({ timeout: 15000 }).should((win) => {
            expect(win.CRM?.fullcalendar).to.exist;
        });

        cy.window().then((win) => {
            const fcLocale = win.CRM.fullcalendar.getOption("locale");
            // Locale is always set (defaults to the CRM configured language).
            expect(fcLocale, "FC locale option").to.be.a("string").and.to.have.length.greaterThan(0);
        });
    });
});
