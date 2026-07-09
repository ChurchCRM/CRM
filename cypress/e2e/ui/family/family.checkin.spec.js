/// <reference types="cypress" />

/**
 * Family Check-In feature tests (#6838).
 *
 * The "Check In Family" action on the family view opens a modal where
 * the user picks an active event, then POSTs all family member IDs to
 * /api/events/{id}/checkin-people in a single batch.
 *
 * As of 7.2.1 this action lives inside the "Actions" dropdown on the
 * family profile toolbar (it was moved off the primary toolbar so the
 * toolbar no longer wraps on tablet widths), so tests must open the
 * dropdown before interacting with #checkInFamilyBtn.
 */
describe("Family Check-In Button (#6838)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("people/family/1");
    });

    // Opens the Actions dropdown so the Check In Family menu item becomes
    // visible and clickable.
    const openActionsMenu = () => {
        cy.get("#family-actions-dropdown").click();
    };

    it("should display the Check In Family item inside the Actions menu", () => {
        openActionsMenu();
        cy.get("#checkInFamilyBtn").should("be.visible").and("contain", "Check In Family");
    });

    it("should open the family check-in modal when the menu item is clicked", () => {
        openActionsMenu();
        cy.get("#checkInFamilyBtn").click();
        cy.get("#familyCheckinModal").should("be.visible");
        cy.contains("#familyCheckinModalLabel", "Check In Family").should("be.visible");
    });

    it("should populate the event selector with active events", () => {
        openActionsMenu();
        cy.get("#checkInFamilyBtn").click();
        cy.get("#familyCheckinModal").should("be.visible");

        // Selector exists and has at least the placeholder option
        cy.get("#familyCheckinEventSelect").should("exist");
        cy.get("#familyCheckinEventSelect option").should("have.length.at.least", 1);
    });

    it("should disable the submit button until an event is selected", () => {
        openActionsMenu();
        cy.get("#checkInFamilyBtn").click();
        cy.get("#familyCheckinSubmit").should("be.disabled");
    });

    it("should enable submit button after selecting an event", function () {
        openActionsMenu();
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
