/// <reference types="cypress" />

describe("Events Dashboard (MVC)", () => {
    beforeEach(() => cy.setupAdminSession());

    it("should display the events dashboard with stat cards", () => {
        cy.visit("event/dashboard");
        cy.contains("Events Dashboard").should("exist");
        cy.contains("Events This Year").should("exist");
        cy.contains("Total Check-ins").should("exist");
        cy.contains("Active Events").should("exist");
        cy.contains("Event Types").should("exist");
    });

    it("should have quick action buttons", () => {
        cy.visit("event/dashboard");
        cy.contains("Add Event").should("exist");
        cy.contains("Check-in").should("exist");
        cy.contains("Calendar").should("exist");
    });

    it("should have event type and year filters", () => {
        cy.visit("event/dashboard");
        cy.get("#WhichType").should("exist");
        cy.get("#WhichYear").should("exist");
        cy.get("#WhichType option").should("have.length.at.least", 1);
    });

    it("should filter dashboard by URL params", () => {
        cy.visit("event/dashboard?WhichYear=2024");
        cy.contains("Events Dashboard").should("exist");
        cy.url().should("include", "WhichYear=2024");
    });

    it("should have Manage Event Types button in header", () => {
        cy.visit("event/dashboard");
        cy.contains("Manage Event Types").should("exist");
    });

    it("Manage Event Types navigates to /event/types", () => {
        cy.visit("event/dashboard");
        cy.contains("Manage Event Types").click();
        cy.url().should("include", "/event/types");
    });
});
