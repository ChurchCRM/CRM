/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

/**
 * Giving statements follow the donor's tax year (iTaxYearStartMonth, default January), not the
 * church's fiscal year (iFYMonth). Seed family 20 (Black) has a June 2017 gift and March 2018 gifts.
 *
 * Tax year from January, year 2018: 2018-01-01 to 2018-12-31 — March in, June out.
 * Tax year from July, year 2018:    2017-07-01 to 2018-06-30 — March in, June out.
 * Tax year from July, year 2017:    2016-07-01 to 2017-06-30 — June in, March out.
 */

const statement = (year, familyId = 20) =>
    cy
        .request({
            url: "/finance/reports/giving-statements/download",
            qs: { familyId, year },
            encoding: "binary",
        })
        .then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body).then((lines) => ({ response, text: lines.join("\n") }));
        });

const setConfig = (name, value) => {
    cy.setupAdminSession();
    cy.makePrivateAdminAPICall("POST", `admin/api/system/config/${name}`, { value: String(value) }, 200);
    cy.getSystemConfig(name).should("eq", String(value));
};

describe("Giving statements follow the tax year, not the fiscal year", () => {
    const original = {};
    const keys = ["iFYMonth", "iTaxYearStartMonth", "iTaxYearStartDay"];

    before(() => {
        cy.setupAdminSession();
        keys.forEach((key) =>
            cy.getSystemConfig(key).then((value) => {
                original[key] = value;
            }),
        );
    });

    after(() => {
        cy.setupAdminSession();
        keys.forEach((key) => cy.restoreSystemConfig(key, original[key]));
    });

    it("a January tax year keeps 2018 as the calendar year", () => {
        setConfig("iFYMonth", 1);
        setConfig("iTaxYearStartMonth", 1);

        cy.visit("/finance/reports");
        cy.contains("Your fiscal year starts in January").should("be.visible");

        cy.visit("/people/family/20");
        cy.contains("button", "Finance").click();
        cy.contains("Print 2018 Giving Statement").should("be.visible");
        cy.contains("January 01 2018 through December 31 2018").should("be.visible");

        statement(2018).then(({ text, response }) => {
            expect(text).to.include("March 04 2018");
            expect(text).not.to.include("June 11 2017");
            expect(text).to.include("January 01 2018 through December 31 2018");
            expect(response.headers["content-disposition"]).to.include("2018");
        });
    });

    it("a July fiscal year leaves the donor's tax year on the calendar year", () => {
        setConfig("iFYMonth", 7);
        setConfig("iTaxYearStartMonth", 1);

        cy.visit("/finance/reports");
        cy.contains("Your fiscal year starts in July").should("be.visible");

        cy.visit("/people/family/20");
        cy.contains("button", "Finance").click();
        cy.contains("Print 2018 Giving Statement").should("be.visible");

        statement(2018).then(({ text }) => {
            expect(text).to.include("March 04 2018");
            expect(text).not.to.include("June 11 2017");
            expect(text).to.include("January 01 2018 through December 31 2018");
        });

        cy.setupLoginSession("giving-member", "lena.black.editself.notes@example.com", "changeme");
        cy.visit("/portal/giving");
        cy.get('#year option[value="2018"]').should("have.text", "2018");
    });

    it("a July tax year filters statements to that year and labels it 2017/18", () => {
        setConfig("iFYMonth", 7);
        setConfig("iTaxYearStartMonth", 7);

        cy.visit("/people/family/20");
        cy.contains("button", "Finance").click();
        cy.contains("Print 2017/18 Giving Statement").should("be.visible");
        cy.contains("July 01 2017 through June 30 2018").should("be.visible");
        cy.contains("Print 2016/17 Giving Statement").should("be.visible");

        statement(2018).then(({ text, response }) => {
            expect(text).to.include("March 04 2018");
            expect(text).not.to.include("June 11 2017");
            expect(text).to.include("July 01 2017 through June 30 2018");
            expect(response.headers["content-disposition"]).to.include("2018");
        });

        statement(2017).then(({ text }) => {
            expect(text).to.include("June 11 2017");
            expect(text).not.to.include("March 04 2018");
            expect(text).to.include("July 01 2016 through June 30 2017");
        });

        cy.setupLoginSession("giving-member", "lena.black.editself.notes@example.com", "changeme");
        cy.visit("/portal/giving");
        cy.get('#year option[value="2018"]').should("have.text", "2017/18 (July 01 2017 through June 30 2018)");
        cy.get('#year option[value="2017"]').should("have.text", "2016/17 (July 01 2016 through June 30 2017)");
    });

    it("a tax year that starts on 6 April runs from that day to the day before", () => {
        setConfig("iFYMonth", 1);
        setConfig("iTaxYearStartMonth", 4);
        setConfig("iTaxYearStartDay", 6);

        statement(2018).then(({ text }) => {
            expect(text).to.include("April 06 2017 through April 05 2018");
            expect(text).to.include("June 11 2017");
            expect(text).to.include("March 04 2018");
        });
    });
});
