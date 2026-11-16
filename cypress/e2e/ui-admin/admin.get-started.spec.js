/// <reference types="cypress" />

describe("Admin Get Started", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should show an upfront warning (no API call) when the database already has data", () => {
        cy.visit("admin/get-started");

        // Intercept the demo load endpoint so we can assert it is NOT hit
        // by the initial click — the warning should appear first and only
        // an explicit "Import demo data anyway" click should trigger POST.
        cy.intercept("POST", "**/api/demo/load").as("demoLoad");

        cy.get("#importDemoDataV2").click();
        cy.get("#demoImportModal").should("be.visible");

        // The warning block and the amber "Import demo data anyway" button
        // should both be present because the DB has data.
        cy.get("#importDemoDataV2").should("have.attr", "data-has-existing-data", "true");
        cy.get("#demoImportWarning").should("be.visible");
        cy.contains("#demoImportWarning", "Your database already contains data");
        cy.get("#demoImportConfirmBtn")
            .should("have.class", "btn-warning")
            .and("contain", "Import demo data anyway");

        cy.get("#demoImportWarning").should("contain", "people").and("contain", "families");

        // And no API call has fired yet — just opening the overlay must
        // not trigger /api/demo/load.
        cy.get("@demoLoad.all").should("have.length", 0);

        cy.get("#demoImportCancelBtn").should("be.focused").click();
        cy.get("#demoImportModal").should("not.be.visible");
    });

});
