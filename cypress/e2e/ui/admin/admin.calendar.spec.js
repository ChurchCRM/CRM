/// <reference types="cypress" />

describe("Admin Calendar", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Create New Calendar", () => {
        const title = "Calendar: " + new Date().getTime();
        cy.visit("v2/calendar");
        cy.contains("Calendar");

        // Open the Calendars offcanvas panel, then click New Calendar
        cy.get('[data-bs-target="#calendarSidebar"]').click();
        cy.get("#calendarSidebar").should("be.visible");
        cy.get("#addCalendarBtn").click();

        cy.get("#calendarName").click().type(title);
        cy.get("#ForegroundColor").type("FA8072");
        cy.get("#BackgroundColor").type("212F3D");

        // Save button rendered by bootbox with float-end (Bootstrap 5)
        cy.get(".modal-footer .btn-primary.float-end").click();
    });
});
