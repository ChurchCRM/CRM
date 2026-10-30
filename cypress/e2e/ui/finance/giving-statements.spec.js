/// <reference types="cypress" />

/**
 * Treasurer prepares year-end giving statements (#8143): choose the period,
 * preview who is included and the total, then download.
 *
 * Seed: family 1 (Campbell) has payments in 2018.
 */
const statementsPath = "/finance/reports/giving-statements";
const seedYear = "DateStart=2018-01-01&DateEnd=2018-12-31&datetype=Payment";
const emptyRange = "DateStart=1991-01-01&DateEnd=1991-01-31&datetype=Payment";

const requestBinary = (url) => cy.request({ url, encoding: "binary", failOnStatusCode: false });

const expectPdf = (response) => {
    expect(response.status).to.eq(200);
    expect(response.headers["content-type"]).to.include("application/pdf");
    expect(response.body.slice(0, 5)).to.eq("%PDF-");
};

describe("Giving statements", () => {
    beforeEach(() => cy.setupAdminSession());

    it("the Finance menu and the dashboard lead to the page", () => {
        cy.visit("/finance/");
        cy.get(`a[href$='${statementsPath}']`).should("exist");
    });

    it("treasurer previews the families and total, then gets the download links", () => {
        cy.visit(statementsPath);
        cy.get("#DateStart").invoke("val", "2018-01-01");
        cy.get("#DateEnd").invoke("val", "2018-12-31");
        cy.get("#previewReport").click();

        cy.get("#previewFamilies")
            .invoke("text")
            .then((text) => expect(Number(text)).to.be.greaterThan(0));
        cy.get("#previewTotal").should("not.be.empty");
        cy.get("#downloadPdf").should("have.attr", "href").and("include", "/giving-statements/download").and("include", "DateStart=2018-01-01");
        cy.get("#downloadCsv").should("have.attr", "href").and("include", "output=csv");
    });

    it("the PDF downloads with and without a remittance slip", () => {
        requestBinary(`${statementsPath}/download?${seedYear}`).then(expectPdf);
        requestBinary(`${statementsPath}/download?${seedYear}&remittance=yes`).then(expectPdf);
    });

    it("a period with no payments keeps the treasurer on the page, and a download sends them back to it", () => {
        cy.visit(`${statementsPath}?preview=1&${emptyRange}`);
        cy.contains("No Data Found");
        cy.get("#downloadPdf").should("not.exist");

        cy.request({ url: `${statementsPath}/download?${emptyRange}`, followRedirect: false })
            .its("status")
            .should("eq", 302);
    });

    it("the minimum applies to a family's total for the period", () => {
        cy.visit(`${statementsPath}?preview=1&${seedYear}&minimum=999999999`);
        cy.contains("No Data Found");
    });

    it("the old Financial Reports form hands the Giving Report to this page", () => {
        cy.visit("/FinancialReports.php?ReportType=Giving%20Report");
        cy.location("pathname").should("include", statementsPath);
    });
});
