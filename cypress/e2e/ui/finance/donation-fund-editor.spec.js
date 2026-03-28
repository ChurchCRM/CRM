/// <reference types="cypress" />

/**
 * Donation Fund Editor - Regression Tests
 *
 * Covers the three bugs fixed in PR #8319:
 *
 * Bug 1 (Read): boolval('false') === true
 *   The fun_Active column stores literal strings 'true'/'false'.
 *   boolval() treats any non-empty string as truthy, so 'false' was
 *   always read back as Active=Yes.
 *
 * Bug 2 (Write): PHP bool cast to enum('true','false')
 *   setActive() received a PHP boolean which Propel casts to '1'/'' —
 *   neither is a valid enum value, so MySQL silently rejected the write.
 *
 * Bug 3 (Delete): assignment '=' instead of comparison '=='
 *   if ($sAction = 'delete' && strlen($sFund) > 0) — the assignment
 *   always evaluated truthy when ?Fund= was present in the URL,
 *   deleting a fund on any page visit carrying that query param.
 */

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
        // The demo database always has at least one fund
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
        // Leave name blank, submit
        cy.get("#newFieldName").clear();
        cy.contains("button", "Add New Fund").click();
        cy.contains("You must enter a name");
    });

    it("should show error when adding a fund with a duplicate name", () => {
        cy.visit("/DonationFundEditor.php");

        // Get the first existing fund name to use as duplicate
        cy.get("tbody tr:first-child input[name$='name']").invoke("val").then((existingName) => {
            if (existingName && existingName.length > 0) {
                cy.get("#newFieldName").clear().type(existingName);
                cy.contains("button", "Add New Fund").click();
                cy.contains("That fund name already exists");
            }
        });
    });

    it("should successfully add a new fund", () => {
        const uniqueName = "Test Fund " + Date.now();

        cy.visit("/DonationFundEditor.php");
        cy.get("#newFieldName").clear().type(uniqueName);
        cy.get("#newFieldDesc").clear().type("Cypress regression test fund");
        cy.contains("button", "Add New Fund").click();

        // Fund should now appear in the table
        cy.contains(uniqueName);
        cy.contains("Existing Donation Funds");
    });
});

describe("Donation Fund Editor - Active Flag (Regression: Bugs 1 & 2)", () => {
    const testFundName = "Active Flag Test " + Date.now();

    before(() => {
        // Create a dedicated test fund so we control its state
        cy.setupAdminSession();
        cy.visit("/DonationFundEditor.php");
        cy.get("#newFieldName").clear().type(testFundName);
        cy.get("#newFieldDesc").clear().type("Created by Cypress for active-flag regression");
        cy.contains("button", "Add New Fund").click();
        cy.contains(testFundName); // confirm it was created
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    /**
     * Bug 1 + 2 regression: Set Active = No, save, reload → must still read back as No.
     * With the old code, boolval('false') === true caused it to always read back Yes,
     * and PHP bool cast to '1'/'0' caused the write to silently fail.
     */
    it("should persist Active=No after save and reload", () => {
        cy.visit("/DonationFundEditor.php");

        // Find the row for our test fund and select "No"
        cy.contains("tbody tr", testFundName).within(() => {
            cy.get("input[type='radio'][value='0']").check({ force: true });
        });

        cy.contains("button[name='SaveChanges']", "Save Changes").click();

        // After save the page reloads — verify the radio reads back "No"
        cy.contains("tbody tr", testFundName).within(() => {
            cy.get("input[type='radio'][value='0']").should("be.checked");
            cy.get("input[type='radio'][value='1']").should("not.be.checked");
        });
    });

    /**
     * Round-trip: toggle back to Yes, save, reload → reads back Yes.
     */
    it("should persist Active=Yes after toggling back and saving", () => {
        cy.visit("/DonationFundEditor.php");

        cy.contains("tbody tr", testFundName).within(() => {
            cy.get("input[type='radio'][value='1']").check({ force: true });
        });

        cy.contains("button[name='SaveChanges']", "Save Changes").click();

        cy.contains("tbody tr", testFundName).within(() => {
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
     * Bug 3 regression: visiting ?Fund=X without ?Action=delete must NOT delete the fund.
     * Old code: `if ($sAction = 'delete' && strlen($sFund) > 0)` — the assignment
     * always triggered deletion whenever ?Fund= was in the URL.
     * Fixed code: `if ($sAction == 'delete' && strlen($sFund) > 0)`
     */
    it("should NOT delete a fund when visiting with ?Fund param but no Action=delete", () => {
        // First visit: count total funds and capture first fund ID
        cy.visit("/DonationFundEditor.php");

        let fundCount = 0;
        cy.get("tbody tr").then(($rows) => {
            fundCount = $rows.length;
        });

        // Extract the ID of the first fund from its delete link
        cy.get(".dropdown-item.text-danger").first().then(($btn) => {
            // The onclick calls confirmDeleteFund(name, id) — extract the id
            const onclick = $btn.attr("onclick") || "";
            const match = onclick.match(/confirmDeleteFund\([^,]+,\s*(\d+)\)/);
            if (match) {
                const fundId = match[1];

                // Now visit with ?Fund=ID but WITHOUT ?Action=delete
                cy.visit(`/DonationFundEditor.php?Fund=${fundId}`);

                // Fund count must be unchanged — no deletion occurred
                cy.get("tbody tr").should("have.length", fundCount);
            }
        });
    });

    it("should still delete when both Action=delete and Fund param are present", () => {
        // Create a disposable fund first
        const disposableName = "Delete Safety Test " + Date.now();

        cy.visit("/DonationFundEditor.php");
        cy.get("#newFieldName").clear().type(disposableName);
        cy.contains("button", "Add New Fund").click();
        cy.contains(disposableName);

        // Extract its ID from the delete button onclick
        cy.contains("tbody tr", disposableName)
            .find(".dropdown-item.text-danger")
            .then(($btn) => {
                const onclick = $btn.attr("onclick") || "";
                const match = onclick.match(/confirmDeleteFund\([^,]+,\s*(\d+)\)/);
                if (match) {
                    const fundId = match[1];
                    // Visit with BOTH params — should delete
                    cy.visit(`/DonationFundEditor.php?Fund=${fundId}&Action=delete`);
                    cy.contains(disposableName).should("not.exist");
                }
            });
    });
});
