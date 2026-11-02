/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

/**
 * The Confirm Report is an mPDF document built from the confirm-report Twig template and
 * served by GET /people/report/verify (Edit Records). Seed family 1 is always present.
 */
const report = "people/report/verify";

describe("Confirm Report PDF", () => {
    it("church-admin downloads a family's confirmation letter as a PDF", () => {
        cy.setupPersonaSession("church-admin");
        cy.request({ url: `${report}?familyId=1`, encoding: "binary" }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.headers["content-disposition"]).to.include("ConfirmReport");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");

            return pdfText(response.body).then((lines) => {
                const text = lines.join(" ");
                expect(text).to.include("Family Name");
                expect(text).to.include("Family Members");
            });
        });
    });

    it("church-admin downloads the letters for every family", () => {
        cy.setupPersonaSession("church-admin");
        cy.request({ url: report, encoding: "binary", timeout: 60000 }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("an unknown family still returns a valid, empty PDF", () => {
        cy.setupPersonaSession("church-admin");
        cy.request({ url: `${report}?familyId=999999`, encoding: "binary" }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    ["notes-only-staff", "zero-permission-staff"].forEach((persona) => {
        it(`${persona} cannot download the report (Edit Records)`, () => {
            cy.setupPersonaSession(persona);
            cy.request({ url: `${report}?familyId=1`, followRedirect: false, failOnStatusCode: false }).then((response) => {
                expect(response.headers["content-type"] || "").to.not.include("application/pdf");
                expect(response.status).to.be.oneOf([302, 403]);
            });
        });
    });
});
