/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

// Seed: family 13 has three CASH payments in fiscal year 22, the first on 2018-03-04.
describe("Pledge Reminder letter follows the church's date, currency and fiscal year settings", () => {
    const settings = {
        sDateFormatLong: "d/m/Y",
        sCurrencySymbol: "€",
        sCurrencyPosition: "after",
        sThousandsSeparator: ".",
        sDecimalSeparator: ",",
        iFYMonth: "7",
    };
    const original = {};
    const configure = (name, value) => cy.request("POST", `/admin/api/system/config/${name}`, { value }).its("status").should("eq", 200);

    before(() => {
        Object.keys(settings).forEach((key) => cy.getSystemConfig(key).then((value) => { original[key] = value; }));
    });

    beforeEach(() => {
        cy.setupAdminSession();
        Object.entries(settings).forEach(([key, value]) => configure(key, value));
    });

    afterEach(() => {
        Object.keys(settings).forEach((key) => cy.restoreSystemConfig(key, original[key]));
    });

    it("prints configured dates, translated payment methods, euro amounts and the split fiscal year", () => {
        cy.request({
            method: "POST",
            url: "/finance/reports/pledge-reminders/download",
            form: true,
            encoding: "binary",
            body: { "family[]": "13", FYID: "22", pledge_filter: "all", only_owe: "no" },
        })
            .then((response) => {
                expect(response.headers["content-type"]).to.include("application/pdf");
                return pdfText(response.body);
            })
            .then((lines) => {
                const text = lines.join(" ");
                expect(lines).to.include("04 March 2018");
                expect(lines).to.include("Cash");
                expect(lines).not.to.include("CASH");
                expect(text).to.match(/\d,\d{2} ?\s?€/);
                expect(text).to.include("2017/18");
            });
    });
});
