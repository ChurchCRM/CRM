/// <reference types="cypress" />

const CERTIFICATES = "/fundraiser/1/reports/certificates";

describe("Fundraiser certificates PDF", () => {
    it("a fundraiser manager gets the PDF", () => {
        cy.setupStandardSession();
        cy.request({ url: CERTIFICATES, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("a user without the Manage Fundraisers permission is denied", () => {
        cy.setupPersonaSession("finance-no-fundraisers");
        cy.request({ url: CERTIFICATES, followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.be.oneOf([302, 403]);
            expect(response.headers["content-type"] || "").to.not.include("application/pdf");
        });
    });
});
