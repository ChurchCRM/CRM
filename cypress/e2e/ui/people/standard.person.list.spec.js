/// <reference types="cypress" />

describe("Standard People", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Person Not Found", () => {
        cy.visit("PersonView.php?PersonID=9999");
        cy.location("pathname").should("include", "person/not-found");
        // New UX: show standard error title and message
        cy.contains("Person not found");
        cy.contains("We could not find the person you were looking for.");
        // New UX: clear button to return to listing
        cy.get('a.btn').contains('Return to People').should('exist');
        // ID should be shown on page
        cy.contains('9999').should('exist');
        // Clicking report should open the issue modal
        cy.get('#errorReportBtn').click();
        cy.get('#IssueReportModal').should('be.visible');
        // pageName input should contain the not-found URL
        cy.get('input[name="pageName"]').invoke('val').should('include', '/people/person/not-found');
    });

    it("Listing all persons", () => {
        cy.visit("people/list");

        // Wait for DataTable to initialize
        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);

        // Search for Admin
        cy.get(".dt-search input").first().type("Admin");
        cy.get("#members tbody").contains("Admin").should("exist");

        // Clear and search for Joel
        cy.get(".dt-search input").first().clear().type("Joel");
        cy.get("#members tbody").contains("Joel").should("exist");

        // Clear and search for Emma
        cy.get(".dt-search input").first().clear().type("Emma");
        cy.get("#members tbody").contains("Emma").should("exist");
    });

    it("Listing all persons with gender url filter", () => {
        cy.visit("people/list?Gender=0");

        // Wait for DataTable to initialize
        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);

        // Search for Admin (male)
        cy.get(".dt-search input").first().type("Admin");
        cy.get("#members tbody").contains("Admin").should("exist");

        // Clear and search for Kennedy (male)
        cy.get(".dt-search input").first().clear().type("Kennedy");
        cy.get("#members tbody").contains("Kennedy").should("exist");

        // Clear search and verify no female entries appear in the filtered results
        cy.get(".dt-search input").first().clear().type("Emma");
        cy.get("#members tbody").should("not.contain", "Female");
    });

    it("Multiple filter combinations", () => {
        cy.visit("people/list");

        // Wait for DataTable and TomSelect to initialize
        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
        cy.get(".filter-Gender").siblings(".ts-wrapper").should("exist");

        // Apply gender filter using TomSelect
        cy.get(".filter-Gender").siblings(".ts-wrapper").find(".ts-control").click();
        cy.get(".filter-Gender").siblings(".ts-wrapper").find(".ts-dropdown").should("be.visible");
        cy.get(".filter-Gender").siblings(".ts-wrapper").find(".ts-dropdown .ts-dropdown-content .option").contains("Female").click();

        // Apply classification filter using TomSelect
        cy.get(".filter-Classification").siblings(".ts-wrapper").find(".ts-control").click();
        cy.get(".filter-Classification").siblings(".ts-wrapper").find(".ts-dropdown").should("be.visible");
        cy.get(".filter-Classification").siblings(".ts-wrapper").find(".ts-dropdown .ts-dropdown-content .option").contains("Member").click();

        // Table should show filtered results
        cy.get("#members tbody tr", { timeout: 5000 }).should("have.length.greaterThan", 0);

        // Clear all filters
        cy.get("#ClearFilter").click();

        // Verify filters are cleared
        cy.get(".filter-Gender").siblings(".ts-wrapper").find(".ts-control .item").should("not.exist");
    });

    it("Add individual person to cart", () => {
        cy.request({
            method: "DELETE",
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({}),
        });

        cy.visit("people/list");
        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);

        cy.get("#members tbody tr:first").within(() => {
            cy.get('[data-bs-toggle="dropdown"], .dropdown-toggle').first().click();
        });
        cy.get(".dropdown-menu.show .AddToCart").first().click({ force: true });

        cy.request({ method: "GET", url: "/api/cart/" }).then((resp) => {
            expect(resp.status).to.eq(200);
            if (resp.body.Persons) {
                expect(resp.body.Persons.length).to.be.greaterThan(0);
            } else if (resp.body.PeopleCart) {
                expect(resp.body.PeopleCart.length).to.be.greaterThan(0);
            } else {
                expect(Object.keys(resp.body).length).to.be.greaterThan(0);
            }
        });
    });
});
