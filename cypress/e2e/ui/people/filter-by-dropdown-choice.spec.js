describe("template spec", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("filter-by-dropdown-choice", () => {
        cy.visit("people/list?familyActiveStatus=all");

        // Wait for DataTable and TomSelect to initialize
        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
        cy.get(".filter-Custom").siblings(".ts-wrapper").should("exist");

        // Use the shared TomSelect helper to select the dropdown value
        cy.tomSelectByText('.filter-Custom', 'My Custom Drop Down List:My Custom Item 1');

        // Wait for table to update and assert filtered row
        cy.get('#members tbody tr', { timeout: 10000 }).should('have.length', 1);
        cy.get('#members tbody').should('contain', 'Mark');
        cy.get('#members tbody').should('contain', 'Smith');
    });
});
