/// <reference types="cypress" />

/**
 * Year-end giving is the finance team's starting point: pick a year, review what is ready,
 * then print or email the statements.
 */
const yearEndPath = "/finance/reports/year-end";

describe("Year-End Giving", () => {
    beforeEach(() => cy.setupAdminSession());

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

});

// Seed payments from June 2017 and March 2018 fall in 2017/18; the April 2021 gifts, which have no family, in 2021/22.
describe("Year-End Giving with an April fiscal year", () => {
    let originalMonth;

    before(() => {
        cy.setupAdminSession();
        cy.getSystemConfig("iFYMonth").then((value) => {
            originalMonth = value;
        });
        cy.makePrivateAdminAPICall("POST", "admin/api/system/config/iFYMonth", { value: "4" }, 200);
    });

    after(() => {
        cy.setupAdminSession();
        cy.restoreSystemConfig("iFYMonth", originalMonth);
    });

    beforeEach(() => cy.setupAdminSession());

    it("offers the current fiscal year and each fiscal year with payments, by its label", () => {
        const today = new Date();
        const currentEndYear = today.getMonth() + 1 >= 4 ? today.getFullYear() + 1 : today.getFullYear();

        cy.visit(yearEndPath);
        cy.get("#year option:selected").should("have.value", String(currentEndYear));
        cy.get("#year option").then((options) => {
            const values = [...options].map((option) => option.value);
            expect(values).to.include.members(["2022", "2018"]);
            expect(values).not.to.include("2021");
            expect(values).not.to.include("2017");
        });
        cy.get("#year option[value='2022']").should("have.text", "2021/22");
    });

    it("a fiscal year that ends in the next calendar year covers its own dates", () => {
        cy.visit(`${yearEndPath}?year=2022`);
        cy.get("#year option:selected").should("have.value", "2022");
        cy.get("#yearChecklist").should("contain", "gifts have no family");
        cy.get("#customStatements")
            .should("have.attr", "href")
            .and("include", "DateStart=2021-04-01")
            .and("include", "DateEnd=2022-03-31");
    });
});
