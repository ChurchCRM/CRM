describe("template spec", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("filter-by-dropdown-choice", () => {
        cy.visit("v2/people?familyActiveStatus=all");

        // Wait for DataTable and TomSelect to initialize
        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
        cy.get(".filter-Custom").siblings(".ts-wrapper").should("exist");

        // Click the TomSelect control for the Custom filter and type selection
        cy.get(".filter-Custom").siblings(".ts-wrapper").find(".ts-control").click();
        cy.get(".filter-Custom").siblings(".ts-wrapper").find(".ts-control input").type("My Custom Drop Down List:My Custom Item 1{enter}");

        cy.contains("Showing 1 to 1 of 1 entries");
        // Verify the filtered result shows Mark Smith (person 104)
        cy.get("#members tbody tr").should("have.length", 1);
        cy.get("#members tbody").should("contain", "Mark");
        cy.get("#members tbody").should("contain", "Smith");
    });
});
