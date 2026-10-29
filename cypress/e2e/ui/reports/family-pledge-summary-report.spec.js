/// <reference types="cypress" />

/**
 * The Pledge Family Summary report lists what each family pledged and paid, so it needs the
 * Finance permission like every other report on the Finance reports page.
 * The form is a plain POST, so a user without Finance can send it directly.
 *
 * The seed has pledges in fiscal year 23.
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

describe("Pledge Family Summary report", () => {
    it("office-staff does not get the report", () => {
        cy.setupPersonaSession("office-staff");
        reportRequest().then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });

    ["finance-only", "church-admin"].forEach((persona) => {
        it(`${persona} gets a PDF`, () => {
            cy.setupPersonaSession(persona);
            reportRequest().then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("application/pdf");
                expect(response.body.slice(0, 5)).to.eq("%PDF-");
            });
        });
    });

    it("filters by fund and family", () => {
        cy.setupPersonaSession("finance-only");
        reportRequest("FYID=23&funds%5B%5D=1&family%5B%5D=9&output=pdf").then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("returns to the report form when nothing matches", () => {
        cy.setupPersonaSession("finance-only");
        reportRequest("FYID=1&output=pdf").then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("ReturnMessage=NoRows");
        });
    });
});
