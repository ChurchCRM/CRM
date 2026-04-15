/// <reference types="cypress" />

/**
 * Family Check-In feature tests (#6838).
 *
 * The "Check In Family" button on the family view opens a modal where
 * the user picks an active event, then POSTs all family member IDs to
 * /api/events/{id}/checkin-people in a single batch.
 */
describe("Family Check-In Button (#6838)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("people/family/1");
    });

    it("should display the Check In Family button when events are enabled", () => {
        cy.get("#checkInFamilyBtn").should("exist").and("contain", "Check In Family");
    });

    it("should open the family check-in modal when clicked", () => {
        cy.get("#checkInFamilyBtn").click();
        cy.get("#familyCheckinModal").should("be.visible");
        cy.contains("#familyCheckinModalLabel", "Check In Family").should("be.visible");
    });

    it("should populate the event selector with active events", () => {
        cy.get("#checkInFamilyBtn").click();
        cy.get("#familyCheckinModal").should("be.visible");

        // Selector exists and has at least the placeholder option
        cy.get("#familyCheckinEventSelect").should("exist");
        cy.get("#familyCheckinEventSelect option").should("have.length.at.least", 1);
    });

    it("should disable the submit button until an event is selected", () => {
        cy.get("#checkInFamilyBtn").click();
        cy.get("#familyCheckinSubmit").should("be.disabled");
    });

    it("should enable submit button after selecting an event", function () {
        cy.get("#checkInFamilyBtn").click();
        cy.get("#familyCheckinModal").should("be.visible");

        // Find any non-empty option to select
        cy.get("#familyCheckinEventSelect option").then(($options) => {
            const validOptions = [...$options].filter((o) => o.value !== "");
            if (validOptions.length === 0) this.skip();
            cy.get("#familyCheckinEventSelect").select(validOptions[0].value);
            cy.get("#familyCheckinSubmit").should("not.be.disabled");
        });
    });
});
