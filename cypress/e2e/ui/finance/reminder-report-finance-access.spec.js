/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

// Seed: family 9 has two unpaid pledges in fiscal year 23.
const reminderRequest = () =>
    cy.request({
        method: "POST",
        url: "finance/reports/pledge-reminders/download",
        form: true,
        body: { FYID: "23", pledge_filter: "pledge", only_owe: "yes", output: "pdf" },
        encoding: "binary",
        failOnStatusCode: false,
        followRedirect: false,
    });

describe("Pledge Reminder report - Finance access", () => {
    it("office-staff is sent to access denied", () => {
        cy.setupPersonaSession("office-staff");
        reminderRequest().then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });

    it("finance-only gets a PDF with a letter and the amount due", () => {
        cy.setupPersonaSession("finance-only");
        reminderRequest().then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.startsWith("%PDF-")).to.eq(true);
            return pdfText(response.body).then((lines) => {
                expect(lines.join(" ")).to.include("Amount due for");
            });
        });
    });
});
