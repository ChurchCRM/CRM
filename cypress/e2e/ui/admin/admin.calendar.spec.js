/// <reference types="cypress" />

describe("Admin Calendar", () => {
    beforeEach(() => {});

    it("Create New Calendar", () => {
        const title = "Calendar: " + new Date().getTime();
        cy.loginAdmin("v2/calendar");
        cy.contains("Calendar");
        cy.get("#newCalendarButton").click();
        cy.get("#calendarName").click().type(title);
        cy.get("#ForegroundColor").type("FA8072");
        cy.get("#BackgroundColor").type("212F3D");
        cy.get(".modal-footer > .pull-right").click();
        // cy.contains(title);
    });
});
