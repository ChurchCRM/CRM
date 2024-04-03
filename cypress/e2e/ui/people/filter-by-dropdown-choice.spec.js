describe("template spec", () => {
    it("filter-by-dropdown-choice", () => {
        cy.loginAdmin("v2/people?familyActiveStatus=all");
        cy.get('*[placeholder="Select Custom"]')
            .click()
            .type("My Custom Drop Down List:My Custom Item 1\n");
        cy.contains("Showing 1 to 1 of 1 entries");
        cy.contains("mark.smith@example.com");
    });
});
