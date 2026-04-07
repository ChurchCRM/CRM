/// <reference types="cypress" />

describe("Mobile Calendar", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Should display calendar on mobile viewport", () => {
        cy.viewport(375, 812);
        cy.visit("v2/calendar");
        cy.url().should("include", "v2/calendar");

        // Calendar should be visible
        cy.get("#calendar").should("be.visible");

        // Calendar container should have a non-zero pixel width
        cy.get("#calendar").should(($calendar) => {
            const width = parseFloat($calendar.css("width"));
            expect(width).to.be.greaterThan(0);
        });

        // Mobile: simplified header with prev/next/today
        cy.get(".fc-prev-button").should("be.visible");
        cy.get(".fc-next-button").should("be.visible");
        cy.get(".fc-today-button").should("be.visible");

        // View-switcher buttons should be in the footer toolbar on mobile
        cy.get(".fc-footer-toolbar").should("be.visible");
        cy.get(".fc-dayGridMonth-button").should("be.visible");
    });

    it("Should show Calendars offcanvas button on mobile", () => {
        cy.viewport(375, 812);
        cy.visit("v2/calendar");

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
        cy.visit("v2/calendar");
        cy.url().should("include", "v2/calendar");

        // Calendar is full-width with offcanvas sidebar — no split columns
        cy.get(".card #calendar").should("be.visible");

        // Calendars toggle must be visible at tablet breakpoint
        cy.get('[data-bs-target="#calendarSidebar"]').should("be.visible");

        // Desktop toolbar should be active (view-switcher in header, no footer toolbar)
        cy.get(".fc-dayGridMonth-button").should("be.visible");
        cy.get(".fc-footer-toolbar").should("not.exist");
    });

    it("Should switch to desktop toolbar after rotating to landscape", () => {
        cy.viewport(375, 812); // portrait — mobile toolbar
        cy.visit("v2/calendar");
        cy.get(".fc-footer-toolbar").should("be.visible");

        // Rotate to landscape past the 768px breakpoint
        cy.viewport(812, 375);

        // After resize, footer toolbar should be gone and view buttons in header
        cy.get(".fc-footer-toolbar").should("not.exist");
        cy.get(".fc-dayGridMonth-button").should("be.visible");
    });

    it("Should open event creation modal on mobile", () => {
        const title = "Mobile Event - " + Cypress._.random(0, 1e6);
        cy.viewport(375, 812);
        cy.visit("v2/calendar");

        cy.get(".fc-daygrid-day").first().click();

        // Modal should appear and be properly sized for mobile.
        // Assert the modal exists, then scroll the title input into view and type.
        cy.get("#eventEditorModal.show").should("exist");
        cy.get("#event-title-input").scrollIntoView().should("be.visible").type(title);
    });
});
