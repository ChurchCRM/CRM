/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

/**
 * The Pledge Family Summary report lists what each family pledged and paid, so it needs the
 * Finance permission like every other report on the Finance reports page.
 * The form is a plain POST, so a user without Finance can send it directly.
 *
 * Seed: family 9 (Diaz) has FY 23 pledges of $11 to Pledges and $333 to New Building Fund.
 */
const reportRequest = (body = "FYID=23&output=pdf") =>
    cy.request({
        method: "POST",
        url: "finance/reports/family-pledge-summary/download",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body,
        encoding: "binary",
        failOnStatusCode: false,
        followRedirect: false,
    });

const pdfLines = (response) => {
    expect(response.status).to.eq(200);
    expect(response.headers["content-type"]).to.include("application/pdf");
    expect(response.headers["cache-control"]).to.include("no-store");
    expect(response.body.slice(0, 5)).to.eq("%PDF-");
    return pdfText(response.body);
};

describe("Pledge Family Summary report", () => {
    it("office-staff does not get the report", () => {
        cy.setupPersonaSession("office-staff");
        reportRequest().then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });

    ["finance-only", "church-admin"].forEach((persona) => {
        it(`${persona} gets the seeded Diaz pledges`, () => {
            cy.setupPersonaSession(persona);
            reportRequest().then((response) =>
                pdfLines(response).then((lines) => {
                    const text = lines.join(" ");
                    expect(text).to.include("Pledge Summary By Family");
                    expect(text).to.include("Diaz");
                    expect(text).to.include("Pledges");
                    expect(text).to.include("New Building Fund");
                    expect(text).to.include("11.00");
                    expect(text).to.include("333.00");
                    expect(text).to.include("Total");
                }),
            );
        });
    });

    it("filters by fund and family", () => {
        cy.setupPersonaSession("finance-only");
        reportRequest("FYID=23&funds%5B%5D=1&family%5B%5D=9&output=pdf").then((response) =>
            pdfLines(response).then((lines) => {
                const text = lines.join(" ");
                expect(text).to.include("Diaz");
                expect(text).to.include("Pledges");
                expect(text).to.include("11.00");
                expect(text).not.to.include("333.00");
                expect(text).not.to.include("New Building Fund");
            }),
        );
    });

    it("returns to the report form when nothing matches", () => {
        cy.setupPersonaSession("finance-only");
        reportRequest("FYID=1&output=pdf").then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("ReturnMessage=NoRows");
        });
    });
});
