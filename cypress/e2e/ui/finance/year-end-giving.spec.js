/// <reference types="cypress" />

/**
 * Year-end giving is the finance team's starting point: pick a year, review what is ready,
 * then print or email the statements.
 */
const yearEndPath = "/finance/reports/year-end";

describe("Year-End Giving", () => {
    beforeEach(() => cy.setupAdminSession());

    it("the Finance menu and the dashboard checklist lead here", () => {
        cy.visit("/finance/");
        cy.get(`a[href$='${yearEndPath}']`).should("exist");
        cy.contains("a.btn", "Generate").should("have.attr", "href").and("include", yearEndPath);
    });

    it("shows the year's totals, the checklist and the statement actions", () => {
        cy.visit(yearEndPath);
        cy.get("#year option").its("length").should("be.gte", 1);
        cy.get("#yearSummary").should("be.visible");
        cy.get("#yearChecklist .list-group-item").should("have.length.gte", 3);
        cy.get("#customStatements").should("have.attr", "href").and("include", "/finance/reports/giving-statements?DateStart=");
    });

    it("a year with payments offers preview, PDF and CSV for that year", () => {
        cy.visit(`${yearEndPath}?year=2018`);
        cy.get("#yearGifts")
            .invoke("text")
            .then((text) => expect(Number(text)).to.be.greaterThan(0));
        cy.get("#yearPdf").should("have.attr", "href").and("include", "DateStart=2018-01-01");
        cy.get("#previewStatements").click();
        cy.get("#previewFamilies")
            .invoke("text")
            .then((text) => expect(Number(text)).to.be.greaterThan(0));
    });

    it("the statement wording is shown and the admin can edit it", () => {
        cy.visit(yearEndPath);
        cy.get("#statementWordingView").should("contain", "Thank you");
        cy.get("#statementWording").should("exist");
    });
});
