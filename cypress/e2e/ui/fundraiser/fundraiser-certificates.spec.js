/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

// Seed fundraiser 2: Spring Silent Auction.
const CERTIFICATES = "/fundraiser/3/reports/certificates";

describe("Fundraiser certificates PDF", () => {
    it("prints a certificate for each seeded silent-auction item", () => {
        cy.setupStandardSession();
        cy.request({ url: CERTIFICATES, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body).then((lines) => {
                const text = lines.join(" ");
                expect(text).to.include("Certificate of Ownership");
                expect(text).to.include("Spring Silent Auction");
                expect(text).to.include("A2: Handmade quilt");
                expect(text).to.include("Donated by Darren Campbell");
            });
        });
    });

    it("a user without the Manage Fundraisers permission is denied", () => {
        cy.setupPersonaSession("finance-no-fundraisers");
        cy.request({ url: CERTIFICATES, followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.be.oneOf([302, 403]);
            expect(response.headers["content-type"] || "").to.not.include("application/pdf");
        });
    });
});
