/// <reference types="cypress" />

describe("Family finance fields (issue #10199)", () => {
    const fields = ["ScanCheck", "ScanCredit", "Envelope"];

    it("hides finance fields from a user without Finance", () => {
        cy.makePrivateNoFinanceAPICall("GET", "/api/family/1", null, 200).then((response) => {
            fields.forEach((field) => expect(response.body).not.to.have.property(field));
        });
    });

    it("shows finance fields to a user with Finance", () => {
        cy.makePrivateFinanceOnlyAPICall("GET", "/api/family/1", null, 200).then((response) => {
            fields.forEach((field) => expect(response.body).to.have.property(field));
        });
    });
});
