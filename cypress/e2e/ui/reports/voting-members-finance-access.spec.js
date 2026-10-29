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
        url: "Reports/VotingMembers.php",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "FYID=&RequireDonationYears=1&output=pdf",
        encoding: "binary",
        failOnStatusCode: false,
    });

describe("Voting Members report - Finance access", () => {
    it("office-staff does not get the report", () => {
        cy.setupPersonaSession("office-staff");
        votingMembersRequest().then((response) => {
            expect(response.headers["content-type"]).to.not.include("application/pdf");
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
});
