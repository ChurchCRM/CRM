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

    it("Year-End Giving leads to the page and Back returns to Year-End Giving", () => {
        cy.visit("/finance/reports/year-end?year=2018");
        cy.get("#customStatements").click();
        cy.location("pathname").should("include", statementsPath);
        cy.contains("a.btn", "Back").click();
        cy.location("pathname").should("include", "/finance/reports/year-end");
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
});

describe("Canadian statement review form", () => {
    let previousFlag;
    before(() => {
        cy.getSystemConfig("bCanadianGivingStatement").then((value) => { previousFlag = value; });
        cy.setSystemConfig("bCanadianGivingStatement", "1");
    });
    after(() => cy.restoreSystemConfig("bCanadianGivingStatement", previousFlag));
    beforeEach(() => cy.freshAdminFormLogin({ enabled: true }));
    it("requires donor review without selecting members or carrying standard filters", () => {
        cy.visit(`${statementsPath}?template=canadian`);
        cy.get("#calendarYear").clear().type("2025");
        cy.get("#canadianFamily").select("1");
        cy.get("#copyKind").select("duplicate");
        cy.get('[name="review"]').click();
        // Prefilled from the heads of household, and still editable.
        cy.get('[name="donorNames[1]"]').invoke("val").should("include", "Mathew").and("include", "Alice");
        cy.get('[name="donorNames[1]"]').should("not.be.disabled");
        cy.get('[name="donorVerified[1]"]').should("not.be.checked");
        // The Campbell family has no postal code, so the review names the problem and leaves it out.
        cy.get('fieldset[data-family-id="1"] .alert-danger').should("contain", "mailing address is incomplete");
        cy.get('fieldset[data-family-id="1"] [name="family[]"]').should("be.disabled").and("not.be.checked");
        cy.get("#canadianBlocked").should("be.visible");
        cy.get("#canadianDownload").should("be.disabled");
        cy.get("#issueDate").should("have.value", "");
        cy.get("#DateStart, #DateEnd, #fundsList, #deposit, #minimum").should("not.exist");
        cy.get("#canadianDownload").should("have.attr", "formaction").and("include", "/download");
        cy.get("#canadianReportForm").should("have.attr", "method", "post");
        for (const theme of ["light", "dark"]) {
            for (const width of [1440, 820, 390]) {
                cy.viewport(width, 1000);
                cy.document().then((document) => document.documentElement.setAttribute("data-bs-theme", theme));
                cy.get("#canadianReportForm").should("be.visible");
                cy.document().then((document) => expect(document.documentElement.scrollWidth).to.be.at.most(width));
                cy.screenshot(`canadian-review-${theme}-${width}`);
            }
        }
    });
});
