describe("People classification filters", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("applies the Classification URL filter on initial load", () => {
        // Load with a classification filter in the URL (regression for #8208)
        cy.visit("v2/people?Classification=1&familyActiveStatus=all");

        cy.url().should("include", "/v2/people?Classification=1");

        // TomSelect should show the selected classification in the control
        cy.get(".filter-Classification")
            .siblings(".ts-wrapper")
            .find(".ts-control .item")
            .should("contain", "Member");

        // Grid remains loaded after initial filter application and shows filtered results
        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
    });

    it("applies Classification query filter on initial load", () => {
        cy.visit("v2/people?Classification=1&familyActiveStatus=all");

        cy.url().should("include", "Classification=1");
        cy.get(".filter-Classification")
            .siblings(".ts-wrapper")
            .find(".ts-control .item")
            .should("contain", "Member");

        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
    });
});
