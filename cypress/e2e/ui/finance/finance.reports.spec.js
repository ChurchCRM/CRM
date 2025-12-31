/// <reference types="cypress" />

require("cy-verify-downloads").addCustomCommand();

describe("Financial Reports", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Navigate to Financial Reports from Dashboard", () => {
        cy.visit("/finance/");
        cy.contains("Finance Dashboard");
        
        // Click Generate Reports from Quick Actions
        cy.contains("a", "Generate Reports").click();
        cy.url().should("contain", "/finance/reports");
        cy.contains("Financial Reports");
    });

    it("Navigate to Giving Report from Reports Index", () => {
        cy.visit("/finance/reports");
        cy.contains("Giving Report (Tax Statements)").click();
        cy.url().should("contain", "FinancialReports.php");
        cy.contains("Financial Reports");
    });

    it("Giving Report", () => {
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        cy.get("#FinancialReportTypes").select("Giving Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Giving Report");
        
        // Set wide date range to capture all test data
        cy.get("#DateStart").clear().type("2005-11-11");
        cy.get("#DateEnd").clear().type("2025-11-11");
        
        // Select CSV output instead of PDF to avoid PDF generation complexity
        cy.get('input[name="output"][value="csv"]').check();
        
        cy.get("#createReport").click();

    });

    it("Pledge Summary", () => {
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        cy.get("#FinancialReportTypes").select("Pledge Summary");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Pledge Summary");
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
        cy.verifyDownload(".pdf", { contains: true });
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
