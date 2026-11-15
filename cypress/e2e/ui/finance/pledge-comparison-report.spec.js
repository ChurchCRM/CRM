/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

describe("Pledge Comparison report", () => {
    before(() => {
        cy.setupAdminSession();
        cy.useChurchTimeZone();
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    after(() => {
        cy.useHostTimeZone();
    });

    it("does not give the report to users without finance access", () => {
        cy.setupPersonaSession("office-staff");
        cy.request({
            url: "/finance/reports/pledge-comparison/download",
            qs: { firstFY: 23, secondFY: 22 },
            failOnStatusCode: false,
            followRedirect: false,
        }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });

    it("offers the current church fiscal year by default", () => {
        cy.getSystemConfig("iFYMonth").then((fyMonth) => {
            const now = new Date();
            const currentFyId = now.getFullYear() - 1996 + (fyMonth > 1 && now.getMonth() + 1 >= fyMonth ? 1 : 0);
            const previousFyId = currentFyId > 1 ? currentFyId - 1 : currentFyId + 1;

            cy.visit("/finance/reports/pledge-comparison");
            cy.get("#firstFY").should("have.value", String(currentFyId));
            cy.get(`#firstFY option[value="${currentFyId}"]`).should("exist");
            cy.get("#secondFY").should("have.value", String(previousFyId));
        });
    });

    it("aggregates each family's pledge totals for both fiscal years", () => {
        cy.request({
            url: "/finance/reports/pledge-comparison/download",
            qs: { firstFY: 23, secondFY: 22 },
            encoding: "binary",
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body).then((lines) => {
                const text = lines.join(" ");
                expect(text).to.include("Pledge Comparison");
                expect(text).to.include("Diaz");
                expect(text).to.match(/\$\s*344\.00/);
                expect(text).to.match(/\$\s*0\.00/);
            });
        });
    });
});
