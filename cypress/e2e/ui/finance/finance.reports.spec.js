/// <reference types="cypress" />

require("cy-verify-downloads").addCustomCommand();

describe("Financial Reports", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Navigate to Financial Reports from Dashboard", () => {
        cy.visit("/finance/");
        cy.contains("Finance Dashboard");

        // Click Reports from action buttons
        cy.get('.card a.btn[href$="/finance/reports"]').first().click();
        cy.url().should("contain", "/finance/reports");
        cy.contains("Financial Reports");
    });

    it("Navigate to Giving Report from Reports Index", () => {
        cy.visit("/finance/reports");
        cy.contains("Giving Statements").click();
        cy.url().should("contain", "/finance/reports/giving-statements");
    });

    it("Pledge Family Summary", () => {
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        cy.get("#FinancialReportTypes").select("Pledge Family Summary");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Pledge Family Summary");
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

    it("Pledge Reminders", () => {
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        cy.get("#FinancialReportTypes").select("Pledge Reminders");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Pledge Reminders");
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

    it("Voting Members", () => {
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        cy.get("#FinancialReportTypes").select("Voting Members");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Voting Members");
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
        cy.get("#FinancialReportTypes").select("Individual Deposit Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Individual Deposit Report");
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
        cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Advanced Deposit Report");
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
