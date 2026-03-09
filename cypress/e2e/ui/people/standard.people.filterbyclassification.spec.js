describe("People classification filters", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("applies the Classification URL filter on initial load", () => {
        // Load with a classification filter in the URL (regression for #8208)
        cy.visit("v2/people?Classification=1&familyActiveStatus=all");

        cy.url().should("include", "/v2/people?Classification=1");

        // The hidden select should be initialized from the URL value
        cy.get(".filter-Classification", { timeout: 10000 }).should("have.value", "1");

        // And the Select2 label should show the selected classification
        cy.get(".filter-Classification")
            .parent()
            .find(".select2-selection__rendered")
            .should("contain", "Member");

        // Grid remains loaded after initial filter application
        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
    });

    it("applies Classification query filter on initial load", () => {
        cy.visit("v2/people?Classification=1&familyActiveStatus=all");

        cy.url().should("include", "Classification=1");
        cy.get(".filter-Classification")
            .siblings(".select2-container")
            .find(".select2-selection__rendered")
            .should("contain", "Member");

        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
    });
});
