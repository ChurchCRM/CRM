/// <reference types="cypress" />

/**
 * Donation Fund Editor - Regression & Security Tests
 *
 * Covers the three bugs fixed in PR #8319:
 *
 * Bug 1 (Read): boolval('false') === true — Active always read back as Yes
 * Bug 2 (Write): PHP bool → enum('true','false') stored '1'/'' — MySQL rejected
 * Bug 3 (Delete): assignment '=' instead of comparison '==' — accidental deletion
 *
 * Also covers the CSRF/method-guard fix (GHSA-68xh-3jh8-3wvq):
 *   - DonationFundRowOps.php now requires POST + valid CSRF token for
 *     delete / up / down actions.
 *   - GET requests to those actions return 405 Method Not Allowed.
 *   - POST without a valid CSRF token returns 403 Forbidden.
 *   - Reorder (up/down) works via the js-reorder-fund UI flow (POST+CSRF).
 *
 * NOTE: Fund names in the existing-funds table are rendered as <input value="...">
 * (editable inline fields), NOT as plain text. Use input[value=] selectors, not
 * cy.contains("td", ...).
 *
 * NOTE: cy.request() resets the PHP session (Set-Cookie: PHPSESSID overwrites the
 * browser cookie). After any cy.request() call, use freshAdminLogin() before
 * the next cy.visit() — see cypress-testing.md skill.
 */

/**
 * Local helper — NOT a cy.* command. Re-authenticates the browser session after
 * cy.request() has clobbered the PHP session cookie.
 */
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(
        Cypress.env("admin.password") + "{enter}",
    );
    cy.url().should("not.include", "/session/begin");
}

/**
 * Extract the CSRF token embedded in the inline JavaScript of DonationFundEditor.php.
 * The token appears as: ['csrf_token', "abcdef..."]
 */
function extractCsrfFromDonationFundEditor(html) {
    // Match the inline JS pattern: ['csrf_token', "<hex-token>"]
    const m = html.match(/'csrf_token',\s*"([a-f0-9]+)"/i);
    expect(m, "csrf_token must be present in DonationFundEditor.php inline JS").to.not.be.null;
    return m[1];
}

/**
 * Find a table row by the fund name inside its name <input>.
 * Returns the <tr> that contains an input whose value matches.
 */
function findFundRow(name) {
    return cy.get(`tbody input[name$='name'][value='${name}']`).closest("tr");
}

/**
 * Assert a fund with the given name exists in the table.
 */
function assertFundExists(name) {
    cy.get(`tbody input[name$='name'][value='${name}']`).should("exist");
}

/**
 * Assert a fund with the given name does NOT exist in the table.
 */
function assertFundNotExists(name) {
    cy.get(`tbody input[name$='name'][value='${name}']`).should("not.exist");
}

describe("Donation Fund Editor - Access & Load", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should load the donation fund editor for admins", () => {
        cy.visit("/DonationFundEditor.php");
        cy.contains("Donation Fund Editor");
        cy.contains("Add New Fund");
    });

    it("should display the existing funds table when funds exist", () => {
        cy.visit("/DonationFundEditor.php");
        cy.get("body").then(($body) => {
            if ($body.find("table.table-hover").length > 0) {
                cy.contains("Existing Donation Funds");
                cy.contains("th", "Name");
                cy.contains("th", "Active");
            }
        });
    });
});

describe("Donation Fund Editor - Add Fund", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should show error when adding a fund with no name", () => {
        cy.visit("/DonationFundEditor.php");
        cy.get("#newFieldName").clear();
        cy.get("button[name='AddField']").click();
        cy.contains("You must enter a name");
    });

    it("should show error when adding a fund with a duplicate name", () => {
        cy.visit("/DonationFundEditor.php");

        // Read the first existing fund's name from its input value
        cy.get("tbody tr:first-child input[name$='name']")
            .invoke("val")
            .then((existingName) => {
                if (existingName && existingName.length > 0) {
                    cy.get("#newFieldName").clear().type(existingName);
                    cy.get("button[name='AddField']").click();
                    cy.contains("That fund name already exists");
                }
            });
    });

    it("should successfully add a new fund", () => {
        const uniqueName = "CyAdd" + Date.now();

        cy.visit("/DonationFundEditor.php");
        cy.get("#newFieldName").clear().type(uniqueName);
        cy.get("#newFieldDesc").clear().type("Cypress test fund");
        cy.get("button[name='AddField']").click();

        // After POST the page reloads — fund name appears as an <input value>
        assertFundExists(uniqueName);
    });
});

