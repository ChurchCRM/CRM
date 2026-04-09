/// <reference types="cypress" />

/**
 * These tests exercise the /event/checkin MVC pages from a standard
 * (non-admin) user's perspective. They are SELF-SUFFICIENT — every
 * precondition (event type, active event) is created via the API at the
 * start of the suite so the tests don't depend on whatever happens to
 * be in the seed database.
 */
describe("Standard User - Event Check-in", () => {
    let testEventId;

    before(() => {
        // Create the event we'll use throughout this suite via the admin API
        // (the standard user can't create events). We grab the first event
        // type, quick-create an event under it, then capture its id.
        cy.setupAdminSession();
        cy.makePrivateAdminAPICall("GET", "/api/events/types", null, 200).then((typesResp) => {
            const types = Array.isArray(typesResp.body)
                ? typesResp.body
                : Object.values(typesResp.body);
            expect(types.length, "at least one event type must be seeded").to.be.greaterThan(0);
            expect(types[0]).to.have.property("Id");

            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId: types[0].Id },
                200,
            ).then((createResp) => {
                expect(createResp.body).to.have.property("eventId");
                testEventId = createResp.body.eventId;
            });
        });
    });

    beforeEach(() => cy.setupStandardSession());

    it("View Event Check-in via URL with event ID", () => {
        cy.visit(`event/checkin/${testEventId}`);
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

        // Select the event we created in `before()` explicitly by id —
        // no dependence on placeholder/option order.
        cy.get(`#EventSelector option[value="${testEventId}"]`).should("exist");
        cy.get("#EventSelector").select(String(testEventId));
        cy.url().should("include", `/event/checkin/${testEventId}`);
        cy.contains("Check In Person");
    });

    it("Filter events by type dropdown exists", () => {
        cy.visit("event/checkin");
        cy.get("#EventTypeFilter").should("exist");
    });

    /**
     * Regression: the EventTypeFilter change handler used to be bound INSIDE
     * an `if (!eventId) return;` guard, which meant it never fired on the
     * landing page (where the filter is the most useful). Selecting a type
     * should navigate the page and apply the URL param.
     */
    it("Selecting an event type filter navigates with EventTypeID param", () => {
        cy.visit("event/checkin");
        // Pick the first real event type (skip the "All Event Types" placeholder
        // whose value is "0"). At least one event type exists because before()
        // verified it.
        cy.get("#EventTypeFilter option").then(($options) => {
            const realType = [...$options].find((o) => o.value && o.value !== "0");
            expect(realType, "at least one real event type option must exist").to.exist;
            cy.get("#EventTypeFilter").select(realType.value);
            cy.url().should("include", `EventTypeID=${realType.value}`);
        });
    });

    it("Walk-in check-in form has child and adult selectors", () => {
        cy.visit(`event/checkin/${testEventId}`);
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
        cy.intercept("POST", `**/api/events/${testEventId}/checkin`).as("checkinPost");

        cy.visit(`event/checkin/${testEventId}`);

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
