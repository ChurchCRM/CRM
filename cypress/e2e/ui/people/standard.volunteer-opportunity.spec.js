/// <reference types="cypress" />

/**
 * Test for GitHub Issue #7917: Bug assigning Volunteer Opportunities
 * @see https://github.com/ChurchCRM/CRM/issues/7917
 * 
 * This test verifies that volunteer opportunities can be assigned to people
 * without causing a blank page / BadMethodCallException error.
 */
describe("Volunteer Opportunity Assignment - Issue #7917", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should assign a volunteer opportunity without error", () => {
        // Visit a person's profile page
        cy.visit("PersonView.php?PersonID=1");
        cy.contains("Person Profile");

        // Click on the Volunteer tab in the profile (use specific id to avoid sidebar matches)
        cy.get('#nav-item-volunteer').click();
        cy.get('#volunteer').should('be.visible');


        // Check if there are volunteer opportunities to assign
        cy.get('#volunteer').then(($volunteerTab) => {
            // Look for the volunteer opportunity select/checkbox elements
            if ($volunteerTab.find('input[name="VolunteerOpportunityIDs[]"]').length > 0) {
                // Get an unassigned opportunity checkbox
                cy.get('input[name="VolunteerOpportunityIDs[]"]:not(:checked)').first().then(($checkbox) => {
                    if ($checkbox.length > 0) {
                        const opportunityId = $checkbox.val();

                        // Check the opportunity
                        cy.wrap($checkbox).check({ force: true });

                        // Submit the form - try multiple possible submit controls to be resilient
                        cy.get('input[name="VolunteerOpportunityAssign"]').then(($btn) => {
                            if ($btn.length) {
                                cy.wrap($btn).click();
                            } else {
                                cy.contains('button', /assign/i).click();
                            }
                        });

                        // Should not show an error - page should reload successfully
                        cy.url().should('contain', 'PersonView.php?PersonID=1');
                        cy.contains('Person Profile');

                        // The opportunity should now be assigned (listed in assigned section)
                        cy.get('#nav-item-volunteer').click();
                        cy.get('#volunteer').should('be.visible');

                        // Clean up: Remove the assigned opportunity (look for remove button by href)
                        cy.get(`a[href*="RemoveVO=${opportunityId}"]`).first().click();
                        cy.url().should('contain', 'PersonView.php?PersonID=1');
                    } else {
                        cy.log('No unassigned volunteer opportunities available to test');
                    }
                });
            } else {
                cy.log('No volunteer opportunities configured in the system');
            }
        });
    });

    it("should display volunteer tab content without errors", () => {
        // Visit a person's profile page
        cy.visit("PersonView.php?PersonID=1");
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
