/// <reference types="cypress" />

/**
 * Issue #9803: Reminder Report returns 500 when no fund is selected.
 * @see https://github.com/ChurchCRM/CRM/issues/9803
 *
 * With no fund checked, Reports/ReminderReport.php never assigned the fund
 * filter string and then passed it to a parameter typed `string`, so the first
 * family that qualified for a letter threw a TypeError (HTTP 500). No fund
 * selected must mean "all funds in the chosen fiscal year", not a crash.
 *
 * Seed data: family 9 has two unpaid pledges (funds 1 and 2) in fiscal year 23,
 * so the default filters (pledges only, only families with unpaid pledges)
 * reach the letter-generation path that used to crash.
 *
 * The response is asserted through cy.intercept because cy.intercept in
 * Cypress 15 does not buffer binary bodies; the Content-Length header FPDF
 * sends is used for the size check instead of the body.
 */
describe("Reminder Report fund selection - Issue #9803", () => {
    // Fiscal year 23 is the only fiscal year with pledges in the Cypress seed.
    const fiscalYearId = "23";
    // A PDF with at least one reminder letter is well above this; the
    // pre-fix response was an empty text/html 500.
    const minimumPdfBytes = 1000;

    const expectPdfResponse = (interception) => {
        const { statusCode, headers } = interception.response;
        const contentType = headers["content-type"] || "";
        const contentLength = Number.parseInt(headers["content-length"] || "0", 10);

        expect(statusCode, "HTTP status").to.equal(200);
        expect(contentType, "Content-Type").to.include("application/pdf");
        expect(contentLength, "Content-Length").to.be.greaterThan(minimumPdfBytes);
    };

    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("/FinancialReports.php");
        cy.contains("Financial Reports");
        cy.get("#FinancialReportTypes").select("Pledge Reminders");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Pledge Reminders");
        cy.get('select[name="FYID"]').select(fiscalYearId);
        cy.intercept("POST", "**/Reports/ReminderReport.php").as("reminderReport");
    });

    it("generates a PDF across all funds when no fund is selected", () => {
        cy.get("#fundsList").invoke("val").should("be.empty");

        cy.get("#createReport").click();

        cy.wait("@reminderReport", { timeout: 30000 }).then(expectPdfResponse);
    });

    it("still generates a PDF when one fund is selected", () => {
        // TomSelect wraps the native multi-select (adding the `tomselected`
        // class) and hides it, so wait for it and set the value with force.
        cy.get("#fundsList").should("have.class", "tomselected");
        cy.get("#fundsList").select("1", { force: true });
        cy.get("#fundsList").invoke("val").should("deep.equal", ["1"]);

        cy.get("#createReport").click();

        cy.wait("@reminderReport", { timeout: 30000 }).then(expectPdfResponse);
    });
});
