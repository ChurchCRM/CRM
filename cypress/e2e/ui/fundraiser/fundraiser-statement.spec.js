/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

const statementUrl = "/fundraiser/1/reports/statement";

describe("Fundraiser buyer statements", () => {
    it("a user without Manage Fundraisers is sent to access denied", () => {
        cy.setupPersonaSession("finance-no-fundraisers");
        cy.request({ url: statementUrl, followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=ManageFundraisers");
        });
    });

    it("a fundraiser manager gets one statement per buyer as a PDF", () => {
        cy.setupPersonaSession("staff-all-but-admin");
        cy.request({ url: statementUrl, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.startsWith("%PDF-")).to.eq(true);
            return pdfText(response.body).then((lines) => {
                expect(lines.join(" ")).to.match(/Donated Items|Purchased Items|No records were returned/i);
            });
        });
    });
});
