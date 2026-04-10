/// <reference types="cypress" />

/**
 * Test for issue: Failure to Generate Advanced Deposit Report Despite Valid Date Range
 *
 * Bug: When "Apply Report Dates To: Deposit Date" was selected, the Advanced Deposit
 * Report always returned "No Data Found" even when deposits existed in the date range.
 *
 * Root cause: PledgeQuery::filterForAdvancedDeposit() called useDepositQuery() twice —
 * once for dateStart and once for dateEnd. Each call created a separate Propel JOIN
 * context with a different alias, so no single row could satisfy both date conditions.
 *
 * Fix: Combine both date bounds into a single useDepositQuery() block so both
 * constraints are applied to the same JOIN alias.
 */

describe("Advanced Deposit Report - Deposit Date Filter (Issue Fix)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    /**
     * Core regression test: when the "Deposit Date" date-type filter is used and
     * deposits exist within the specified date range, the report must return data.
     *
     * This directly tests the bug introduced by calling useDepositQuery() twice in
     * PledgeQuery::filterForAdvancedDeposit(), which created two separate Propel JOIN
     * aliases and prevented any row from satisfying both date conditions.
     *
     * Uses a broad date range covering all demo data so no API call is required —
     * mixing cy.request() API-key calls with cy.setupAdminSession() UI session within
     * the same test would corrupt the server-side session.
     *
     * Note: Date inputs use Bootstrap Datepicker with format "yyyy-mm-dd" (the default
     * ChurchCRM sDatePickerPlaceHolder setting). Always use YYYY-MM-DD format when
     * interacting with these inputs to ensure the datepicker parses the value correctly.
     */
    it("should return data when filtering by Deposit Date with a matching date range", () => {
        cy.visit("/FinancialReports.php");
        cy.contains("Financial Reports").should("be.visible");

        cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Advanced Deposit Report").should("be.visible");

        // Broad date range guaranteed to include all demo-data deposits.
        // Use invoke('val') to bypass Bootstrap Datepicker keystroke interception —
        // .type() triggers datepicker events that mangle the input value.
        cy.get("#DateStart").invoke("val", "2018-01-01");
        cy.get("#DateEnd").invoke("val", "2099-12-31");

        // CRITICAL: select "Deposit Date" — the filter that was broken.
        cy.get("input[name='datetype'][value='Deposit']").check({ force: true });

        // Use CSV for reliable response inspection.
        cy.get("input[name='output'][value='csv']").check({ force: true });

        cy.intercept("POST", "**/AdvancedDeposit.php").as("reportRequest");
        cy.get("#createReport").click();

        // The fix ensures the report must NOT redirect to "No Data Found"
        // when deposits exist within the selected date range.
        cy.url().then((url) => {
            expect(url, "Report should return data — the Deposit Date filter JOIN fix must be working").to.not.include("ReturnMessage=NoRows");
        });

        cy.wait("@reportRequest", { timeout: 30000 }).then((interception) => {
            expect(interception.response.statusCode).to.equal(200);
            expect(interception.response.headers["content-type"]).to.include(
                "text/csv"
            );

            const lines = interception.response.body
                .split("\n")
                .filter((l) => l.trim() !== "");

            // Must have a header row and at least one data row.
            expect(lines.length).to.be.greaterThan(
                1,
                "CSV must contain at least one data row"
            );

            cy.log(
                "✅ Deposit Date filter returns data for broad date range — PledgeQuery single-JOIN fix is working"
            );
        });
    });

    /**
     * Boundary test: a date range that has no deposits (year 1900) must correctly
     * return "No Data Found". This ensures the fix does not suppress valid empty results.
     */
    it("should return 'No Data Found' when Deposit Date range excludes all deposits", () => {
        cy.visit("/FinancialReports.php");
        cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Advanced Deposit Report").should("be.visible");

        // Use invoke('val') to bypass Bootstrap Datepicker keystroke interception.
        cy.get("#DateStart").invoke("val", "1900-01-01");
        cy.get("#DateEnd").invoke("val", "1900-01-01");

        cy.get("input[name='datetype'][value='Deposit']").check({ force: true });
        cy.get("input[name='output'][value='csv']").check({ force: true });
        cy.get("#createReport").click();

        // Should redirect to "No Data Found" for a date range with no deposits.
        cy.url().should("include", "ReturnMessage=NoRows");
        cy.contains("No Data Found").should("be.visible");
        cy.log("✅ Correct 'No Data Found' response for an out-of-range date");
    });

    /**
     * PDF output test: Deposit Date filter must produce a valid PDF (not a server error or redirect).
     * PDF is the default output format — verifies the full render path works end-to-end.
     */
    it("should return a valid PDF when filtering by Deposit Date", () => {
        cy.visit("/FinancialReports.php");
        cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Advanced Deposit Report").should("be.visible");

        // Use invoke('val') to bypass Bootstrap Datepicker keystroke interception.
        cy.get("#DateStart").invoke("val", "2018-01-01");
        cy.get("#DateEnd").invoke("val", "2099-12-31");

        cy.get("input[name='datetype'][value='Deposit']").check({ force: true });
        // PDF is the default output — select explicitly for clarity.
        cy.get("input[name='output'][value='pdf']").check({ force: true });

        cy.intercept("POST", "**/AdvancedDeposit.php").as("pdfReport");
        cy.get("#createReport").click();

        cy.url().should("not.include", "ReturnMessage=NoRows");

        cy.wait("@pdfReport", { timeout: 30000 }).then((interception) => {
            expect(interception.response.statusCode).to.equal(200);

            const contentType = interception.response.headers["content-type"] || "";
            expect(contentType).to.include("application/pdf");

            // Verify the response is a real PDF by checking the %PDF- magic bytes.
            // cy.intercept may return binary bodies as ArrayBuffer in Cypress 15.
            const rawBody = interception.response.body;
            const isArrayBuffer =
                Object.prototype.toString.call(rawBody) === "[object ArrayBuffer]";
            const pdfHeader = isArrayBuffer
                ? new TextDecoder().decode(new Uint8Array(rawBody, 0, 5))
                : typeof rawBody === "string"
                  ? rawBody.slice(0, 5)
                  : "";
            expect(pdfHeader).to.equal("%PDF-");

            cy.log("✅ Deposit Date PDF report generated successfully");
        });
    });

    /**
     * Parity test: the Payment Date path must also return data for a broad range.
     * This confirms neither the fix nor any regression has broken the existing path.
     */
    it("should return data when filtering by Payment Date with a broad date range", () => {
        cy.visit("/FinancialReports.php");
        cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Advanced Deposit Report").should("be.visible");

        // Use invoke('val') to bypass Bootstrap Datepicker keystroke interception.
        cy.get("#DateStart").invoke("val", "2018-01-01");
        cy.get("#DateEnd").invoke("val", "2099-12-31");

        cy.get("input[name='datetype'][value='Payment']").check({ force: true });
        cy.get("input[name='output'][value='csv']").check({ force: true });

        cy.intercept("POST", "**/AdvancedDeposit.php").as("paymentDateReport");
        cy.get("#createReport").click();

        cy.url().should("not.include", "ReturnMessage=NoRows");

        cy.wait("@paymentDateReport", { timeout: 30000 }).then((interception) => {
            expect(interception.response.statusCode).to.equal(200);
            expect(interception.response.headers["content-type"]).to.include("text/csv");
            cy.log("✅ Payment Date filter returns data correctly");
        });
    });
});
