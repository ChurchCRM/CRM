/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

/**
 * The Pledge Family Summary report lists what each family pledged and paid, so it needs the
 * Finance permission like every other report on the Finance reports page.
 * The form is a plain POST, so a user without Finance can send it directly.
 *
 * Seed: family 9 (Diaz) has FY 23 pledges of $11 to Pledges and $333 to New Building Fund.
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

const pdfLines = (response) => {
    expect(response.status).to.eq(200);
    expect(response.headers["content-type"]).to.include("application/pdf");
    expect(response.headers["cache-control"]).to.include("no-store");
    expect(response.body.slice(0, 5)).to.eq("%PDF-");
    return pdfText(response.body);
};

describe("Pledge Family Summary report", () => {
    it("office-staff does not get the report or its form", () => {
        cy.setupPersonaSession("office-staff");
        reportRequest().then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
        cy.request({ url: "/finance/reports/family-pledge-summary", failOnStatusCode: false, followRedirect: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });

    ["finance-only", "church-admin"].forEach((persona) => {
        it(`${persona} gets the seeded Diaz pledges`, () => {
            cy.setupPersonaSession(persona);
            reportRequest().then((response) =>
                pdfLines(response).then((lines) => {
                    const text = lines.join(" ");
                    expect(text).to.include("Pledge Summary By Family");
                    expect(text).to.include("Diaz");
                    expect(text).to.include("Pledges");
                    expect(text).to.include("New Building Fund");
                    expect(text).to.include("11.00");
                    expect(text).to.include("333.00");
                    expect(text).to.match(/\bTotal\s+\$\s*344\.00\s+\$\s*0\.00\s+\$\s*344\.00\b/i);
                }),
            );
        });
    });

    it("filters by fund and family", () => {
        cy.setupPersonaSession("finance-only");
        reportRequest("FYID=23&funds%5B%5D=1&family%5B%5D=9&output=pdf").then((response) =>
            pdfLines(response).then((lines) => {
                const text = lines.join(" ");
                expect(text).to.include("Diaz");
                expect(text).to.include("Pledges");
                expect(text).to.include("11.00");
                expect(text).not.to.include("333.00");
                expect(text).not.to.include("New Building Fund");
            }),
        );
    });

    it("answers an empty result with the form instead of a redirect", () => {
        cy.setupPersonaSession("finance-only");
        reportRequest("FYID=22&family%5B%5D=9&output=pdf").then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("text/html");
            expect(response.body).to.include('id="familyPledgeSummaryMessage"');
        });
    });

    describe("report form", () => {
        beforeEach(() => {
            cy.setupFinanceOnlySession();
        });

        it("is listed with the pledge reports and opens on the current fiscal year", () => {
            cy.getSystemConfig("iFYMonth").then((fyMonth) => {
                const now = new Date();
                const currentFyId = now.getFullYear() - 1996 + (fyMonth > 1 && now.getMonth() + 1 >= fyMonth ? 1 : 0);

                cy.visit("/finance/reports");
                cy.get('#financeReports a[href$="/finance/reports/family-pledge-summary"]').first().click();
                cy.location("pathname").should("match", /\/finance\/reports\/family-pledge-summary$/);
                cy.get("#familyPledgeSummaryForm").should("exist");
                cy.get("#familyPledgeSummaryMessage").should("not.exist");
                cy.get("#FYID").should("have.value", String(currentFyId));
                cy.get("#FYID option[value='23']").should("exist");
                cy.get("#classList").should("have.class", "tomselected");
                cy.get("#family").should("have.class", "tomselected");
                cy.get("#fundsList").should("have.class", "tomselected");
            });
        });

        it("is no longer offered on the legacy report picker", () => {
            cy.visit("/FinancialReports.php?ReportType=Pledge%20Family%20Summary");
            cy.get("#FinancialReportTypes").should("exist");
            cy.get('#FinancialReportTypes option[value="Pledge Family Summary"]').should("not.exist");
        });

        it("downloads the summary for the filters a treasurer picks on the form", () => {
            cy.visit("/finance/reports/family-pledge-summary");
            cy.get("#FYID").select("23");
            cy.get("#family").select("9", { force: true });
            cy.get("#fundsList").select("1", { force: true });
            cy.intercept("POST", "**/finance/reports/family-pledge-summary/download", (req) => {
                const body = new URLSearchParams(req.body);
                expect(body.get("FYID")).to.eq("23");
                expect(body.getAll("family[]")).to.deep.eq(["9"]);
                expect(body.getAll("funds[]")).to.deep.eq(["1"]);
            }).as("download");
            cy.get("#downloadFamilyPledgeSummary").click();
            cy.wait("@download").then(({ response }) => {
                expect(response.statusCode).to.eq(200);
                expect(response.headers["content-type"]).to.include("application/pdf");
            });
        });

        it("remembers the fiscal year of the last download", () => {
            reportRequest("FYID=22&output=pdf").its("status").should("eq", 200);
            cy.visit("/finance/reports/family-pledge-summary");
            cy.get("#FYID").should("have.value", "22");
        });

        it("stays on the form with a no-rows message and the chosen filters", () => {
            cy.visit("/finance/reports/family-pledge-summary");
            cy.get("#FYID").select("22");
            cy.get("#family").select("9", { force: true });
            cy.get("#fundsList").select("1", { force: true });
            cy.get("#downloadFamilyPledgeSummary").click();

            cy.location("pathname").should("match", /\/finance\/reports\/family-pledge-summary\/download$/);
            cy.get("#familyPledgeSummaryMessage").should("be.visible").and("contain", "No Data Found");
            cy.get("#FYID").should("have.value", "22");
            cy.get("#family").invoke("val").should("deep.equal", ["9"]);
            cy.get("#fundsList").invoke("val").should("deep.equal", ["1"]);
        });
    });
});
