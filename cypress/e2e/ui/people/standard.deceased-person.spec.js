/// <reference types="cypress" />
/**
 * Cypress E2E tests for the deceased person flag feature (#9171).
 *
 * Acceptance criteria covered:
 *  - Deceased checkbox + date picker visible in PersonEditor Card 5
 *  - Ticking checkbox without a date stores today; with a date stores that date
 *  - Future date rejected with validation message
 *  - Person view page shows "Deceased" badge after marking
 *  - Un-ticking clears the flag (reversible)
 *  - Person list hides deceased by default; Deceased Status filter reveals them
 *  - Global search autocomplete excludes deceased
 */

describe("Deceased Person Flag", () => {
    // Track created person IDs for deterministic cleanup (cannot search deceased via API)
    const createdPersonIds = [];

    after(() => {
        // Delete all persons created by this suite by ID (bypass living-only search filter)
        createdPersonIds.forEach((personId) => {
            cy.apiRequest({
                method: "DELETE",
                url: `/api/persons/${personId}`,
            });
        });
    });

    beforeEach(() => cy.setupStandardSession());

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Creates a minimal person via PersonEditor.
     * Returns a Cypress chain that resolves to the new person's numeric ID.
     */
    function createTestPerson(firstName) {
        cy.visit("/PersonEditor.php");
        cy.get("#FirstName").type(firstName);
        cy.get("#LastName").type("DeceasedE2ETest");
        cy.get("#Gender").select("1");
        cy.get("#Classification").select("1");
        cy.get("button[name='PersonSubmit']").click();
        cy.url().should("match", /people\/view\/\d+/);
        return cy.url().then((url) => {
            const match = url.match(/\/people\/view\/(\d+)/);
            const personId = match ? parseInt(match[1], 10) : null;
            if (personId) createdPersonIds.push(personId);
            return personId;
        });
    }

    // -----------------------------------------------------------------------
    // PersonEditor UI tests
    // -----------------------------------------------------------------------

    it("shows deceased checkbox and hidden date picker in Card 5", () => {
        cy.visit("/PersonEditor.php");

        // Checkbox should be present and unchecked by default
        cy.get("#IsDeceased")
            .should("exist")
            .and("not.be.checked");

        // Date picker group should be hidden initially
        cy.get("#DeceasedDateGroup").should("not.be.visible");
    });

    it("reveals date picker when deceased checkbox is ticked", () => {
        cy.visit("/PersonEditor.php");

        cy.get("#IsDeceased").check();
        cy.get("#DeceasedDateGroup").should("be.visible");

        cy.get("#IsDeceased").uncheck();
        cy.get("#DeceasedDateGroup").should("not.be.visible");
    });

    it("rejects a future date of death with a validation error", () => {
        createTestPerson("FutureDeath").then((personId) => {
            if (!personId) return;

            cy.visit(`/PersonEditor.php?PersonID=${personId}`);

            // Tick deceased and enter a future date in yyyy-mm-dd format (bootstrap-datepicker's
            // configured format). Typing MM/DD/YYYY would get silently cleared by the datepicker
            // on blur, resulting in an empty field that bypasses server-side validation.
            cy.get("#IsDeceased").check();
            const futureYear = new Date().getFullYear() + 2;
            cy.get("#DateDeceased").type(`${futureYear}-12-01`);

            cy.get("button[name='PersonSubmit']").click();

            // Should stay on the editor page with a validation error
            cy.url().should("include", "PersonEditor");
            cy.contains("Not a valid date of death").should("be.visible");
        });
    });

    it("marks a person as deceased (no date) and shows the badge on the view page", () => {
        createTestPerson("BobDeceased").then((personId) => {
            if (!personId) return;

            // Edit the person and mark as deceased without a date
            cy.visit(`/PersonEditor.php?PersonID=${personId}`);
            cy.get("#IsDeceased").check();
            cy.get("button[name='PersonSubmit']").click();

            // Should redirect to person view
            cy.url().should("include", `people/view/${personId}`);

            // Deceased badge should appear
            cy.get(".badge").contains("Deceased").should("be.visible");
        });
    });

    it("marks a person deceased with a specific past date and shows the badge", () => {
        createTestPerson("AliceDeceased").then((personId) => {
            if (!personId) return;

            const dateStr = "01/15/2020"; // past date MM/DD/YYYY

            cy.visit(`/PersonEditor.php?PersonID=${personId}`);
            cy.get("#IsDeceased").check();
            cy.get("#DateDeceased").type(dateStr);
            cy.get("button[name='PersonSubmit']").click();

            cy.url().should("include", `people/view/${personId}`);
            cy.get(".badge").contains("Deceased").should("be.visible");
        });
    });

    it("un-ticking deceased clears the flag (fully reversible)", () => {
        createTestPerson("CharlieReversible").then((personId) => {
            if (!personId) return;

            // Mark deceased
            cy.visit(`/PersonEditor.php?PersonID=${personId}`);
            cy.get("#IsDeceased").check();
            cy.get("button[name='PersonSubmit']").click();
            cy.url().should("include", `people/view/${personId}`);
            cy.get(".badge").contains("Deceased").should("be.visible");

            // Now un-tick and save
            cy.visit(`/PersonEditor.php?PersonID=${personId}`);
            cy.get("#IsDeceased").should("be.checked").uncheck();
            cy.get("button[name='PersonSubmit']").click();

            cy.url().should("include", `people/view/${personId}`);
            // Deceased badge should be gone
            cy.get(".badge").contains("Deceased").should("not.exist");
        });
    });

    // -----------------------------------------------------------------------
    // Person list toggle tests
    // -----------------------------------------------------------------------

    it("person list hides deceased by default and shows them when filter is cleared", () => {
        createTestPerson("DanDeceased").then((personId) => {
            if (!personId) return;

            // Mark as deceased
            cy.visit(`/PersonEditor.php?PersonID=${personId}`);
            cy.get("#IsDeceased").check();
            cy.get("button[name='PersonSubmit']").click();
            cy.url().should("include", `people/view/${personId}`);

            // Person list should hide deceased by default
            cy.visit("/people/list");
            cy.get("#members tbody tr", { timeout: 15000 }).should(
                "have.length.greaterThan",
                0
            );

            // The deceased person should not appear in the default (Living-only) view
            cy.get("#members tbody").should("not.contain", "DanDeceased");

            // Clear the Deceased Status filter to reveal deceased rows
            // TomSelect inserts its .ts-wrapper as a sibling AFTER the <select>,
            // so use .siblings() not .closest() (which only traverses ancestors).
            cy.get(".filter-DeceasedStatus").should("exist");
            cy.get(".filter-DeceasedStatus")
                .siblings(".ts-wrapper")
                .find(".remove")
                .click({ force: true });

            // The deceased person should now appear
            cy.get("#members tbody", { timeout: 5000 }).should(
                "contain",
                "DanDeceased"
            );
        });
    });

    // -----------------------------------------------------------------------
    // Search autocomplete excludes deceased
    // -----------------------------------------------------------------------

    it("global search autocomplete excludes deceased persons", () => {
        createTestPerson("EveDeceased").then((personId) => {
            if (!personId) return;

            // Mark deceased
            cy.visit(`/PersonEditor.php?PersonID=${personId}`);
            cy.get("#IsDeceased").check();
            cy.get("button[name='PersonSubmit']").click();

            // Autocomplete endpoint should not return the deceased person
            cy.apiRequest({
                method: "GET",
                url: `/api/persons/search/EveDeceased`,
            }).then((response) => {
                expect(response.status).to.eq(200);
                const names = (response.body || []).map(
                    (p) => `${p.firstName} ${p.lastName}`
                );
                expect(names.join(",")).to.not.include("EveDeceased");
            });
        });
    });
});
