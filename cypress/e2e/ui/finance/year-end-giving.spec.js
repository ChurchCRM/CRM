/// <reference types="cypress" />

/**
 * Year-end giving is the finance team's starting point: pick a year, review what is ready,
 * then download the statements.
 */
const yearEndPath = "/finance/reports/year-end";

describe("Year-End Giving", () => {
    beforeEach(() => cy.setupAdminSession());

    it("a year with payments offers PDF, CSV and a filtered review for that year", () => {
        cy.visit(`${yearEndPath}?year=2018`);
        cy.get("#yearGifts")
            .invoke("text")
            .then((text) => expect(Number(text)).to.be.greaterThan(0));
        cy.get("#yearPdf").should("have.attr", "href").and("include", "DateStart=2018-01-01");
        cy.get("#customStatements").click();
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
        cy.get(`#year option[value='${currentEndYear}']`).should("exist");
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

describe("Year-End Giving keeps one year and saves the wording as it changes", () => {
    beforeEach(() => cy.setupAdminSession());

    it("the year picked on the dashboard is the year on Year-End Giving and Giving Statements", () => {
        // Fiscal year id 22 ends in 2018.
        cy.visit("/finance/?fyid=22");
        cy.visit(yearEndPath);
        cy.get("#year option:selected").should("have.value", "2018");
        cy.visit("/finance/reports/giving-statements");
        cy.get("#DateStart").should("have.value", "2018-01-01");
        cy.get("#DateEnd").should("have.value", "2018-12-31");
    });

    it("the year picked on Year-End Giving is the year the dashboard card reports", () => {
        cy.visit(`${yearEndPath}?year=2018`);
        cy.visit("/finance/");
        cy.get("#year-end-entry a.btn-primary").should("have.attr", "href").and("include", "/finance/reports/year-end?year=2018");
    });

    it("the wording saves without a Save button and survives a reload", () => {
        const key = "sGivingStatementClosing";
        const wording = "Year-end thanks — saved as typed";
        cy.getSystemConfig(key).then((original) => {
            cy.intercept("POST", `**/finance/api/config/${key}`).as("saveWording");
            cy.visit(yearEndPath);
            cy.get(`#statementWording textarea[name='${key}']`).should("not.be.disabled").clear().type(wording).blur();
            cy.wait("@saveWording").its("response.statusCode").should("eq", 200);
            cy.reload();
            cy.get(`#statementWording textarea[name='${key}']`).should("have.value", wording);
            cy.makePrivateAdminAPICall("POST", `/admin/api/system/config/${key}`, { value: original }, 200);
        });
    });

    it("Review and Filter opens the filters with the totals and Back returns here", () => {
        cy.visit(`${yearEndPath}?year=2018`);
        cy.get("#customStatements").click();
        cy.get("#previewFamilies").invoke("text").then((text) => expect(Number(text)).to.be.greaterThan(0));
        cy.contains("a.btn", "Back").click();
        cy.location("pathname").should("include", yearEndPath);
    });
});
