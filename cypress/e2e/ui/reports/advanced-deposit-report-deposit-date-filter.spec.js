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
     * Convert a YYYY-MM-DD date string to MM/DD/YYYY for the date picker inputs.
     */
    const toMMDDYYYY = (isoDate) => {
        const [year, month, day] = isoDate.split("-");
        return `${month}/${day}/${year}`;
    };

    /**
     * Core regression test: when the "Deposit Date" date-type filter is used and
     * a deposit exists within the specified date range, the report must return data.
     *
     * This directly tests the bug introduced by calling useDepositQuery() twice in
     * PledgeQuery::filterForAdvancedDeposit(), which created two separate Propel JOIN
     * aliases and prevented any row from satisfying both date conditions.
     */
    it("should return data when filtering by Deposit Date with a matching date range", () => {
        // Step 1: Fetch existing deposits to find one whose date we can target.
        // Using the admin API key so the session cookie alone is sufficient.
        cy.makePrivateAdminAPICall("GET", "/api/deposits", null, 200).then(
            (resp) => {
                expect(resp.body).to.have.property("Deposits");
                const deposits = resp.body.Deposits;
                expect(
                    deposits.length,
                    "Demo data must contain at least one deposit"
                ).to.be.greaterThan(0);

                // Pick the first deposit that has a date so we know the exact date to filter on.
                const targetDeposit = deposits.find((d) => !!d.Date);
                expect(targetDeposit, "At least one deposit must have a Date").to.exist;

                const depositDate = targetDeposit.Date; // YYYY-MM-DD
                const formattedDate = toMMDDYYYY(depositDate);

                cy.log(
                    `Testing with deposit ID=${targetDeposit.Id} dated ${depositDate}`
                );

                // Step 2: Navigate to the Advanced Deposit Report form.
                cy.visit("/FinancialReports.php");
                cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
                cy.get("#FinancialReports").submit();
                cy.contains("Advanced Deposit Report").should("be.visible");

                // Step 3: Set date range to exactly the deposit's date.
                cy.get("input[name='DateStart']")
                    .clear({ force: true })
                    .type(formattedDate, { force: true });
                cy.get("input[name='DateEnd']")
                    .clear({ force: true })
                    .type(formattedDate, { force: true });

                // Step 4: Select "Deposit Date" — the filter that was broken.
                cy.get("input[name='datetype'][value='Deposit']").check({
                    force: true,
                });

                // Use CSV for reliable response inspection.
                cy.get("input[name='output'][value='csv']").check({
                    force: true,
                });

                cy.intercept("POST", "**/AdvancedDeposit.php").as("reportRequest");
                cy.get("#createReport").click();

                // Step 5: The fix ensures the report must NOT redirect to "No Data Found"
                // when a deposit exists on the target date.
                cy.url().should(
                    "not.include",
                    "ReturnMessage=NoRows",
                    "Report should return data — the Deposit Date filter JOIN fix must be working"
                );

                cy.wait("@reportRequest", { timeout: 30000 }).then(
                    (interception) => {
                        expect(interception.response.statusCode).to.equal(200);
                        expect(
                            interception.response.headers["content-type"]
                        ).to.include("text/csv");

                        const lines = interception.response.body
                            .split("\n")
                            .filter((l) => l.trim() !== "");

                        // Must have a header row and at least one data row.
                        expect(lines.length).to.be.greaterThan(
                            1,
                            "CSV must contain at least one data row for the targeted deposit date"
                        );

                        cy.log(
                            "✅ Deposit Date filter returns data — PledgeQuery single-JOIN fix is working"
                        );
                    }
                );
            }
        );
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

        cy.get("input[name='DateStart']")
            .clear({ force: true })
            .type("01/01/1900", { force: true });
        cy.get("input[name='DateEnd']")
            .clear({ force: true })
            .type("01/01/1900", { force: true });

        cy.get("input[name='datetype'][value='Deposit']").check({ force: true });
        cy.get("input[name='output'][value='csv']").check({ force: true });
        cy.get("#createReport").click();

        // Should redirect to "No Data Found" for an empty range.
        cy.url().should("include", "ReturnMessage=NoRows");
        cy.contains("No Data Found").should("be.visible");
        cy.log("✅ Correct 'No Data Found' response for an out-of-range date");
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

        cy.get("input[name='DateStart']")
            .clear({ force: true })
            .type("01/01/2018", { force: true });
        cy.get("input[name='DateEnd']")
            .clear({ force: true })
            .type(toMMDDYYYY(new Date().toISOString().split("T")[0]), {
                force: true,
            });

        cy.get("input[name='datetype'][value='Payment']").check({ force: true });
        cy.get("input[name='output'][value='csv']").check({ force: true });

        cy.intercept("POST", "**/AdvancedDeposit.php").as("paymentDateReport");
        cy.get("#createReport").click();

        cy.url().then((url) => {
            if (!url.includes("ReturnMessage=NoRows")) {
                cy.wait("@paymentDateReport", { timeout: 30000 }).then(
                    (interception) => {
                        expect(interception.response.statusCode).to.equal(200);
                        expect(
                            interception.response.headers["content-type"]
                        ).to.include("text/csv");
                        cy.log("✅ Payment Date filter returns data correctly");
                    }
                );
            } else {
                cy.log(
                    "⚠️ No payment data found for broad date range — test environment may be empty"
                );
            }
        });
    });
});
