/// <reference types="cypress" />

const zeroGivers = (output) =>
    cy.request({
        method: "POST",
        url: "finance/reports/zero-givers/download",
        form: true,
        body: { DateStart: "1990-01-01", DateEnd: "1990-12-31", letterhead: "address", output },
        encoding: "binary",
        failOnStatusCode: false,
        followRedirect: false,
    });

describe("Zero Givers report", () => {
    it("finance staff get the PDF letters", () => {
        cy.setupPersonaSession("finance-only");
        zeroGivers("pdf").then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("finance staff get the CSV", () => {
        cy.setupPersonaSession("finance-only");
        zeroGivers("csv").then((response) => {
            expect(response.headers["content-type"]).to.include("text/csv");
            expect(response.body).to.include("fam_ID");
        });
    });

    it("office staff are sent to access denied", () => {
        cy.setupPersonaSession("office-staff");
        zeroGivers("pdf").then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });
});
