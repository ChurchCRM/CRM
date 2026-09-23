/// <reference types="cypress" />

/**
 * FullCalendar v7 no longer uses stable .fc-* class names — all class names
 * are hashed (e.g. .fc-classic-0Bj). Use stable alternatives:
 *
 *  - Toolbar nav buttons: aria-label partial match (English defaults):
 *      Previous \u2192 [aria-label*="Previous"]
 *      Next     \u2192 [aria-label*="Next"]
 *      Today    \u2192 [aria-label*="Today"], [aria-label*="This"] (month view: "This Month")
 *      Month view button \u2192 [aria-label="Month view"]
 *
 *  - Footer toolbar: .crm-fc-footer-toolbar (injected via footerToolbarClass option
 *      added to the Calendar constructor in event-calendars.js for FC v7 compat).
 *
 *  - Day cell clicks: window.showNewEventForm() global (selector-independent).
 */
describe("Mobile Calendar", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Should display calendar on mobile viewport", () => {
        cy.viewport(375, 812);
        cy.visit("event/calendars");
        cy.url().should("include", "event/calendars");

        // Calendar should be visible
        cy.get("#calendar").should("be.visible");

        // Calendar container should have a non-zero pixel width
        cy.get("#calendar").should(($calendar) => {
            const width = parseFloat($calendar.css("width"));
            expect(width).to.be.greaterThan(0);
        });

        // Mobile: simplified header with prev/next/today navigation buttons.
        // Use aria-label partial match — stable across FullCalendar v7 theme changes.
        cy.get("#calendar [aria-label*='Previous']").should("be.visible");
        cy.get("#calendar [aria-label*='Next']").should("be.visible");
        // Today button: "This Month" in month view, "Today" in day view.
        cy.get("#calendar [aria-label*='Today'], #calendar [aria-label*='This']").should("be.visible");

        // View-switcher buttons should be in the footer toolbar on mobile.
        // .crm-fc-footer-toolbar is set via footerToolbarClass option in event-calendars.js.
        cy.get(".crm-fc-footer-toolbar").should("be.visible");
        cy.get("#calendar [aria-label='Month view']").should("be.visible");
    });

    it("Should show Calendars offcanvas button on mobile", () => {
        cy.viewport(375, 812);
        cy.visit("event/calendars");

        // Calendar is now full-width with an offcanvas panel for the sidebar
        cy.get(".card #calendar").should("be.visible");

        // The Calendars offcanvas trigger must be present and functional
        cy.get('[data-bs-target="#calendarSidebar"]').should("be.visible").click();
        cy.get("#calendarSidebar").should("be.visible");
        cy.get(".offcanvas-title").should("contain.text", "Calendars");

        // Close the offcanvas
        cy.get("#calendarSidebar .btn-close").click();
        cy.get("#calendarSidebar").should("not.have.class", "show");
    });

    it("Should display calendar on tablet viewport", () => {
        cy.viewport(768, 1024);
        cy.visit("event/calendars");
        cy.url().should("include", "event/calendars");

        // Calendar is full-width with offcanvas sidebar — no split columns
        cy.get(".card #calendar").should("be.visible");

        // Calendars toggle must be visible at tablet breakpoint
        cy.get('[data-bs-target="#calendarSidebar"]').should("be.visible");

        // Desktop toolbar should be active (view-switcher in header, no footer toolbar)
        cy.get("#calendar [aria-label='Month view']").should("be.visible");
        cy.get(".crm-fc-footer-toolbar").should("not.exist");
    });

    it("Should switch to desktop toolbar after rotating to landscape", () => {
        cy.viewport(375, 812); // portrait — mobile toolbar
        cy.visit("event/calendars");
        cy.get(".crm-fc-footer-toolbar").should("be.visible");

        // Rotate to landscape past the 768px breakpoint.
        // The native window resize listener (200ms debounce) calls
        // fullcalendar.setOption("footerToolbar", false), removing the footer toolbar.
        cy.viewport(812, 375);

        // After resize + debounce, footer toolbar should be gone and view buttons in header
        cy.get(".crm-fc-footer-toolbar").should("not.exist");
        cy.get("#calendar [aria-label='Month view']").should("be.visible");
    });

    it("Should open event creation modal on mobile", () => {
        const title = "Mobile Event - " + Cypress._.random(0, 1e6);
        cy.viewport(375, 812);
        cy.visit("event/calendars");

        // Open modal via the stable global function (avoids FullCalendar v7 hashed class names)
        cy.window().should("have.property", "showNewEventForm");
        cy.window().then((win) => {
            const today = new Date().toISOString().split("T")[0];
            win.showNewEventForm({ startStr: today, endStr: today, allDay: true });
        });

        // Modal should appear and be properly sized for mobile.
        // Assert the modal exists, then scroll the title input into view and type.
        cy.get("#eventEditorModal.show").should("exist");
        cy.get("#event-title-input").scrollIntoView().should("be.visible").type(title);
    });
});
