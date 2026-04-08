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

    describe("Event action menu", () => {
        it("renders the standard action dropdown for each event row", () => {
            cy.visit("event/dashboard");
            // Wait for the action menu to be hydrated by JS
            cy.get(".event-action-menu-placeholder .dropdown", { timeout: 10000 })
                .should("have.length.at.least", 1);
        });

        it("event title link navigates to the event editor", () => {
            cy.visit("event/dashboard");
            cy.get("table tbody tr td:first-child a").first().then(($link) => {
                const href = $link.attr("href");
                expect(href).to.include("/event/editor/");
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
            cy.visit("event/dashboard");
            cy.intercept("POST", "**/api/events/*/status").as("status");

            // Find an event currently marked Active
            cy.get("table tbody tr").contains(".badge", "Active").first().parents("tr").within(() => {
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
            cy.visit("event/dashboard");
            cy.intercept("POST", "**/api/events/*/status").as("status");

            cy.get("table tbody tr").then(($rows) => {
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
