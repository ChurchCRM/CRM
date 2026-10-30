/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

/**
 * The Voting Members report can filter families by giving history, so it needs the
 * Finance permission like every other report on the Finance reports page.
 *
 * The form is a plain POST, so a user without Finance can send it directly.
 */
const votingMembersRequest = () =>
    cy.request({
        method: "POST",
        url: "finance/reports/voting-members/download",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "FYID=&RequireDonationYears=1&output=pdf",
        encoding: "binary",
        failOnStatusCode: false,
        followRedirect: false,
    });

describe("Voting Members report - Finance access", () => {
    it("office-staff does not get the report", () => {
        cy.setupPersonaSession("office-staff");
        votingMembersRequest().then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });

    ["finance-only", "church-admin"].forEach((persona) => {
        it(`${persona} gets the report`, () => {
            cy.setupPersonaSession(persona);
            votingMembersRequest().then((response) => {
                expect(response.headers["content-type"]).to.include("application/pdf");
                return pdfText(response.body).then((lines) => {
                    expect(lines.join(" ")).to.include("Voting Members");
                });
            });
        });
    });

    it("lists the members and their count, with or without a donation requirement", () => {
        cy.setupPersonaSession("finance-only");
        ["0", "2"].forEach((years) => {
            cy.request({
                url: `finance/reports/voting-members/download?FYID=&RequireDonationYears=${years}`,
                encoding: "binary",
            }).then((response) => {
                expect(response.headers["content-type"]).to.include("application/pdf");
                return pdfText(response.body).then((lines) => {
                    expect(lines.join(" ")).to.include("Number of Voting Members");
                });
            });
        });
    });
});
