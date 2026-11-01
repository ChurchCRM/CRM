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

    it("says which period a statement covers by default", () => {
        cy.visit(statementsPath);
        cy.get("#statementTimeline")
            .invoke("text")
            .should("match", /covers the tax year by default\. The last tax year, \d{4}, is January 01 \d{4} through December 31 \d{4}\./);
    });

    it("treasurer previews the families and total in a popup, then gets the download links", () => {
        cy.visit(statementsPath);
        cy.get("#DateStart").invoke("val", "2018-01-01");
        cy.get("#DateEnd").invoke("val", "2018-12-31");
        cy.get("#previewReport").click();

        cy.get("#givingPreviewModal").should("be.visible");
        cy.location("search").should("not.include", "preview=1");
        cy.get("#modalFamilies")
            .invoke("text")
            .then((text) => expect(Number(text)).to.be.greaterThan(0));
        cy.get("#modalTotal").should("not.be.empty");
        cy.get("#modalDownload").should("have.attr", "method", "post").and("have.attr", "action").and("include", "/giving-statements/download");
        cy.get("#modalDownload input[name='DateStart']").should("have.value", "2018-01-01");
        cy.get("#modalDownload input[name='preview']").should("not.exist");
    });

    it("Add All Families previews and downloads with the whole selection as one value", () => {
        cy.visit(statementsPath);
        cy.get("#DateStart").invoke("val", "2018-01-01");
        cy.get("#DateEnd").invoke("val", "2018-12-31");
        cy.get("[data-select-all='family']").click();
        cy.get("#previewReport").click();

        cy.get("#givingPreviewModal").should("be.visible");
        cy.get("#modalDownload input[name='family']").invoke("val").should("include", ",");
        cy.get("#modalDownload input[name='family[]']").should("not.exist");
    });

    it("a preview with no payments says so in the popup and offers no downloads", () => {
        cy.visit(statementsPath);
        cy.get("#DateStart").invoke("val", "1991-01-01");
        cy.get("#DateEnd").invoke("val", "1991-01-31");
        cy.get("#previewReport").click();

        cy.get("#givingPreviewModal").should("be.visible");
        cy.get("#modalEmpty").should("be.visible");
        cy.get("#modalDownload").should("not.be.visible");
    });

    it("a long family selection previews through a POST body as one value, so max_input_vars cannot cut it", () => {
        const body = new URLSearchParams(`${seedYear}&minimum=0`);
        body.append("family", Array.from({ length: 1500 }, (_, index) => index + 1).join(","));
        cy.request({
            method: "POST",
            url: `${statementsPath}/preview`,
            headers: { "content-type": "application/x-www-form-urlencoded" },
            body: body.toString(),
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.families).to.be.greaterThan(0);
        });
    });

    it("the preview URL still renders the summary on the page", () => {
        cy.visit(`${statementsPath}?preview=1&${seedYear}`);
        cy.get("#previewFamilies")
            .invoke("text")
            .then((text) => expect(Number(text)).to.be.greaterThan(0));
        cy.get("#downloadPdf").should("have.attr", "href").and("include", "/giving-statements/download");
    });

    it("the PDF is named for the report and the year", () => {
        cy.request({ url: `${statementsPath}/download?${seedYear}`, encoding: "binary" }).then((response) => {
            expect(response.headers["content-disposition"]).to.match(/filename="[^"]+-GivingStatements-2018\.pdf"/);
        });
        cy.request({ url: `${statementsPath}/download?familyId=1&year=2018`, encoding: "binary" }).then((response) => {
            expect(response.headers["content-disposition"]).to.match(/filename="[^"]+-GivingStatement-2018-[A-Za-z-]+\.pdf"/);
        });
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
