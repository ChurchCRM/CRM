/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

// https://github.com/ChurchCRM/CRM/issues/10183
describe("Reminder Report with a payment that has no check number - Issue #10183", () => {
    // Seed: family 13 has three CASH payments (plg_CheckNo NULL) in fiscal year 22.
    const familyId = "13";
    const fiscalYearId = "22";

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("prints the cash payment with an empty check number cell", () => {
        cy.request({
            method: "POST",
            url: "/Reports/ReminderReport.php",
            form: true,
            encoding: "binary",
            failOnStatusCode: false,
            body: {
                "family[]": familyId,
                FYID: fiscalYearId,
                pledge_filter: "all",
                only_owe: "no",
                output: "pdf",
            },
        })
            .then((response) => {
                expect(response.status, "HTTP status").to.equal(200);
                expect(response.headers["content-type"], "Content-Type").to.include("application/pdf");
                return pdfText(response.body);
            })
            .then((lines) => {
                const firstPayment = lines.indexOf("Amount", lines.indexOf("Chk No.")) + 1;
                expect(lines.slice(firstPayment, firstPayment + 3), "date, method, fund").to.deep.equal([
                    "2018-03-04",
                    "CASH",
                    "Pledges",
                ]);
            });
    });
});
