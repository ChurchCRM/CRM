/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

// Seed: family 9 (Diaz) pledged $11 and $333 for fiscal year 23 and nothing for any other year.
const missingPledges = (qs) =>
    cy.request({
        url: "/finance/reports/missing-pledges/download",
        qs,
        encoding: "binary",
        failOnStatusCode: false,
        followRedirect: false,
    });

describe("Missing Pledges report", () => {
    before(() => {
        cy.setupAdminSession();
        cy.useChurchTimeZone();
    });

    beforeEach(() => {
        cy.setupFinanceOnlySession();
    });

    after(() => {
        cy.useHostTimeZone();
    });

    it("is listed with the pledge reports", () => {
        cy.visit("/finance/reports");
        cy.get('#financeReports a[href$="/finance/reports/missing-pledges"]').first().click();
        cy.location("pathname").should("match", /\/finance\/reports\/missing-pledges$/);
        cy.get("#missingPledgesForm").should("exist");
    });

    it("defaults to families that pledged last fiscal year and not this year", () => {
        cy.getSystemConfig("iFYMonth").then((fyMonth) => {
            const now = new Date();
            const currentFyId = now.getFullYear() - 1996 + (fyMonth > 1 && now.getMonth() + 1 >= fyMonth ? 1 : 0);

            cy.visit("/finance/reports/missing-pledges");
            cy.get("#pledgedFY").should("have.value", String(currentFyId - 1));
            cy.get("#missingFY").should("have.value", String(currentFyId));
            cy.get(`#missingFY option[value="${currentFyId + 1}"]`).should("exist");
        });
    });

    it("lists a family that pledged for one year and not the other in the PDF", () => {
        missingPledges({ pledgedFY: 23, missingFY: 22, output: "pdf" }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body).then((lines) => {
                const text = lines.join(" ");
                expect(text).to.include("Missing Pledges");
                expect(text).to.include("Diaz");
                expect(text).to.include("1158 Harrison Ct");
                expect(text).to.match(/\$\s*344\.00/);
            });
        });
    });

    it("exports the same families as CSV", () => {
        missingPledges({ pledgedFY: 23, missingFY: 22, output: "csv" }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("text/csv");
            const [header, ...rows] = response.body.trim().split(/\r?\n/);
            expect(header).to.include("fam_ID");
            const diaz = rows.filter((row) => row.split(",")[0] === "9");
            expect(diaz).to.have.length(1);
            expect(diaz[0]).to.include("344.00");
        });
    });

    it("returns to the form with a message when no family matches", () => {
        missingPledges({ pledgedFY: 22, missingFY: 23, output: "pdf" }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("text/html");
            expect(response.body).to.include('id="missingPledgesMessage"');
        });
        cy.visit("/finance/reports/missing-pledges/download?pledgedFY=22&missingFY=23");
        cy.get("#missingPledgesMessage").should("be.visible");
        cy.get("#pledgedFY").should("have.value", "22");
        cy.get("#missingFY").should("have.value", "23");
    });

    describe("leaves out families", () => {
        let pledgeKey;

        before(() => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/payments/pledges",
                {
                    type: "Pledge",
                    iMethod: "CASH",
                    FamilyID: 9,
                    Date: "2018-03-01",
                    FYID: 22,
                    schedule: "Once",
                    FundSplit: JSON.stringify([{ FundID: "1", Amount: 50, NonDeductible: 0, Comment: "" }]),
                },
                200,
            ).then(({ body }) => {
                pledgeKey = body.groupKey;
            });
        });

        after(() => {
            cy.makePrivateAdminAPICall("DELETE", `/api/payments/${encodeURIComponent(pledgeKey)}`, null, [200, 404]);
        });

        it("that pledged for both years", () => {
            missingPledges({ pledgedFY: 23, missingFY: 22, output: "csv" }).then((response) => {
                expect(response.headers["content-type"]).to.include("text/html");
                expect(response.body).to.include('id="missingPledgesMessage"');
            });
            missingPledges({ pledgedFY: 22, missingFY: 21, output: "csv" }).then((response) => {
                expect(response.body).to.match(/^9,.*50\.00/m);
            });
        });

        describe("that are inactive", () => {
            before(() => {
                cy.setupAdminSession();
                cy.request("POST", "/api/family/9/activate/false");
            });

            after(() => {
                cy.setupAdminSession();
                cy.request("POST", "/api/family/9/activate/true");
            });

            it("even when they pledged", () => {
                missingPledges({ pledgedFY: 22, missingFY: 21, output: "csv" }).then((response) => {
                    expect(response.headers["content-type"]).to.include("text/html");
                    expect(response.body).to.include('id="missingPledgesMessage"');
                });
            });
        });
    });

    it("sends users without finance access to access denied", () => {
        cy.setupPersonaSession("office-staff");
        missingPledges({ pledgedFY: 23, missingFY: 22 }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });
});
