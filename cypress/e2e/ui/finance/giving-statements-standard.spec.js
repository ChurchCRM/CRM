/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

// Seed family 4 (Ray) is deactivated and has no payments.
const FAMILY_ID = "4";
const range = "DateStart=2018-01-01&DateEnd=2018-12-31&datetype=Payment";
const download = "/finance/reports/giving-statements/download";

describe("Giving statements follow common church practice", () => {
    let groupKey;

    beforeEach(() => cy.setupAdminSession());

    before(() => {
        cy.setupAdminSession();
        cy.request("POST", "/api/payments/pledges", {
            type: "Payment",
            iMethod: "CHECK",
            iCheckNo: "7701",
            Date: "2018-05-06",
            FamilyID: FAMILY_ID,
            FYID: 22,
            tScanString: "",
            FundSplit: JSON.stringify([{ FundID: "1", Amount: 25, NonDeductible: 0, Comment: "" }]),
        })
            .its("body.groupKey")
            .then((key) => {
                groupKey = key;
            });
    });

    after(() => {
        cy.setupAdminSession();
        cy.request({ method: "DELETE", url: `/api/payments/${groupKey}`, failOnStatusCode: false }).its("status").should("be.oneOf", [200, 404]);
    });

    it("includes a deactivated family that gave in the period", () => {
        cy.request(`${download}?${range}&output=csv`).its("body").should("match", /^"?4"?,"?Ray"?,/m);
    });

    it("prints the method by name, with the check number, and the no-goods-or-services statement", () => {
        cy.request({ url: `${download}?${range}&familyId=${FAMILY_ID}`, encoding: "binary" })
            .then((response) => pdfText(response.body))
            .then((lines) => {
                const text = lines.join("\n");
                expect(text).to.include("Check #7701");
                expect(text).not.to.include("CHECK #7701");
                expect(text).to.include("No goods or services were provided in exchange for these contributions, other than intangible religious benefits.");
            });
    });
});
