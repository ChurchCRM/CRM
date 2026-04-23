/// <reference types="cypress" />

/**
 * Helper — quick-create a fresh event using the seeded "Church Service"
 * event type (id 1) and return its id via a callback. Centralized so
 * every test in this file can guarantee the dashboard has at least one
 * row to assert against.
 *
 * Tests in this file are SELF-SUFFICIENT — they never depend on seed
 * data being populated, only on the seeded event types being present
 * (which the other passing event specs also rely on).
 */
function createTestEvent(callback) {
    cy.makePrivateAdminAPICall(
        "POST",
        "/api/events/quick-create",
        { eventTypeId: 1 },
        200,
    ).then((createResp) => {
        expect(createResp.body).to.have.property("eventId");
        callback(createResp.body.eventId);
    });
}

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
        cy.contains("Add Recurring Event").should("exist");
        cy.contains("Check-in").should("exist");
        cy.contains("Calendar").should("exist");
    });

    /**
     * Issue #8658 comment: users asked for a button to create a Recurring
     * Event alongside the "Add Event" button — previously only surfaced in
     * the empty-state message, so volunteers with any existing events
     * couldn't find it. Asserts the button exists and points at the
     * repeat-editor route.
     */
    it("Add Recurring Event button links to /event/repeat-editor", () => {
        cy.visit("event/dashboard");
        cy.contains("a", "Add Recurring Event")
            .should("have.attr", "href")
            .and("match", /\/event\/repeat-editor$/);
    });

    it("should have event type and year filters", () => {
        cy.visit("event/dashboard");
        cy.get("#type").should("exist");
        cy.get("#year").should("exist");
        cy.get("#type option").should("have.length.at.least", 1);
    });

    it("should filter dashboard by URL params", () => {
        cy.visit("event/dashboard?year=2024");
        cy.contains("Events Dashboard").should("exist");
        cy.url().should("include", "year=2024");
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

    describe("Stat cards data accuracy", () => {
        it("Event Types card shows total types, not types-with-events-this-year", () => {
            // Fetch the actual count via the API and assert the dashboard matches.
            // /api/events/types returns { EventTypes: [...] } — read the
            // wrapped array, NOT the raw response body.
            cy.makePrivateAdminAPICall("GET", "/api/events/types", null, 200).then((apiResp) => {
                expect(apiResp.body).to.have.property("EventTypes");
                const apiCount = apiResp.body.EventTypes.length;
                cy.setupAdminSession({ forceLogin: true });
                cy.visit("event/dashboard");

                // The view renders the count as a plain <div class="fw-medium">
                // inside the Event Types card-body — not as <h2>/<h3>. Match the
                // stat label text and read the digit from its sibling .fw-medium.
                cy.contains(".card-body", "Event Types").within(() => {
                    cy.get(".fw-medium").first().invoke("text").then((txt) => {
                        const shown = parseInt(txt.replace(/\D/g, ""), 10);
                        expect(shown).to.equal(apiCount);
                    });
                });
            });
        });

        it("event title row does not render Quill empty placeholder (<p><br /></p>)", () => {
            // Ensure at least one row exists so the assertion is meaningful.
            createTestEvent(() => {
                cy.setupAdminSession({ forceLogin: true });
                cy.visit("event/dashboard");
                // The literal markup must NEVER appear as text under any event row
                cy.get("table tbody").should("not.contain.text", "<p>");
                cy.get("table tbody").should("not.contain.text", "<br />");
            });
        });
    });

    describe("Inactive event guards", () => {
        it("shows a warning banner on /event/checkin/{id} for an inactive event", () => {
            // Create our own event, deactivate it, verify the banner.
            createTestEvent((eventId) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${eventId}/status`,
                    { active: false },
                    200,
                );
                cy.setupAdminSession({ forceLogin: true });

                cy.visit(`event/checkin/${eventId}`);

                // The walk-in form should NOT be present
                cy.get("#checkinBtn").should("not.exist");
                // The inactive warning banner should be visible
                cy.contains("This event is inactive").should("be.visible");
            });
        });

        it("API rejects check-in to inactive event with 409", () => {
            createTestEvent((eventId) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${eventId}/status`,
                    { active: false },
                    200,
                );

                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${eventId}/checkin`,
                    { personId: 1 },
                    409,
                );
            });
        });
    });

    describe("Event action menu", () => {
        // Each test creates its own event so the dashboard table tbody is
        // always populated. We can't rely on seed data — DemoData does not
        // seed any events, and tests within this suite shouldn't share state.
        let testEventId;

        beforeEach(() => {
            createTestEvent((id) => {
                testEventId = id;
            });
            // After API calls the PHP session can be reset — re-establish admin session
            cy.setupAdminSession({ forceLogin: true });
        });

        it("renders the standard action dropdown for each event row", () => {
            cy.visit("event/dashboard");
            // Wait for the action menu to be hydrated by JS
            cy.get(".event-action-menu-placeholder .dropdown", { timeout: 10000 })
                .should("have.length.at.least", 1);
        });

        it("event title link navigates to the read-only event view page", () => {
            cy.visit("event/dashboard");
            cy.get("table tbody tr td:first-child a", { timeout: 10000 }).first().then(($link) => {
                const href = $link.attr("href");
                expect(href).to.include("/event/view/");
            });
        });

        it("dropdown menu has View, Edit, Check-in, Deactivate, Delete items", () => {
            cy.visit("event/dashboard");
            cy.get(".event-action-menu-placeholder .dropdown button[data-bs-toggle='dropdown']", { timeout: 10000 })
                .first()
                .click({ force: true });
            cy.get(".dropdown-menu.show").within(() => {
                cy.contains("View").should("exist");
                cy.contains("Edit").should("exist");
                cy.contains("Check-in").should("exist");
                // For an active event the toggle says Deactivate
                cy.contains(/Deactivate|Activate/).should("exist");
                cy.contains("Delete").should("exist");
            });
        });

        it("Deactivate POSTs /api/events/{id}/status with active=false", () => {
            cy.intercept("POST", "**/api/events/*/status").as("status");
            cy.visit("event/dashboard");

            // Find the event we just created and deactivate it via the menu
            cy.get(`.event-action-menu-placeholder[data-event-id="${testEventId}"]`, { timeout: 10000 })
                .within(() => {
                    cy.get(".dropdown button[data-bs-toggle='dropdown']").click({ force: true });
                });

            cy.get(".dropdown-menu.show").contains("Deactivate").click();

            cy.wait("@status").then(({ request, response }) => {
                expect(response.statusCode).to.eq(200);
                expect(request.body).to.deep.equal({ active: false });
            });
        });

        it("Activate POSTs /api/events/{id}/status with active=true", () => {
            // First deactivate the test event so the action menu shows "Activate"
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${testEventId}/status`,
                { active: false },
                200,
            );
            cy.setupAdminSession({ forceLogin: true });

            cy.intercept("POST", "**/api/events/*/status").as("status");
            cy.visit("event/dashboard");

            cy.get(`.event-action-menu-placeholder[data-event-id="${testEventId}"]`, { timeout: 10000 })
                .within(() => {
                    cy.get(".dropdown button[data-bs-toggle='dropdown']").click({ force: true });
                });

            cy.get(".dropdown-menu.show").contains("Activate").click();

            cy.wait("@status").then(({ request, response }) => {
                expect(response.statusCode).to.eq(200);
                expect(request.body).to.deep.equal({ active: true });
            });
        });
    });
});
