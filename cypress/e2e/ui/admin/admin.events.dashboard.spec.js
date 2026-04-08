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

    describe("Stat cards data accuracy", () => {
        it("Event Types card shows total types, not types-with-events-this-year", () => {
            // Fetch the actual count via the API and assert the dashboard matches.
            cy.request("/api/events/types").then((apiResp) => {
                const apiCount = Array.isArray(apiResp.body) ? apiResp.body.length : 0;
                cy.visit("event/dashboard");
                cy.contains(".card", "Event Types").within(() => {
                    // Find the digit shown in the card and assert it equals API count
                    cy.get("h2, h3, .h2, .h3, .stat-value, .display-4").first().invoke("text").then((txt) => {
                        const shown = parseInt(txt.replace(/\D/g, ""), 10);
                        expect(shown).to.equal(apiCount);
                    });
                });
            });
        });

        it("event title row does not render Quill empty placeholder (<p><br /></p>)", () => {
            cy.visit("event/dashboard");
            // The literal markup must NEVER appear as text under any event row
            cy.get("table tbody").should("not.contain.text", "<p>");
            cy.get("table tbody").should("not.contain.text", "<br />");
        });
    });

    describe("Inactive event guards", () => {
        it("shows a warning banner on /event/checkin/{id} for an inactive event", () => {
            // Find or create an inactive event by deactivating one via the API
            cy.request("/api/events").then((listResp) => {
                const events = listResp.body.Events || listResp.body || [];
                if (!events.length) return;
                const eventId = events[0].Id;

                // Force-deactivate via the status endpoint
                cy.request({
                    method: "POST",
                    url: `/api/events/${eventId}/status`,
                    body: { active: false },
                });

                cy.visit(`event/checkin/${eventId}`);

                // The walk-in form should NOT be present
                cy.get("#checkinBtn").should("not.exist");
                // The inactive warning banner should be visible
                cy.contains("This event is inactive").should("be.visible");

                // Re-activate so other tests aren't affected
                cy.request({
                    method: "POST",
                    url: `/api/events/${eventId}/status`,
                    body: { active: true },
                });
            });
        });

        it("API rejects check-in to inactive event with 409", () => {
            cy.request("/api/events").then((listResp) => {
                const events = listResp.body.Events || listResp.body || [];
                if (!events.length) return;
                const eventId = events[0].Id;

                cy.request({ method: "POST", url: `/api/events/${eventId}/status`, body: { active: false } });

                cy.request({
                    method: "POST",
                    url: `/api/events/${eventId}/checkin`,
                    body: { personId: 1 },
                    failOnStatusCode: false,
                }).then((resp) => {
                    expect(resp.status).to.eq(409);
                });

                // Restore
                cy.request({ method: "POST", url: `/api/events/${eventId}/status`, body: { active: true } });
            });
        });
    });

    describe("Event action menu", () => {
        // The DemoData service does not seed events, so a fresh test database
        // has zero events on the dashboard. Create one via quick-create before
        // running any of the action-menu assertions so the table tbody exists.
        beforeEach(() => {
            // /api/events/types returns a Propel ObjectCollection serialized as
            // an OBJECT keyed by index ("0", "1", ...) — not a true JS array.
            // Normalize via Object.values() before reading [0].Id.
            cy.makePrivateAdminAPICall("GET", "/api/events/types", null, [200, 404]).then(
                (typesResp) => {
                    if (typesResp.status !== 200 || !typesResp.body) {
                        return;
                    }
                    const types = Array.isArray(typesResp.body)
                        ? typesResp.body
                        : Object.values(typesResp.body);
                    if (types.length === 0 || !types[0] || !types[0].Id) {
                        return;
                    }
                    cy.makePrivateAdminAPICall(
                        "POST",
                        "/api/events/quick-create",
                        { eventTypeId: types[0].Id },
                        200,
                    );
                },
            );
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

            // Find an event currently marked Active
            cy.get("table tbody tr", { timeout: 10000 })
                .contains(".badge", "Active")
                .first()
                .parents("tr")
                .within(() => {
                    cy.get(".event-action-menu-placeholder .dropdown button[data-bs-toggle='dropdown']")
                        .click({ force: true });
                });

            cy.get(".dropdown-menu.show").contains("Deactivate").click();

            cy.wait("@status").then(({ request, response }) => {
                expect(response.statusCode).to.eq(200);
                expect(request.body).to.deep.equal({ active: false });
            });
        });

        it("Activate POSTs /api/events/{id}/status with active=true (when an inactive event exists)", () => {
            cy.intercept("POST", "**/api/events/*/status").as("status");
            cy.visit("event/dashboard");

            cy.get("table tbody tr", { timeout: 10000 }).then(($rows) => {
                const $inactive = $rows.filter((_, r) => Cypress.$(r).find(".badge:contains('Inactive')").length > 0);
                if ($inactive.length === 0) {
                    // No inactive events to activate — nothing to assert here
                    return;
                }
                cy.wrap($inactive.first()).within(() => {
                    cy.get(".event-action-menu-placeholder .dropdown button[data-bs-toggle='dropdown']")
                        .click({ force: true });
                });
                cy.get(".dropdown-menu.show").contains("Activate").click();
                cy.wait("@status").then(({ request, response }) => {
                    expect(response.statusCode).to.eq(200);
                    expect(request.body).to.deep.equal({ active: true });
                });
            });
        });
    });
});
