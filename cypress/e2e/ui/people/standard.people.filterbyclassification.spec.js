describe("People classification filters", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("applies the Classification URL filter on initial load", () => {
        // Load with a classification filter in the URL (regression for #8208)
        cy.visit("people/list?Classification=1&familyActiveStatus=all");

        cy.url().should("include", "/people/list?Classification=1");

        // TomSelect should show the selected classification in the control
        cy.get(".filter-Classification")
            .siblings(".ts-wrapper")
            .find(".ts-control .item")
            .should("contain", "Member");

        // Grid remains loaded after initial filter application and shows filtered results
        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
    });

    it("applies Classification query filter on initial load", () => {
        cy.visit("people/list?Classification=1&familyActiveStatus=all");

        cy.url().should("include", "Classification=1");
        cy.get(".filter-Classification")
            .siblings(".ts-wrapper")
            .find(".ts-control .item")
            .should("contain", "Member");

        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
    });

    // Regression test for issue #9182: OptionId 5 (Non-Attender) was previously mapped
    // by positional index, causing it to select the wrong option when OptionId != position.
    it("correctly selects Non-Attender (OptionId=5) from URL param — regression #9182", () => {
        cy.visit("people/list?Classification=5&familyActiveStatus=all");

        cy.url().should("include", "Classification=5");

        // TomSelect must show "Non-Attender", NOT the 5th positional entry "Non-Attender (staff)".
        // Note: remove_button plugin appends × inside .item, so have.text won't match — use contain.
        // The .and("not.contain", "(staff)") guard is what catches the wrong-option bug.
        cy.get(".filter-Classification")
            .siblings(".ts-wrapper")
            .find(".ts-control .item")
            .should("contain", "Non-Attender")
            .and("not.contain", "(staff)");
    });

    it("filter-by-dropdown-choice filters table to matching row", () => {
        cy.visit("people/list?familyActiveStatus=all");

        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
        cy.get(".filter-Custom").siblings(".ts-wrapper").should("exist");

        cy.tomSelectByText(".filter-Custom", "My Custom Drop Down List:My Custom Item 1");

        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length", 1);
        cy.get("#members tbody").should("contain", "Mark");
        cy.get("#members tbody").should("contain", "Smith");
    });
});
