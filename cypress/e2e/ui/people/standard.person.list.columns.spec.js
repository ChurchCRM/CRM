/// <reference types="cypress" />

describe("People list column integrity (#9855)", () => {
    beforeEach(() => cy.setupStandardSession());

    it("every row has one cell per header", () => {
        cy.visit("/people/list");
        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);

        cy.get("#members thead tr")
            .first()
            .find("th")
            .its("length")
            .then((headers) => {
                cy.get("#members tbody tr").each(($row) => {
                    expect($row.children("td").length).to.eq(headers);
                });
            });
    });
});
