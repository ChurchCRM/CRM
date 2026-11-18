/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

// Seed: family 9 (Diaz) has two unpaid pledges in fiscal year 23 and no pledge in any other year.
const reminders = (body) =>
    cy.request({
        method: "POST",
        url: "/finance/reports/pledge-reminders/download",
        form: true,
        body,
        encoding: "binary",
        failOnStatusCode: false,
        followRedirect: false,
    });

describe("Pledge Reminders report", () => {
    beforeEach(() => {
        cy.setupFinanceOnlySession();
    });

    it("is listed with the pledge reports and opens on the current fiscal year", () => {
        cy.getSystemConfig("iFYMonth").then((fyMonth) => {
            const now = new Date();
            const currentFyId = now.getFullYear() - 1996 + (fyMonth > 1 && now.getMonth() + 1 >= fyMonth ? 1 : 0);

            cy.visit("/finance/reports");
            cy.get('#financeReports a[href$="/finance/reports/pledge-reminders"]').first().click();
            cy.location("pathname").should("match", /\/finance\/reports\/pledge-reminders$/);
            cy.get("#pledgeRemindersForm").should("exist");
            cy.get("#pledgeRemindersMessage").should("not.exist");
            cy.get("#FYID").should("have.value", String(currentFyId));
            cy.get(`#FYID option[value="${currentFyId + 1}"]`).should("exist");
            cy.get("#FYID option[value='23']").should("exist");
            cy.get("#pledgeFilterPledge").should("be.checked");
            cy.get("#onlyOweYes").should("be.checked");
            cy.get("#fundsList").should("have.class", "tomselected");
        });
    });

    it("is no longer offered on the legacy report picker", () => {
        cy.visit("/FinancialReports.php?ReportType=Pledge%20Reminders");
        cy.get("#FinancialReportTypes").should("exist");
        cy.get('#FinancialReportTypes option[value="Pledge Reminders"]').should("not.exist");
    });

    it("downloads the letters for the filters a treasurer picks on the form", () => {
        cy.visit("/finance/reports/pledge-reminders");
        cy.get("#FYID").select("23");
        cy.get("#family").select("9", { force: true });
        cy.get("#fundsList").select(["1", "2"], { force: true });
        cy.intercept("POST", "**/finance/reports/pledge-reminders/download", (req) => {
            const body = new URLSearchParams(req.body);
            expect(body.get("FYID")).to.eq("23");
            expect(body.getAll("family[]")).to.deep.eq(["9"]);
            expect(body.getAll("funds[]").sort()).to.deep.eq(["1", "2"]);
            expect(body.get("pledge_filter")).to.eq("pledge");
            expect(body.get("only_owe")).to.eq("yes");
        }).as("download");
        cy.get("#downloadPledgeReminders").click();
        cy.wait("@download").then(({ response }) => {
            expect(response.statusCode).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
        });
        reminders({ FYID: "23", "family[]": "9" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body).then((lines) => {
                expect(lines.join(" ")).to.include("Diaz");
            });
        });
    });

    it("remembers the fiscal year of the last download", () => {
        reminders({ FYID: "22", pledge_filter: "all", only_owe: "no" }).its("status").should("eq", 200);
        cy.visit("/finance/reports/pledge-reminders");
        cy.get("#FYID").should("have.value", "22");
    });

    it("stays on the form with a no-rows message and the chosen filters", () => {
        cy.visit("/finance/reports/pledge-reminders");
        cy.get("#FYID").select("22");
        cy.get("#family").select("9", { force: true });
        cy.get("#fundsList").select("1", { force: true });
        cy.get("#onlyOweNo").check();
        cy.get("#downloadPledgeReminders").click();

        cy.location("pathname").should("match", /\/finance\/reports\/pledge-reminders\/download$/);
        cy.get("#pledgeRemindersMessage").should("be.visible").and("contain", "No Data Found");
        cy.get("#FYID").should("have.value", "22");
        cy.get("#family").invoke("val").should("deep.equal", ["9"]);
        cy.get("#fundsList").invoke("val").should("deep.equal", ["1"]);
        cy.get("#pledgeFilterPledge").should("be.checked");
        cy.get("#onlyOweNo").should("be.checked");
    });

    it("answers an empty request with the form instead of a redirect", () => {
        reminders({ FYID: "22", "family[]": "9" }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("text/html");
            expect(response.body).to.include('id="pledgeRemindersMessage"');
        });
    });

    it("sends users without finance access to access denied", () => {
        cy.setupPersonaSession("office-staff");
        ["/finance/reports/pledge-reminders", "/finance/reports/pledge-reminders/download?FYID=23"].forEach((url) => {
            cy.request({ url, failOnStatusCode: false, followRedirect: false }).then((response) => {
                expect(response.status).to.eq(302);
                expect(response.headers.location).to.include("v2/access-denied?role=Finance");
            });
        });
    });
});
