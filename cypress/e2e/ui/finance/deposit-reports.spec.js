/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

describe("Treasurer: review a deposit and prepare it for the bank", () => {
    let depositId;

    before(() => {
        cy.setupAdminSession();
        cy.request("POST", "/api/deposits", {
            depositType: "Bank", depositComment: "Deposit report workflow", depositDate: "2025-10-25",
        }).its("body.Id").then((id) => {
            depositId = id;
            [["CASH", 42], ["CREDITCARD", 8]].forEach(([method, amount]) => {
                cy.request("POST", "/api/payments/pledges", {
                    type: "Payment", iMethod: method, Date: "2025-10-25", FamilyID: "3", FYID: 29,
                    tScanString: "", DepositID: id,
                    FundSplit: JSON.stringify([{ FundID: "1", Amount: amount, NonDeductible: 0, Comment: "Report test" }]),
                });
            });
        });
    });

    after(() => {
        cy.setupAdminSession();
        cy.request("DELETE", `/api/deposits/${depositId}`);
    });

    it("opens the selected deposit, previews its total and downloads the same payments", () => {
        cy.setupPersonaSession("finance-only");
        cy.visit(`/finance/deposit/${depositId}`);
        cy.get(`a[href$="/finance/reports/deposits?deposit=${depositId}"]`).click();
        cy.get("#deposit").should("have.value", String(depositId));
        cy.get("#previewDepositReport").click();
        cy.get("#depositReportPreview").should("contain", "50.00");
        cy.get("#deposit").should("have.value", String(depositId));
        cy.get("input[name='output'][value='csv']").check();
        cy.intercept("POST", "**/finance/reports/deposits/download").as("csv");
        cy.get("#createReport").click();
        cy.wait("@csv").then(({ response }) => {
            expect(response.statusCode).to.eq(200);
            expect(response.headers["content-type"]).to.include("text/csv");
            expect(response.headers["cache-control"]).to.include("no-store");
            expect(response.body).to.include("42.00").and.include("8.00");
        });
        cy.request({ url: `/finance/reports/deposits/download?deposit=${depositId}`, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.headers["cache-control"]).to.include("no-store");
            return pdfText(response.body).then((lines) => expect(lines.join(" ")).to.include("50.00"));
        });
    });

    it("prints the whole deposit even when analysis filters exclude its payments", () => {
        cy.setupPersonaSession("finance-only");
        cy.visit(`/finance/reports/deposits?deposit=${depositId}&method[]=CHECK`);
        cy.get("#depositAdvancedSettings").should("have.attr", "open");
        cy.intercept("POST", "**/finance/reports/deposits/download").as("slip");
        cy.get("#printBankSlip").click();
        cy.wait("@slip").then(({ response }) => {
            expect(response.statusCode).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
        });
        cy.request({ url: `/api/deposits/${depositId}/pdf`, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.headers["content-disposition"]).to.include(`-DepositSlip-${depositId}-`);
            expect(response.headers["cache-control"]).to.include("private").and.include("no-store");
            expect(response.headers["x-content-type-options"]).to.eq("nosniff");
            return pdfText(response.body).then((lines) => {
                expect(lines.join(" ")).to.include("42.00").and.include("50.00");
                cy.request({ method: "HEAD", url: `/api/deposits/${depositId}/pdf` }).then((head) => {
                    expect(head.status).to.eq(200);
                    expect(head.body).to.eq("");
                    expect(head.headers["content-disposition"]).to.eq(response.headers["content-disposition"]);
                });
            });
        });
    });

    it("retains filters after an empty result and explains a missing bank-slip selection", () => {
        cy.setupPersonaSession("finance-only");
        cy.visit("/finance/reports/deposits?DateStart=1900-01-01&DateEnd=1900-01-01&sort=family");
        cy.get("#previewDepositReport").click();
        cy.get("#depositReportError").should("contain", "No records");
        cy.get("#DateStart").should("have.value", "1900-01-01");
        cy.get("input[name='sort'][value='family']").should("be.checked");
        cy.get("#printBankSlip").click();
        cy.get("#depositReportError").should("contain", "Select one deposit");
    });

    it("supports each grouping and detail level for the selected deposit", () => {
        cy.setupPersonaSession("finance-only");
        ["deposit", "fund", "family"].forEach((sort) => {
            ["detail", "medium", "summary"].forEach((detail) => {
                cy.request({ url: `/finance/reports/deposits/download?deposit=${depositId}&sort=${sort}&detail_level=${detail}`, encoding: "binary" })
                    .then((response) => {
                        expect(response.headers["content-type"]).to.include("application/pdf");
                        expect(response.body.slice(0, 5)).to.eq("%PDF-");
                    });
            });
        });
    });
});

describe("Finance reviewer: deposit reports across themes and screen sizes", () => {
    for (const theme of ["light", "dark"]) {
        for (const [device, width] of [["desktop", 1440], ["tablet", 820], ["phone", 390]]) {
            it(`keeps review and bank actions usable on ${theme} ${device}`, () => {
                cy.setupPersonaSession("finance-only");
                cy.viewport(width, 900);
                cy.visit("/finance/reports/deposits");
                cy.document().then((doc) => doc.documentElement.setAttribute("data-bs-theme", theme));
                cy.get("#createReport").should("be.visible");
                cy.get("#depositAdvancedSettings").should("not.have.attr", "open");
                cy.document().then((doc) => {
                    expect(doc.documentElement.scrollWidth).to.be.at.most(doc.documentElement.clientWidth);
                });
                cy.screenshot(`deposit-report-${theme}-${device}`, { capture: "fullPage" });
                cy.get("#depositAdvancedSettings > summary").click();
                cy.get("input[name='sort'][value='family']").check();
                cy.get("#DateStart").invoke("val", "1900-01-01");
                cy.get("#DateEnd").invoke("val", "1900-01-01");
                cy.get("#previewDepositReport").click();
                cy.get("#depositReportError").should("contain", "No records");
                cy.get("input[name='sort'][value='family']").should("be.checked");
                cy.get("#depositAdvancedSettings").should("have.attr", "open");
                cy.get("#printBankSlip").click();
                cy.get("#depositReportError").should("contain", "Select one deposit");
            });
        }
    }
});
