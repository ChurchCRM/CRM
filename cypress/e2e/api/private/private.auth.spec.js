/// <reference types="cypress" />

context("API Private wihouth Auth", () => {
    it("Basic Rejcet", () => {
        cy.request({
            method: "GET",
            url: "/api/persons/latest",
            failOnStatusCode: false
        }).then((resp) => {
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(resp.status).to.eq(401);
        });
    });

    it("Basic Rejcet, public bypass", () => {
        cy.request({
            method: "GET",
            url: "/api/persons/latest?bypass=api/public",
            failOnStatusCode: false
        }).then((resp) => {
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(resp.status).to.eq(401);
        });
    });

});
