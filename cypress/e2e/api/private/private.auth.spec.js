/// <reference types="cypress" />

describe("API Private without Auth", () => {
    it("Basic Reject", () => {
        cy.apiRequest({
            method: "GET",
            url: "/api/persons/latest",
        }).then((resp) => {
            expect(resp.status).to.eq(401);
            expect(resp.body).to.exist;
        });
    });

    it("Basic Reject, public bypass", () => {
        cy.apiRequest({
            method: "GET",
            url: "/api/persons/latest?bypass=api/public",
        }).then((resp) => {
            expect(resp.status).to.eq(401);
            expect(resp.body).to.exist;
        });
    });
});
