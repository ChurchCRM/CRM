/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

const envelopeReport = "finance/reports/envelope-numbers";

const reportLines = (query = "") =>
    cy.request({ url: `${envelopeReport}${query}`, encoding: "binary" }).then((response) => {
        expect(response.headers["content-type"]).to.include("application/pdf");
        return pdfText(response.body);
    });

// Seed salutations: family 1 (Campbell) is "Mathew Campbell & Alice Noperm", family 20
// (Black) is "Amanda & Lena Black".
const CAMPBELL = "Mathew Campbell & Alice Noperm";
const BLACK = "Amanda & Lena Black";

describe("Envelope Numbers report", () => {
    let restoreEnvelopes = false;

    // Assigns envelope numbers through the manager's own save form (CSRF token from the page).
    const saveEnvelopes = (envelopes) => {
        cy.visit("/finance/envelopes");
        cy.get('#envelope-form input[name="csrf_token"]').invoke("val").then((token) => {
            const body = { csrf_token: token, action: "save", classification: "0", sortBy: "name" };
            Object.entries(envelopes).forEach(([id, num]) => {
                body[`envelopes[${id}]`] = String(num);
            });
            cy.request({ method: "POST", url: "/finance/envelopes", form: true, body, followRedirect: false })
                .its("status")
                .should("eq", 302);
        });
    };

    afterEach(() => {
        if (restoreEnvelopes) {
            saveEnvelopes({ 1: 0, 20: 0 });
            restoreEnvelopes = false;
        }
    });

    it("finance staff get the PDF", () => {
        cy.setupPersonaSession("finance-only");
        cy.request({ url: envelopeReport, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body).then((lines) => {
                expect(lines.join(" ")).to.include("Envelope Numbers");
            });
        });
    });

    it("office staff are sent to access denied", () => {
        cy.setupPersonaSession("office-staff");
        cy.request({ url: envelopeReport, followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });

    it("the Envelope Manager links to the report with its current filter and sort", () => {
        cy.setupFinanceOnlySession();
        cy.visit("/finance/envelopes");
        cy.get(`a[href$="/${envelopeReport}?classification=0&sortBy=name"]`).should("exist");
        cy.visit("/finance/envelopes?classification=2&sortBy=envelope");
        cy.get(`a[href$="/${envelopeReport}?classification=2&sortBy=envelope"]`).should("exist");
    });

    it("the report follows the manager sort and classification (#2933)", () => {
        cy.setupFinanceOnlySession();
        // Campbell envelope 3, Black envelope 5: name order is Black then Campbell,
        // envelope order is Campbell then Black, so the two sorts disagree.
        restoreEnvelopes = true;
        saveEnvelopes({ 1: 3, 20: 5 });

        reportLines("?sortBy=name").then((lines) => {
            expect(lines.indexOf(BLACK)).to.be.lessThan(lines.indexOf(CAMPBELL));
        });
        reportLines("?sortBy=envelope").then((lines) => {
            expect(lines.indexOf(CAMPBELL)).to.be.lessThan(lines.indexOf(BLACK));
        });
        // Family 1 has Member (1) members; family 20 is all Regular Attender (2).
        reportLines("?classification=1").then((lines) => {
            expect(lines).to.include(CAMPBELL);
            expect(lines).to.not.include(BLACK);
        });
        reportLines("?classification=2").then((lines) => {
            expect(lines).to.include(BLACK);
            expect(lines).to.not.include(CAMPBELL);
        });
    });
});
