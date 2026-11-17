/// <reference types="cypress" />

describe("Floating action buttons under overlays (#10419)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.viewport(500, 900);
    });

    it("sit below an open modal's backdrop", () => {
        cy.visit("/people/reports/birthdays?month=7");
        cy.get("#printLabels").click();
        cy.get("#labelsModal").should("have.class", "show");
        cy.get(".modal-backdrop.show").then(($backdrop) => {
            const backdropZ = Number($backdrop.css("z-index"));
            cy.get("#fab-container").should(($fab) => {
                expect(Number($fab.css("z-index")), "FAB z-index").to.be.lessThan(backdropZ);
            });
        });
    });

    it("sit below an open offcanvas panel", () => {
        cy.visit("/event/calendars");
        cy.get('[data-bs-target="#calendarSidebar"]').click();
        cy.get("#calendarSidebar").should("have.class", "show").and("not.have.class", "showing");
        cy.get("#calendarSidebar").then(($panel) => {
            cy.get("#fab-container").should(($fab) => {
                expect(Number($fab.css("z-index")), "FAB z-index").to.be.lessThan(Number($panel.css("z-index")));
            });
        });
    });
});
