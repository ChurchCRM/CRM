/// <reference types="cypress" />

/**
 * Test for GH #1697 - Person deletion redirect behavior
 *
 * Verifies that when a person is deleted from the PersonView page,
 * the user is redirected to the people list page instead of being
 * stuck on the not-found page.
 *
 * Fix: Changed CRMJSOM.js delete handler to redirect to /people/list
 *      instead of location.reload() which would show the not-found page.
 */
describe("Standard Person Delete", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Delete person and redirect to people list", () => {
        const uniqueSeed = Date.now().toString();
        const name = "DeleteTest " + uniqueSeed;

        // Create a temporary person with minimum required fields
        cy.visit("PersonEditor.php");
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type("Person");
        cy.get("#Gender").select("1");
        cy.get("#Classification").select("1");
        cy.get('button[name="PersonSubmit"]').click();

        // Verify we're on the person view page and capture the person ID
        cy.url().should("contain", "PersonView.php").then((url) => {
            const personId = new URL(url).searchParams.get("PersonID");
            expect(personId).to.be.ok;

            // Open Actions dropdown and click Delete
            cy.get("#person-actions-dropdown").first().click();
            cy.get("#deletePersonBtn").first().click();

            // Confirm deletion in bootbox modal
            cy.get(".bootbox-accept").first().click({ force: true });

            // After deletion, should redirect to the people list page
            cy.url({ timeout: 5000 }).should("include", "people/list");
            cy.location("pathname").should("not.include", "person/not-found");
            
            // Verify the deleted person is no longer in the list
            cy.get(".dt-search input").first().type(name);
            cy.get("#members tbody").should("not.contain", name);
        });
    });
});
