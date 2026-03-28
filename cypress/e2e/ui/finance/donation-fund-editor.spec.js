/// <reference types="cypress" />

/**
 * Donation Fund Editor - Regression Tests
 *
 * Covers the three bugs fixed in PR #8319:
 *
 * Bug 1 (Read): boolval('false') === true — Active always read back as Yes
 * Bug 2 (Write): PHP bool → enum('true','false') stored '1'/'' — MySQL rejected
 * Bug 3 (Delete): assignment '=' instead of comparison '==' — accidental deletion
 *
 * NOTE: Fund names in the existing-funds table are rendered as <input value="...">
 * (editable inline fields), NOT as plain text. Use input[value=] selectors, not
 * cy.contains("td", ...).
 */

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

    it("should delete a fund when both Action=delete and Fund param are present", () => {
        const disposableName = "CyDel" + Date.now();

        // Create fund via UI
        cy.visit("/DonationFundEditor.php");
        cy.get("#newFieldName").clear().type(disposableName);
        cy.get("button[name='AddField']").click();
        assertFundExists(disposableName);

        // Get the new fund's ID from the delete button onclick
        findFundRow(disposableName)
            .find("button.dropdown-item.text-danger")
            .then(($btn) => {
                const onclick = $btn.attr("onclick") || "";
                const match = onclick.match(
                    /confirmDeleteFund\([^,]+,\s*(\d+)\)/,
                );
                expect(match).to.not.be.null;
                const fundId = match[1];

                // Visit with BOTH params — should delete
                cy.visit(
                    `/DonationFundEditor.php?Fund=${fundId}&Action=delete`,
                );

                // Fund should no longer exist
                assertFundNotExists(disposableName);
            });
    });
});
