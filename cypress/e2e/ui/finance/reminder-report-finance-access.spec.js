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

    it("every family's letter carries the church letterhead", () => {
        cy.setupAdminSession();
        cy.request("/admin/api/system/config/sChurchName").its("body.value").then((churchName) => {
            cy.request({
                method: "POST",
                url: "finance/reports/pledge-reminders/download",
                form: true,
                // Fiscal year 22 holds payments for seven families.
                body: { FYID: "22", pledge_filter: "all", only_owe: "no" },
                encoding: "binary",
            })
                .then((response) => pdfText(response.body))
                .then((lines) => {
                    const letters = lines.filter((line) => line.startsWith("Sincerely")).length;
                    expect(letters).to.be.greaterThan(1);
                    expect(lines.filter((line) => line.includes(churchName)).length).to.be.at.least(letters);
                });
        });
    });
});
