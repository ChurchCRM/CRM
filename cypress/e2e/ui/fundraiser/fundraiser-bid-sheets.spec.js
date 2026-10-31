/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

// Seed fundraiser 2: Spring Silent Auction, item A1.
const BID_SHEETS = "/fundraiser/3/reports/bid-sheets";

describe("Fundraiser bid sheets PDF", () => {
    it("prints the seeded silent-auction items", () => {
        cy.setupStandardSession();
        cy.request({ url: BID_SHEETS, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body).then((lines) => {
                const text = lines.join(" ");
                expect(text).to.include("A1: Weekend at the church cabin");
                expect(text).to.include("Donated by Mathew Campbell");
                expect(text).to.include("Handmade quilt");
            });
        });
    });

    it("a user without the Manage Fundraisers permission is denied", () => {
        cy.setupPersonaSession("finance-no-fundraisers");
        cy.request({ url: BID_SHEETS, followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.be.oneOf([302, 403]);
            expect(response.headers["content-type"] || "").to.not.include("application/pdf");
        });
    });
});
