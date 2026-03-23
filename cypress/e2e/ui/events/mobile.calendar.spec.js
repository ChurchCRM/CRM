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

    it("Should stack sidebar below calendar on mobile", () => {
        cy.viewport(375, 812);
        cy.visit("v2/calendar");

        // Both columns should be full-width and the sidebar should stack below the calendar
        cy.get(".col-sm-12")
            .should("have.length.at.least", 2)
            .then(($cols) => {
                const firstRect = $cols[0].getBoundingClientRect();
                const secondRect = $cols[1].getBoundingClientRect();
                expect(secondRect.top).to.be.at.least(firstRect.bottom);
            });
    });

    it("Should display calendar on tablet viewport", () => {
        cy.viewport(768, 1024);
        cy.visit("v2/calendar");
        cy.url().should("include", "v2/calendar");

        cy.get("#calendar").should("be.visible");

        // Tablet uses col-md-8 / col-md-4 split
        cy.get(".col-md-8").should("exist");
        cy.get(".col-md-4").should("exist");

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

        // Modal should appear and be properly sized for mobile
        cy.get(".modal.show .modal-dialog").should("be.visible");
        cy.get(".modal.show .modal-header input").should("be.visible").type(title);
    });
});
