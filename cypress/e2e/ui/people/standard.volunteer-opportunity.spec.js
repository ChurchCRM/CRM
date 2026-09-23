/// <reference types="cypress" />

/**
 * Test for GitHub Issue #7917: Bug assigning Volunteer Opportunities
 * @see https://github.com/ChurchCRM/CRM/issues/7917
 *
 * This test verifies that volunteer opportunities can be assigned to people
 * without causing a blank page / BadMethodCallException error.
 *
 * Fixture data (#9729): a fresh install seeds zero rows in
 * volunteeropportunity_vol, and person-view.php only renders the assignment
 * form when at least one opportunity exists. The suite therefore creates one
 * through /api/volunteer-opportunities in `before` and deletes it in `after`,
 * so the assign/remove round-trip is actually reachable in CI.
 *
 * Control shape (#9729): the assignment control is a TomSelect-enhanced
 * `<select multiple name="VolunteerOpportunityIDs[]">` and the submit control
 * is a `<button name="VolunteerOpportunityAssign">` — not the checkboxes /
 * input the original spec guarded on, which is why it silently skipped.
 */

const PERSON_ID = 1;
const PERSON_VIEW_URL = `/people/view/${PERSON_ID}`;
// volunteeropportunity_vol.vol_Name is capped at 30 chars by the API
const OPPORTUNITY_NAME = "Cypress Sound Desk";
const OPPORTUNITY_DESC = "Created by standard.volunteer-opportunity.spec.js";
const VOLUNTEER_SELECT = '#volunteer select[name="VolunteerOpportunityIDs[]"]';

describe("Volunteer Opportunity Assignment - Issue #7917", () => {
    /** @type {number} */
    let opportunityId;

    const adminApiHeaders = () => ({ "x-api-key": Cypress.env("admin.api.key") });

    before(() => {
        // Remove any leftover opportunity from an interrupted earlier run so the
        // "That name already exists." guard cannot make this suite flaky.
        cy.request({
            method: "GET",
            url: "/api/volunteer-opportunities",
            headers: adminApiHeaders(),
        }).then((listRes) => {
            const stale = listRes.body.volunteerOpportunities.filter((opp) => opp.name === OPPORTUNITY_NAME);
            stale.forEach((opp) => {
                cy.request({
                    method: "DELETE",
                    url: `/api/volunteer-opportunities/${opp.id}`,
                    headers: adminApiHeaders(),
                    failOnStatusCode: false,
                });
            });
        });

        cy.request({
            method: "POST",
            url: "/api/volunteer-opportunities",
            headers: adminApiHeaders(),
            body: { name: OPPORTUNITY_NAME, description: OPPORTUNITY_DESC, active: true },
        }).then((res) => {
            expect(res.status).to.eq(201);
            opportunityId = res.body.volunteerOpportunity.id;
            expect(opportunityId, "seeded volunteer opportunity id").to.be.greaterThan(0);
        });
    });

    after(() => {
        if (!opportunityId) {
            return;
        }
        // Drop any assignment a failed test may have left behind — the API
        // refuses (409) to delete an opportunity that is still assigned.
        cy.setupAdminSession();
        cy.request({
            url: `${PERSON_VIEW_URL}?RemoveVO=${opportunityId}`,
            failOnStatusCode: false,
        });
        cy.request({
            method: "DELETE",
            url: `/api/volunteer-opportunities/${opportunityId}`,
            headers: adminApiHeaders(),
        })
            .its("status")
            .should("eq", 200);
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should assign a volunteer opportunity without error", () => {
        cy.visit(PERSON_VIEW_URL);
        cy.contains("Person Profile");

        // Open the Volunteer tab (use specific id to avoid sidebar matches)
        cy.get("#nav-item-volunteer").click();
        cy.get("#volunteer").should("be.visible");

        // The assignment form must be rendered — the seeded opportunity above
        // guarantees count($allVolunteerOppsData) > 0.
        cy.get(VOLUNTEER_SELECT).should("exist");
        cy.get('#volunteer button[name="VolunteerOpportunityAssign"]').should("exist");

        // Drive the real TomSelect control, not the hidden <select>.
        cy.tomSelectIsInitialized(VOLUNTEER_SELECT);
        cy.tomSelectByText(VOLUNTEER_SELECT, OPPORTUNITY_NAME);
        cy.tomSelectGetSelected(VOLUNTEER_SELECT).should("contain.text", OPPORTUNITY_NAME);

        // Close the still-open TomSelect dropdown; its "No results" panel
        // overlays the submit button underneath it.
        cy.get(VOLUNTEER_SELECT).then(($select) => {
            $select[0].tomselect.close();
            $select[0].tomselect.blur();
        });

        cy.get('#volunteer button[name="VolunteerOpportunityAssign"]').click();

        // The POST handler redirects back to the person view — #7917 was a
        // BadMethodCallException that produced a blank page here instead.
        cy.url().should("contain", `people/view/${PERSON_ID}`);
        cy.contains("Person Profile");
        cy.get("body").should("not.contain", "BadMethodCallException");
        cy.get("body").should("not.contain", "Fatal error");

        // The assignment must now be listed on the Volunteer tab.
        cy.get("#nav-item-volunteer").click();
        cy.get("#volunteer").should("be.visible");
        cy.get("#volunteer table tbody").should("contain.text", OPPORTUNITY_NAME);
        cy.get(`#volunteer a[href*="RemoveVO=${opportunityId}"]`).should("exist");

        // The opportunity is assigned, so it must no longer be offered for assignment.
        cy.get(VOLUNTEER_SELECT).find(`option[value="${opportunityId}"]`).should("not.exist");

        // Remove it again and assert the round-trip actually unwound.
        cy.get(`#volunteer a[href*="RemoveVO=${opportunityId}"]`).first().click();
        cy.url().should("contain", `people/view/${PERSON_ID}`);
        cy.get("#nav-item-volunteer").click();
        cy.get("#volunteer").should("be.visible");
        cy.get(`#volunteer a[href*="RemoveVO=${opportunityId}"]`).should("not.exist");
        cy.get("#volunteer").should("contain.text", "No volunteer opportunity assignments yet.");
        // ...and it is offered for assignment again.
        cy.get(VOLUNTEER_SELECT).find(`option[value="${opportunityId}"]`).should("exist");
    });

    it("should display volunteer tab content without errors", () => {
        // Visit a person's profile page
        cy.visit(PERSON_VIEW_URL);
        cy.contains("Person Profile");

        // Click on the Volunteer tab
        cy.get("#nav-item-volunteer").click();

        // Volunteer tab should be visible and functional
        cy.get("#volunteer").should("be.visible");

        // Should not have any PHP errors on page
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "BadMethodCallException");
    });
});
