/// <reference types="cypress" />

const advancedDeposit = (output, overrides = {}) =>
    cy.request({
        method: "POST",
        url: "finance/reports/advanced-deposit/download",
        form: true,
        body: {
            DateStart: "2018-01-01",
            DateEnd: "2099-12-31",
            datetype: "Deposit",
            sort: "deposit",
            detail_level: "detail",
            output,
            ...overrides,
        },
        encoding: "binary",
        failOnStatusCode: false,
        followRedirect: false,
    });

describe("Advanced Deposit report", () => {
    it("finance staff get the PDF for every sort order", () => {
        cy.setupPersonaSession("finance-only");
        ["deposit", "fund", "family"].forEach((sort) => {
            advancedDeposit("pdf", { sort }).then((response) => {
                expect(response.headers["content-type"]).to.include("application/pdf");
                expect(response.body.slice(0, 5)).to.eq("%PDF-");
            });
        });
    });

    it("finance staff get the CSV", () => {
        cy.setupPersonaSession("finance-only");
        advancedDeposit("csv").then((response) => {
            expect(response.headers["content-type"]).to.include("text/csv");
            expect(response.body).to.include("fam_ID");
            expect(response.body).to.include("fun_Name");
        });
    });

    it("an empty period returns to the form with the no-data notice", () => {
        cy.setupPersonaSession("finance-only");
        advancedDeposit("pdf", { DateStart: "1900-01-01", DateEnd: "1900-01-01" }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("ReturnMessage=NoRows");
        });
    });

    it("office staff are sent to access denied", () => {
        cy.setupPersonaSession("office-staff");
        advancedDeposit("pdf").then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });
});
