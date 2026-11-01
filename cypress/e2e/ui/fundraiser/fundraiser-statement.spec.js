/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

// Seed fundraiser 2 is the closed silent auction. Fundraiser 5 is the bazaar with a multibuy.
const AUCTION_STATEMENT = "/fundraiser/3/reports/statement";
const BAZAAR_STATEMENT = "/fundraiser/7/reports/statement";

describe("Fundraiser buyer statements", () => {
    it("a user without Manage Fundraisers is sent to access denied", () => {
        cy.setupPersonaSession("finance-no-fundraisers");
        cy.request({ url: AUCTION_STATEMENT, followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=ManageFundraisers");
        });
    });

    it("prints the seeded buyers, their purchases, and a multibuy quantity", () => {
        cy.setupPersonaSession("staff-all-but-admin");
        cy.request({ url: AUCTION_STATEMENT, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body).then((lines) => {
                const text = lines.join(" ");
                expect(text).to.include("Constance & Marion Hart");
                expect(text).to.include("Weekend at the church cabin");
                expect(text).to.include("Sunday casserole");
                expect(text).to.include("Buyer # 101");
            });
        });
        cy.request({ url: BAZAAR_STATEMENT, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body).then((lines) => {
                const text = lines.join(" ");
                expect(text).to.include("Handmade ornaments");
                expect(text).to.include("Cocoa mix");
                expect(text).to.include("Buyer # 7, 8");
            });
        });
    });
});
