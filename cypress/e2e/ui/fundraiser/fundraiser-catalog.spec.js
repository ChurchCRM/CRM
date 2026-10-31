/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

// Seed fundraiser 3: Spring Silent Auction.
const CATALOG = "/fundraiser/3/reports/catalog";

describe("Fundraiser catalog PDF", () => {
    it("prints the seeded silent-auction catalog", () => {
        cy.setupStandardSession();
        cy.request({ url: CATALOG, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body).then((lines) => {
                const text = lines.join(" ");
                expect(text).to.include("Spring Silent Auction");
                expect(text).to.include("A1: Weekend at the church cabin");
                expect(text).to.include("B1: Pie assortment");
                expect(text).to.include("Donated by Albert Campbell");
            });
        });
    });

    it("a user without the Manage Fundraisers permission is denied", () => {
        cy.setupPersonaSession("finance-no-fundraisers");
        cy.request({ url: CATALOG, followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.be.oneOf([302, 403]);
            expect(response.headers["content-type"] || "").to.not.include("application/pdf");
        });
    });
});
