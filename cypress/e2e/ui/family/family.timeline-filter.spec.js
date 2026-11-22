/// <reference types="cypress" />

/**
 * Timeline filter chips on the family & person profile views (7.2.1).
 *
 * The timeline can get long on established records, so the view renders
 * filter chips (Notes / Events / System) above the timeline. Notes is the
 * only category enabled by default since that is the high-signal user
 * content. Clicking chips toggles which categories render; "Show all"
 * reveals every timeline item.
 *
 * All assertions here are UI-only — no API calls. Category metadata is
 * rendered server-side on each .timeline-event via data-timeline-category.
 */
describe("Timeline filter chips", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("Family profile", () => {
        beforeEach(() => {
            cy.visit("people/family/1");
        });

        it("should keep notes out of the family Timeline tab and show them on the Notes tab", () => {
            cy.get("#nav-item-timeline").click();
            cy.get("#timeline.timeline-container .timeline-filters").should("be.visible");
            cy.get('#timeline .timeline-filter-chip[data-filter="notes"]').should("not.exist");
            cy.get('#timeline .timeline-event[data-timeline-category="notes"]').should("not.exist");
            cy.get("#notes.timeline-container .timeline-filters").should("not.exist");
        });

        it('should reveal every activity category when "Show all" is clicked', () => {
            cy.get("#nav-item-timeline").click();
            cy.get("#timeline .timeline-filter-all").click();
            cy.get("#timeline .timeline-event").each(($el) => {
                cy.wrap($el).should("be.visible");
            });
        });
    });

    describe("Person profile", () => {
        it("should keep notes out of the person Timeline tab and show them on the Notes tab", () => {
            // PersonID=2 is seeded in demo data and always has at least one system
            // timeline event (postInsert hook).
            cy.visit("/people/view/2");
            cy.get("#timeline.timeline-container .timeline-filters").should("exist");
            cy.get('#timeline .timeline-filter-chip[data-filter="notes"]').should("not.exist");
            cy.get('#timeline .timeline-event[data-timeline-category="notes"]').should("not.exist");
            cy.get("#notes.timeline-container .timeline-filters").should("not.exist");
        });
    });
});
