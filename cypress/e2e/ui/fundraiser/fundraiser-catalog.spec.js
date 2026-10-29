/// <reference types="cypress" />

const CATALOG = "/fundraiser/1/reports/catalog";

describe("Fundraiser catalog PDF", () => {
    it("a fundraiser manager gets the PDF", () => {
        cy.setupStandardSession();
        cy.request({ url: CATALOG, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("a user without the Manage Fundraisers permission is denied", () => {
        cy.setupPersonaSession("finance-no-fundraisers");
        cy.request({ url: CATALOG, followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.be.oneOf([302, 403]);
            expect(response.headers["content-type"] || "").to.not.include("application/pdf");
        });
    });
});
