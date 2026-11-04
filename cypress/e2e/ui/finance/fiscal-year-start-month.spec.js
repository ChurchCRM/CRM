/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

/**
 * A non-January fiscal year must filter giving reports to that year, not the
 * calendar year. Seed family 20 (Black) has a June 2017 gift and March 2018 gifts.
 *
 * January start, year 2018: 2018-01-01 to 2018-12-31 — March in, June out.
 * July start, year 2018:    2017-07-01 to 2018-06-30 — March in, June out.
 * July start, year 2017:    2016-07-01 to 2017-06-30 — June in, March out.
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

const setMonth = (month) => {
    cy.setupAdminSession();
    cy.makePrivateAdminAPICall("POST", "admin/api/system/config/iFYMonth", { value: String(month) }, 200);
    cy.getSystemConfig("iFYMonth").should("eq", String(month));
};

describe("Giving reports follow the fiscal year start month", () => {
    let originalMonth;

    before(() => {
        cy.setupAdminSession();
        cy.getSystemConfig("iFYMonth").then((value) => {
            originalMonth = value;
        });
    });

    after(() => {
        cy.setupAdminSession();
        cy.restoreSystemConfig("iFYMonth", originalMonth);
    });

    it("a January start keeps 2018 as the calendar year", () => {
        setMonth(1);

        cy.visit("/finance/reports");
        cy.contains("Your fiscal year starts in January").should("be.visible");

        cy.visit("/people/family/20");
        cy.contains("button", "Finance").click();
        cy.contains("Print 2018 Giving Statement").should("be.visible");

        statement(2018).then(({ text, response }) => {
            expect(text).to.include("March 04 2018");
            expect(text).not.to.include("June 11 2017");
            expect(text).to.include("January 01 2018 through December 31 2018");
            expect(response.headers["content-disposition"]).to.include("2018");
        });
    });

    it("a July start filters to the fiscal year and labels it 2017/18", () => {
        setMonth(7);

        const now = new Date();
        const endYear = now.getMonth() + 1 >= 7 ? now.getFullYear() + 1 : now.getFullYear();
        const currentLabel = `${endYear - 1}/${String(endYear).slice(2)}`;

        cy.visit("/finance/reports");
        cy.contains("Your fiscal year starts in July").should("be.visible");
        cy.contains(currentLabel).should("be.visible");

        cy.visit("/people/family/20");
        cy.contains("button", "Finance").click();
        cy.contains("Print 2017/18 Giving Statement").should("be.visible");
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

        cy.signInAsMember(100);
        cy.visit("/portal/giving");
        cy.get('#year option[value="2018"]').should("have.text", "2017/18");
        cy.get('#year option[value="2017"]').should("have.text", "2016/17");
    });
});