describe("Donation Fund Editor - Active Flag (Regression: Bugs 1 & 2)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    /**
     * Bug 1 + 2 regression:
     *   1. Create a test fund via UI
     *   2. Set Active = No, save
     *   3. Reload and verify No persists
     *   4. Set Active = Yes, save
     *   5. Reload and verify Yes persists
     */
    it("should persist Active flag through No → Yes round-trip", () => {
        const testFundName = "CyActive" + Date.now();

        // Step 1: Create fund via UI
        cy.visit("/DonationFundEditor.php");
        cy.get("#newFieldName").clear().type(testFundName);
        cy.get("#newFieldDesc").clear().type("Active flag regression");
        cy.get("button[name='AddField']").click();
        assertFundExists(testFundName);

        // Step 2: Set Active = No and save
        findFundRow(testFundName).within(() => {
            cy.get("input[type='radio'][value='0']").check({ force: true });
        });
        cy.get("button[name='SaveChanges']").click();

        // Step 3: Explicit reload — verify No is checked
        cy.visit("/DonationFundEditor.php");
        findFundRow(testFundName).within(() => {
            cy.get("input[type='radio'][value='0']").should("be.checked");
            cy.get("input[type='radio'][value='1']").should("not.be.checked");
        });

        // Step 4: Set Active = Yes and save
        findFundRow(testFundName).within(() => {
            cy.get("input[type='radio'][value='1']").check({ force: true });
        });
        cy.get("button[name='SaveChanges']").click();

        // Step 5: Explicit reload — verify Yes is checked
        cy.visit("/DonationFundEditor.php");
        findFundRow(testFundName).within(() => {
            cy.get("input[type='radio'][value='1']").should("be.checked");
            cy.get("input[type='radio'][value='0']").should("not.be.checked");
        });
    });
});

describe("Donation Fund Editor - Delete Safety (Regression: Bug 3)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    /**
     * Bug 3 regression: visiting ?Fund=X without ?Action=delete must NOT delete.
     */
    it("should NOT delete a fund when visiting with ?Fund param but no Action=delete", () => {
        cy.visit("/DonationFundEditor.php");

        cy.get("tbody tr")
            .its("length")
            .then((fundCount) => {
                cy.get("button.dropdown-item.text-danger")
                    .first()
                    .then(($btn) => {
                        const onclick = $btn.attr("onclick") || "";
                        const match = onclick.match(
                            /confirmDeleteFund\([^,]+,\s*(\d+)\)/,
                        );
                        expect(match).to.not.be.null;
                        const fundId = match[1];

                        // Visit with ?Fund= but WITHOUT ?Action=delete
                        cy.visit(
                            `/DonationFundEditor.php?Fund=${fundId}`,
                        );

                        // Fund count must be unchanged
                        cy.get("tbody tr").should(
                            "have.length",
                            fundCount,
                        );
                    });
            });
    });

    /**
     * GHSA-68xh-3jh8-3wvq fix: DonationFundRowOps.php now requires POST.
     * A plain GET with ?Action=delete must return 405 Method Not Allowed.
     */
    it("should reject GET requests to DonationFundRowOps with Action=delete (405)", () => {
        cy.request({
            method: "GET",
            url: "/DonationFundRowOps.php?FundID=1&Action=delete",
            followRedirect: false,
            failOnStatusCode: false,
        }).its("status").should("eq", 405);
    });

    /**
     * GHSA-68xh-3jh8-3wvq fix: POST without a valid CSRF token must return 403 Forbidden.
     */
    it("should reject POST to DonationFundRowOps without valid CSRF token (403)", () => {
        cy.request({
            method: "POST",
            url: "/DonationFundRowOps.php",
            form: true,
            body: {
                FundID: "1",
                Action: "delete",
                csrf_token: "bogus-invalid-token",
            },
            failOnStatusCode: false,
        }).its("status").should("eq", 403);
    });

    /**
     * Full delete flow: create fund via UI, capture CSRF token from the page,
     * POST delete via cy.request() (session-sharing), verify fund is removed.
     *
     * Uses cy.request() for the POST (not cy.visit()) so that the page that
     * served the CSRF token shares the same PHP session as the delete request.
     * After cy.request() calls, freshAdminLogin() re-establishes the browser
     * session before any subsequent cy.visit().
     */
    it("should delete a fund via POST+CSRF through DonationFundRowOps", () => {
        const disposableName = "CyDel" + Date.now();

        // Step 1: Create the fund via UI
        cy.visit("/DonationFundEditor.php");
        cy.get("#newFieldName").clear().type(disposableName);
        cy.get("button[name='AddField']").click();
        assertFundExists(disposableName);

        // Step 2: Get the fund ID from the delete button onclick attribute
        findFundRow(disposableName)
            .find("button.dropdown-item.text-danger")
            .then(($btn) => {
                const onclick = $btn.attr("onclick") || "";
                const match = onclick.match(
                    /confirmDeleteFund\([^,]+,\s*(\d+)\)/,
                );
                expect(match, "fund ID must be in onclick attr").to.not.be.null;
                const fundId = match[1];

                // Step 3: GET the editor page to capture its CSRF token.
                // cy.request() shares cookies with the current browser session so
                // the PHP session (and its CSRF token) is the same one the browser holds.
                cy.request("/DonationFundEditor.php").then((res) => {
                    const csrfToken = extractCsrfFromDonationFundEditor(res.body);

                    // Step 4: POST the delete with the valid CSRF token.
                    // followRedirect:false so we can assert the 302 status; the
                    // redirect itself goes back to DonationFundEditor.php.
                    cy.request({
                        method: "POST",
                        url: "/DonationFundRowOps.php",
                        form: true,
                        followRedirect: false,
                        failOnStatusCode: false,
                        body: {
                            FundID: fundId,
                            Action: "delete",
                            csrf_token: csrfToken,
                        },
                    }).then((post) => {
                        // A successful delete redirects to DonationFundEditor.php?Action=delete
                        expect(post.status).to.eq(302);
                        expect(post.headers.location || "").to.include(
                            "DonationFundEditor.php",
                        );
                    });

                    // Step 5: Re-establish the browser session (cy.request() resets
                    // the PHP session cookie), then verify the fund is gone.
                    freshAdminLogin();
                    cy.visit("/DonationFundEditor.php");
                    assertFundNotExists(disposableName);
                });
            });
    });
});

