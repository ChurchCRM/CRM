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

        it("should render filter chips and default to Notes only", () => {
            cy.get("#family-timeline-container .timeline-filters").should("be.visible");
            cy.get('#family-timeline-container .timeline-filter-chip[data-filter="notes"]').should(
                "have.class",
                "btn-primary",
            );
            cy.get('#family-timeline-container .timeline-filter-chip[data-filter="events"]').should(
                "have.class",
                "btn-outline-secondary",
            );
            cy.get('#family-timeline-container .timeline-filter-chip[data-filter="system"]').should(
                "have.class",
                "btn-outline-secondary",
            );
        });

        it("should hide non-note timeline events by default", () => {
            // Every visible event under the timeline must be tagged as notes.
            cy.get("#family-timeline-container .timeline-event:visible").each(($el) => {
                expect($el.attr("data-timeline-category")).to.equal("notes");
            });
        });

        it('should reveal every category when "Show all" is clicked', () => {
            cy.get("#family-timeline-container .timeline-filter-all").click();
            // If system/events rows exist, they should now be visible.
            cy.get("#family-timeline-container .timeline-event").each(($el) => {
                cy.wrap($el).should("be.visible");
            });
        });

        it("should hide the year divider when its events are filtered out", () => {
            // Toggle Notes off (no default categories active → JS falls back
            // to Notes) then toggle System on via click to compare visible
            // categories.
            cy.get('#family-timeline-container .timeline-filter-chip[data-filter="notes"]').click();
            cy.get('#family-timeline-container .timeline-filter-chip[data-filter="system"]').click();
            // Any visible timeline-year block must still have at least one
            // visible .timeline-event with a matching data-timeline-year.
            cy.get("#family-timeline-container .timeline-year:visible").each(($year) => {
                const yr = $year.attr("data-timeline-year");
                cy.get(`#family-timeline-container .timeline-event[data-timeline-year="${yr}"]:visible`).should(
                    "have.length.at.least",
                    1,
                );
            });
        });
    });

    describe("Person profile", () => {
        // `function()` (not arrow) so `this.skip()` works when the DB has
        // no people rows to visit.
        it("should default to Notes-only on the person timeline", function () {
            // PersonView uses the same .timeline-container class so the
            // shared init runs on the #timeline tab-pane.
            cy.request("/api/persons/latest").then((response) => {
                const people = (response.body && response.body.people) || [];
                const person = people[0];
                if (!person) {
                    this.skip();
                    return;
                }
                const personId = person.id || person.Id;
                cy.visit(`PersonView.php?PersonID=${personId}`);
                cy.get("#timeline.timeline-container .timeline-filters").should("exist");
                cy.get('#timeline .timeline-filter-chip[data-filter="notes"]').should("have.class", "btn-primary");
            });
        });
    });
});
