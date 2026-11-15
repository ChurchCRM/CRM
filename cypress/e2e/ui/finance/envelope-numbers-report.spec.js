/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

const envelopeReport = "finance/reports/envelope-numbers";

describe("Envelope Numbers report", () => {
    it("finance staff get the PDF", () => {
        cy.setupPersonaSession("finance-only");
        cy.request({ url: envelopeReport, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body).then((lines) => {
                expect(lines.join(" ")).to.include("Envelope Numbers");
            });
        });
    });

    it("office staff are sent to access denied", () => {
        cy.setupPersonaSession("office-staff");
        cy.request({ url: envelopeReport, followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });

    it("the Envelope Manager links to the report", () => {
        cy.setupFinanceOnlySession();
        cy.visit("/finance/envelopes");
        cy.get(`a[href$="/${envelopeReport}"]`).should("exist");
    });
});
