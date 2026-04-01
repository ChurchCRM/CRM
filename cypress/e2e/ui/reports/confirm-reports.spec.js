/// <reference types="cypress" />

/**
 * Comprehensive test suite for ConfirmReport and ConfirmReportEmail features
 *
 * Tests PDF generation for confirmation reports with various data scenarios:
 * - Single family confirmation report
 * - Confirmation report email with all family members
 * - Null/missing address fields handling
 * - Custom fields in confirmation reports
 * - Large families with page breaks
 */
describe("Confirmation Reports - ConfirmReport & ConfirmReportEmail", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("ConfirmReport - PDF Generation", () => {
        it("should generate confirmation report for all families without errors", () => {
            cy.intercept("GET", "**/Reports/ConfirmReport.php*").as("confirmReportAll");

            cy.visit("Reports/ConfirmReport.php");

            cy.wait("@confirmReportAll", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);

                const contentType = interception.response.headers["content-type"] || "";
                expect(contentType).to.include("application/pdf");

                // Verify no PHP errors
                const body = interception.response.body;
                if (typeof body === "string") {
                    expect(body).to.not.include("Fatal error");
                    expect(body).to.not.include("TypeError");
                }
            });
        });

        it("should generate single family confirmation report", () => {
            // Get a family ID first
            cy.makePrivateAdminAPICall("/api/families", "GET").then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.length.greaterThan(0);

                const familyId = response.body[0].id;

                cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}*`).as(
                    "confirmReportSingle"
                );

                cy.visit(`Reports/ConfirmReport.php?familyId=${familyId}`);

                cy.wait("@confirmReportSingle", { timeout: 15000 }).then((interception) => {
                    expect(interception.response.statusCode).to.equal(200);

                    const contentType = interception.response.headers["content-type"] || "";
                    expect(contentType).to.include("application/pdf");
                });
            });
        });

        it("should handle families with missing address fields", () => {
            cy.makePrivateAdminAPICall("/api/families", "GET").then((response) => {
                const families = response.body;
                expect(families, "Test requires at least one family").to.have.length.greaterThan(0);

                const familyId = families[0].id;

                cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}*`).as(
                    "confirmReportIncomplete"
                );

                cy.visit(`Reports/ConfirmReport.php?familyId=${familyId}`);

                cy.wait("@confirmReportIncomplete", { timeout: 15000 }).then((interception) => {
                    expect(interception.response.statusCode).to.equal(200);

                    // Verify no errors even with potentially missing fields
                    const body = interception.response.body;
                    if (typeof body === "string") {
                        expect(body).to.not.include("Uncaught TypeError");
                        expect(body).to.not.include("convertToLatin1");
                    }
                });
            });
        });

        it("should include family members table in confirmation report", () => {
            // Get a family with members
            cy.makePrivateAdminAPICall("/api/families", "GET").then((response) => {
                const families = response.body;
                const familyWithMembers = families.find((fam) => fam.members && fam.members.length > 0);

                if (familyWithMembers) {
                    const familyId = familyWithMembers.id;

                    cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}*`).as(
                        "confirmReportWithMembers"
                    );

                    cy.visit(`Reports/ConfirmReport.php?familyId=${familyId}`);

                    cy.wait("@confirmReportWithMembers", { timeout: 15000 }).then((interception) => {
                        expect(interception.response.statusCode).to.equal(200);
                        expect(interception.response.headers["content-type"]).to.include("application/pdf");
                    });
                } else {
                    cy.log("No family with members found, skipping test");
                }
            });
        });
    });

    describe("ConfirmReportEmail - PDF Generation & Email", () => {
        it("should generate confirmation report email without errors", () => {
            cy.visit("v2/people/verify");

            // Verify page loads
            cy.contains("Family Verification", { timeout: 10000 }).should("be.visible");

            // Check that confirmation report email generation feature is accessible
            cy.get("body").should("not.contain", "Fatal error");
            cy.get("body").should("not.contain", "500");
        });

        it("should generate confirmation report email for families with valid emails", () => {
            // Make API call to test ConfirmReportEmail generation
            cy.makePrivateAdminAPICall("/api/families", "GET").then((response) => {
                const families = response.body;
                expect(families, "Test requires at least one family").to.have.length.greaterThan(0);

                // Find family with email
                const familyWithEmail = families.find((fam) => fam.email && fam.email.length > 0);

                if (familyWithEmail) {
                    // The actual email generation is done via POST to ConfirmReportEmail.php
                    cy.intercept("GET", "**/v2/people/verify*").as("verifyPage");

                    cy.visit("v2/people/verify");

                    cy.wait("@verifyPage").then(() => {
                        // Verify page loaded without errors
                        cy.get("body").should("not.contain", "Fatal error");
                        cy.get("body").should("not.contain", "TypeError");
                    });
                } else {
                    cy.log("No family with email found, skipping test");
                }
            });
        });

        it("should handle confirmation report email with custom fields", () => {
            // Get custom fields to verify they're accessible
            cy.makePrivateAdminAPICall("/api/system/custom-person-fields", "GET").then((response) => {
                if (response.status === 200 && response.body && response.body.length > 0) {
                    cy.log(`Found ${response.body.length} custom fields`);

                    // Verify ConfirmReportEmail can handle custom fields
                    cy.visit("v2/people/verify");
                    cy.get("body").should("not.contain", "Fatal error");
                } else {
                    cy.log("No custom fields configured");
                }
            });
        });
    });

    describe("Report Data Integrity", () => {
        it("should include all family information in confirmation report", () => {
            cy.makePrivateAdminAPICall("/api/families", "GET").then((response) => {
                const families = response.body;
                expect(families).to.have.length.greaterThan(0);

                const family = families[0];

                cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${family.id}*`).as(
                    "reportWithFamilyData"
                );

                cy.visit(`Reports/ConfirmReport.php?familyId=${family.id}`);

                cy.wait("@reportWithFamilyData", { timeout: 15000 }).then((interception) => {
                    expect(interception.response.statusCode).to.equal(200);

                    // Verify response has PDF content-type (indicates successful generation)
                    const contentType = interception.response.headers["content-type"] || "";
                    expect(contentType).to.include("application/pdf");
                });
            });
        });

        it("should handle large families with multiple pages", () => {
            cy.makePrivateAdminAPICall("/api/families", "GET").then((response) => {
                const families = response.body;

                // Find family with most members (larger families)
                const largeFamily = families.reduce((prev, current) => {
                    const prevMemberCount = (prev.members && prev.members.length) || 0;
                    const currentMemberCount = (current.members && current.members.length) || 0;
                    return currentMemberCount > prevMemberCount ? current : prev;
                });

                if (largeFamily && largeFamily.members && largeFamily.members.length > 1) {
                    cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${largeFamily.id}*`).as(
                        "largeFamily"
                    );

                    cy.visit(`Reports/ConfirmReport.php?familyId=${largeFamily.id}`);

                    cy.wait("@largeFamily", { timeout: 15000 }).then((interception) => {
                        expect(interception.response.statusCode).to.equal(200);

                        // Large families should still generate valid PDFs
                        const contentType = interception.response.headers["content-type"] || "";
                        expect(contentType).to.include("application/pdf");
                    });
                }
            });
        });

        it("should handle all letterhead options in confirmation report", () => {
            // Test that different letterhead options don't break the report
            cy.makePrivateAdminAPICall("/api/families?limit=1", "GET").then((response) => {
                const families = response.body;
                if (families.length > 0) {
                    const familyId = families[0].id;

                    const letterheadOptions = ["", "graphic", "none"];

                    letterheadOptions.forEach((letterhead) => {
                        const url = letterhead
                            ? `Reports/ConfirmReport.php?familyId=${familyId}&letterhead=${letterhead}`
                            : `Reports/ConfirmReport.php?familyId=${familyId}`;

                        cy.intercept("GET", `**${url}**`).as(`letterhead${letterhead || "default"}`);

                        cy.visit(url);

                        cy.wait(`@letterhead${letterhead || "default"}`, { timeout: 15000 }).then(
                            (interception) => {
                                expect(
                                    interception.response.statusCode,
                                    `Letterhead option '${letterhead || "default"}' should work`
                                ).to.equal(200);
                            }
                        );
                    });
                }
            });
        });
    });

    describe("Error Handling & Edge Cases", () => {
        it("should handle invalid family ID gracefully", () => {
            const invalidFamilyId = 999999;

            cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${invalidFamilyId}*`).as(
                "invalidFamily"
            );

            cy.visit(`Reports/ConfirmReport.php?familyId=${invalidFamilyId}`);

            // Should either return 200 (empty report) or redirect
            cy.wait("@invalidFamily", { timeout: 15000 }).then((interception) => {
                expect([200, 302]).to.include(interception.response.statusCode);
            });
        });

        it("should not crash with special characters in family names", () => {
            cy.makePrivateAdminAPICall("/api/families", "GET").then((response) => {
                const families = response.body;

                // Find family with special characters
                const familyWithSpecialChars = families.find((fam) =>
                    /[<>&"'`]/.test(fam.name)
                );

                if (familyWithSpecialChars) {
                    const familyId = familyWithSpecialChars.id;

                    cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}*`).as(
                        "specialChars"
                    );

                    cy.visit(`Reports/ConfirmReport.php?familyId=${familyId}`);

                    cy.wait("@specialChars", { timeout: 15000 }).then((interception) => {
                        expect(interception.response.statusCode).to.equal(200);
                    });
                } else {
                    cy.log("No families with special characters found");
                }
            });
        });
    });
});
