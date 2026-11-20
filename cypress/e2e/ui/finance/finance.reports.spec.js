/// <reference types="cypress" />

require("cy-verify-downloads").addCustomCommand();

describe("Financial Reports", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Navigate to Financial Reports from Dashboard", () => {
        cy.visit("/finance/");
        cy.contains("Finance Dashboard");

        cy.get('a[href$="/finance/reports"]').first().click();
        cy.url().should("contain", "/finance/reports");
        cy.contains("Financial Reports");
    });

    it("Navigate to Giving Report from Reports Index", () => {
        cy.visit("/finance/reports");
        cy.contains("Giving Statements").click();
        cy.url().should("contain", "/finance/reports/giving-statements");
    });

    it("Pledge Family Summary", () => {
        cy.visit("/finance/reports");
        cy.get('#financeReports a[href$="/finance/reports/family-pledge-summary"]').first().click();
        cy.location("pathname").should("match", /\/finance\/reports\/family-pledge-summary$/);

        cy.intercept("POST", "**/finance/reports/family-pledge-summary/download").as("familyPledgeSummary");
        cy.get("#FYID").select("23");
        cy.get("#downloadFamilyPledgeSummary").click();
        cy.wait("@familyPledgeSummary").then(({ response }) => {
            expect(response.statusCode).to.equal(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
        });
    });

    it("Pledge Reminders", () => {
        cy.visit("/finance/reports");
        cy.get('#financeReports a[href$="/finance/reports/pledge-reminders"]').first().click();
        cy.location("pathname").should("match", /\/finance\/reports\/pledge-reminders$/);

        cy.intercept("POST", "**/finance/reports/pledge-reminders/download").as("pledgeReminders");
        cy.get("#FYID").select("23");
        cy.get("#downloadPledgeReminders").click();
        cy.wait("@pledgeReminders").then(({ response }) => {
            expect(response.statusCode).to.equal(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
        });
    });

    it("Zero Givers", () => {
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        cy.get("#FinancialReportTypes").select("Zero Givers");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Zero Givers");
        cy.window().then(function (win) {
            win.document
                .getElementById("createReport")
                .addEventListener("click", () => {
                    setTimeout(function () {
                        win.location.reload();
                    }, 10_000);
                });

            /* Make sure the file exists */
            cy.intercept("/", (req) => {
                req.reply((res) => {
                    expect(res.statusCode).to.equal(200);
                });
            });

            cy.get("#createReport").click();
        });
    });

    it("Individual Deposit Report", () => {
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        cy.visit("/finance/reports/deposits");
        cy.get("#depositAdvancedSettings > summary").click();
        cy.contains("Deposit Reports");
        cy.window().then(function (win) {
            win.document
                .getElementById("createReport")
                .addEventListener("click", () => {
                    setTimeout(function () {
                        win.location.reload();
                    }, 10_000);
                });

            /* Make sure the file exists */
            cy.intercept("/", (req) => {
                req.reply((res) => {
                    expect(res.statusCode).to.equal(200);
                });
            });

            cy.get("#createReport").click();
        });
    });

    it("Advanced Deposit Report", () => {
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        cy.visit("/finance/reports/deposits");
        cy.get("#depositAdvancedSettings > summary").click();
        cy.contains("Deposit Reports");
        cy.window().then(function (win) {
            win.document
                .getElementById("createReport")
                .addEventListener("click", () => {
                    setTimeout(function () {
                        win.location.reload();
                    }, 10_000);
                });

            /* Make sure the file exists */
            cy.intercept("/", (req) => {
                req.reply((res) => {
                    expect(res.statusCode).to.equal(200);
                });
            });

            cy.get("#createReport").click();
        });
    });
});