describe("Donation Fund Editor - Reorder (GHSA-68xh-3jh8-3wvq CSRF fix)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    /**
     * GET with Action=up must return 405 (reorder also requires POST).
     */
    it("should reject GET requests to DonationFundRowOps with Action=up (405)", () => {
        cy.request({
            method: "GET",
            url: "/DonationFundRowOps.php?FundID=1&Action=up",
            followRedirect: false,
            failOnStatusCode: false,
        }).its("status").should("eq", 405);
    });

    /**
     * GET with Action=down must return 405.
     */
    it("should reject GET requests to DonationFundRowOps with Action=down (405)", () => {
        cy.request({
            method: "GET",
            url: "/DonationFundRowOps.php?FundID=1&Action=down",
            followRedirect: false,
            failOnStatusCode: false,
        }).its("status").should("eq", 405);
    });

    /**
     * POST without valid CSRF token for reorder must return 403.
     */
    it("should reject POST to DonationFundRowOps with invalid CSRF token for reorder (403)", () => {
        cy.request({
            method: "POST",
            url: "/DonationFundRowOps.php",
            form: true,
            body: {
                FundID: "1",
                Action: "up",
                csrf_token: "bogus-token",
            },
            failOnStatusCode: false,
        }).its("status").should("eq", 403);
    });

    /**
     * Reorder UI flow: when there are ≥2 funds, clicking the "Move down" button
     * on the first fund (via the js-reorder-fund POST mechanism) swaps the
     * first two funds. Verify by checking DOM order before and after.
     *
     * Uses cy.request() to obtain the CSRF token, then performs the reorder
     * via POST directly (bypassing the JS click + form-submit so that there
     * is no bootbox confirmation dialog to dismiss).
     */
    it("should reorder funds via POST+CSRF through DonationFundRowOps", () => {
        cy.visit("/DonationFundEditor.php");

        // Only run reorder test when there are at least 2 funds
        cy.get("tbody tr").then(($rows) => {
            if ($rows.length < 2) {
                cy.log("Skipping reorder test: fewer than 2 funds exist");
                return;
            }

            // Capture the first two fund names and IDs before reorder
            cy.get("tbody tr:nth-child(1) input[name$='name']")
                .invoke("val")
                .then((firstName) => {
                    cy.get("tbody tr:nth-child(2) input[name$='name']")
                        .invoke("val")
                        .then((secondName) => {
                            // Get the first fund's ID from its "Move down" button
                            cy.get("tbody tr:nth-child(1) .js-reorder-fund[data-direction='down']")
                                .invoke("data", "fund-id")
                                .then((fundId) => {
                                    // GET editor to obtain a CSRF token in the same PHP session
                                    cy.request("/DonationFundEditor.php").then((res) => {
                                        const csrfToken = extractCsrfFromDonationFundEditor(res.body);

                                        // POST the "down" action with valid CSRF
                                        cy.request({
                                            method: "POST",
                                            url: "/DonationFundRowOps.php",
                                            form: true,
                                            followRedirect: false,
                                            failOnStatusCode: false,
                                            body: {
                                                FundID: String(fundId),
                                                Action: "down",
                                                csrf_token: csrfToken,
                                            },
                                        }).then((post) => {
                                            expect(post.status).to.eq(302);
                                        });

                                        // Re-establish browser session after cy.request()
                                        freshAdminLogin();
                                        cy.visit("/DonationFundEditor.php");

                                        // After moving the first fund down, the second fund
                                        // should now appear in row 1 and the first in row 2
                                        cy.get("tbody tr:nth-child(1) input[name$='name']")
                                            .invoke("val")
                                            .should("eq", secondName);
                                        cy.get("tbody tr:nth-child(2) input[name$='name']")
                                            .invoke("val")
                                            .should("eq", firstName);
                                    });
                                });
                        });
                });
        });
    });
});
