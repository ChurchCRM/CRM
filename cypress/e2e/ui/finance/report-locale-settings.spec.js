/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

// Seed: family 20 (Black) has gifts in March 2018.
describe("Report PDFs print dates and money in the church's locale settings", () => {
    const settings = {
        sDateFormatLong: "d.m.Y",
        sCurrencySymbol: "€",
        sCurrencyPosition: "after",
        sThousandsSeparator: ".",
        sDecimalSeparator: ",",
    };
    const original = {};
    const configure = (name, value) => cy.request("POST", `/admin/api/system/config/${name}`, { value }).its("status").should("eq", 200);

    before(() => {
        cy.setupAdminSession();
        Object.keys(settings).forEach((key) => cy.getSystemConfig(key).then((value) => { original[key] = value; }));
    });

    beforeEach(() => {
        cy.setupAdminSession();
        Object.entries(settings).forEach(([key, value]) => configure(key, value));
    });

    after(() => {
        cy.setupAdminSession();
        Object.keys(settings).forEach((key) => cy.restoreSystemConfig(key, original[key]));
    });

    it("giving statement and footer use the configured date layout and currency", () => {
        cy.request({ url: "/finance/reports/giving-statements/download", qs: { familyId: 20, year: 2018 }, encoding: "binary" })
            .then((response) => pdfText(response.body))
            .then((lines) => {
                const text = lines.join("\n");
                expect(text).to.include("04 March 2018");
                expect(text).not.to.include("04.March");
                expect(text).to.match(/\d,\d{2}\s?€/);
                expect(text).not.to.include("$");
                expect(text).to.match(/Generated \d{2} [A-Za-z]+ \d{4}/);
            });
    });
});
