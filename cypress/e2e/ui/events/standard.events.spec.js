/// <reference types="cypress" />

describe("Standard User - Event Check-in", () => {
    beforeEach(() => cy.setupStandardSession());

    it("View Event Check-in via URL with event ID", () => {
        cy.visit("event/checkin/3");
        cy.contains("Event Check-in");
        // Direct event access shows event title in the info bar
        cy.get("body").should("contain.text", "Event:");
    });

    it("View Check-in page without event ID shows event selector", () => {
        cy.visit("event/checkin");
        cy.contains("Event Check-in");
        cy.contains("Select Event for Check-In");
    });

    it("Selecting an event shows the check-in form", () => {
        cy.visit("event/checkin");
        cy.get("#EventSelector").select(3);
        // Selecting an event navigates to /event/checkin/3
        cy.url().should("include", "/event/checkin/3");
        cy.contains("Check In Person");
    });

    it("Filter events by type dropdown exists", () => {
        cy.visit("event/checkin");
        cy.get("#EventTypeFilter").should("exist");
    });

    it("Walk-in check-in form has child and adult selectors", () => {
        cy.visit("event/checkin/3");
        cy.get("#child").should("exist");
        cy.get("#adult").should("exist");
        cy.get("#checkinBtn").should("exist");
    });

    /**
     * Regression test for the "Please select a person to check in" bug.
     *
     * The walk-in check-in handler was reading $("#child-id") to get the
     * person ID, but no such hidden input exists in the view — the value
     * lives directly on the TomSelect-wrapped <select id="child">.
     * The result: even after picking a person via TomSelect, the click
     * handler thought the field was empty and refused to submit.
     *
     * This test selects a person via TomSelect and intercepts the
     * /checkin POST to verify the request is made with the correct
     * personId payload (not blocked by the false-negative validation).
     */
    it("Walk-in check-in submits the selected person to /api/events/{id}/checkin", () => {
        cy.intercept("POST", "**/api/events/3/checkin").as("checkinPost");

        cy.visit("event/checkin/3");

        // Open the #child TomSelect dropdown and type to trigger the AJAX search
        cy.get("#child + .ts-wrapper .ts-control").click();
        cy.get("#child + .ts-wrapper input").type("Pa", { delay: 50 });

        // Pick the first person result that loads
        cy.get(".ts-dropdown .option", { timeout: 10000 })
            .first()
            .click();

        // The underlying <select id="child"> should now have a non-empty value
        cy.get("#child").invoke("val").should("not.be.empty");

        cy.get("#checkinBtn").click();

        // The POST should fire — no "Please select a person" warning
        cy.wait("@checkinPost").its("request.body").should((body) => {
            expect(body).to.have.property("personId");
            expect(body.personId).to.be.a("number").and.not.equal(0);
        });
    });
});
