/// <reference types="cypress" />

describe("Admin Get Started", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should display the Get Started landing page", () => {
        cy.visit("admin/get-started");
        cy.contains("Get Your Data Into ChurchCRM");
        cy.contains("Explore with Demo Data");
        cy.contains("Import from a Spreadsheet");
        cy.contains("Enter Data Manually");
        cy.contains("Restore a Backup");
    });

    it("should expose existing-data flags on the demo import card so the UI can warn before calling the API", () => {
        // The standard test DB already has demo data loaded, so the
        // get-started route should render data-has-existing-data="true"
        // on #importDemoDataV2 along with the current person/family counts.
        // Those drive the client-side warning in importDemoData.js — we
        // verify the server rendered the attributes here so future regressions
        // (e.g. dropping the counts in dashboard.php) are caught.
        cy.visit("admin/get-started");
        cy.get("#importDemoDataV2")
            .should("have.attr", "data-has-existing-data", "true")
            .and("have.attr", "data-person-list-url")
            .and("match", /\/people\/list$/);
        cy.get("#importDemoDataV2").should("have.attr", "data-family-list-url").and("match", /\/family\/?$/);
        cy.get("#importDemoDataV2").invoke("attr", "data-person-count").then((val) => {
            expect(Number(val)).to.be.greaterThan(1);
        });
    });

    it("should show an upfront warning (no API call) when the database already has data", () => {
        cy.visit("admin/get-started");

        // Intercept the demo load endpoint so we can assert it is NOT hit
        // by the initial click — the warning should appear first and only
        // an explicit "Import demo data anyway" click should trigger POST.
        cy.intercept("POST", "**/api/demo/load").as("demoLoad");

        cy.get("#importDemoDataV2").click();
        cy.get("#demoImportConfirmOverlay").should("be.visible");

        // The warning block and the amber "Import demo data anyway" button
        // should both be present because the DB has data.
        cy.get("#demoImportWarning").should("be.visible");
        cy.contains("#demoImportWarning", "Your database already contains data");
        cy.get("#demoImportConfirmBtn")
            .should("have.class", "btn-warning")
            .and("contain", "Import demo data anyway");

        // The people/families counts in the warning should be rendered as
        // clickable links to the list pages.
        cy.get("#demoImportWarning a[href*='/people/list']")
            .should("have.attr", "target", "_blank")
            .and("have.attr", "rel")
            .and("match", /noopener/);
        cy.get("#demoImportWarning a[href*='/family/']")
            .should("have.attr", "target", "_blank");

        // And no API call has fired yet — just opening the overlay must
        // not trigger /api/demo/load.
        cy.get("@demoLoad.all").should("have.length", 0);

        cy.get("#demoImportCancelBtn").click();
        cy.get("#demoImportConfirmOverlay").should("not.be.visible");
    });

    it("should show skip link back to Admin Dashboard", () => {
        cy.visit("admin/get-started");
        cy.contains("a", "Skip — go to Admin Dashboard").should("have.attr", "href").and("include", "admin/");
    });

    it("Enter Data Manually card links to the manual data entry guide", () => {
        cy.visit("admin/get-started");
        cy.contains("a.gs-card", "Enter Data Manually").should("have.attr", "href").and("include", "admin/get-started/manual");
    });

    it("Import from a Spreadsheet card links to /admin/import/csv", () => {
        cy.visit("admin/get-started");
        cy.contains("a.gs-card", "Import from a Spreadsheet").should("have.attr", "href").and("include", "/admin/import/csv");
    });

    it("should display the manual data entry guide page", () => {
        cy.visit("admin/get-started/manual");
        cy.contains("Start Fresh");
        cy.contains("Recommended Order");
        cy.contains("Add Your First Family");
        cy.contains("Add People to the Family");
        cy.contains("Quick Tips");
    });

    it("should show the Add First Family button linking to FamilyEditor", () => {
        cy.visit("admin/get-started/manual");
        cy.contains("a", "Add First Family").first().should("have.attr", "href").and("include", "FamilyEditor.php");
    });

    it("should show the Add a Person button linking to PersonEditor", () => {
        cy.visit("admin/get-started/manual");
        cy.contains("a", "Add a Person").first().should("have.attr", "href").and("include", "PersonEditor.php");
    });

    it("should show Back to Get Started link on manual page", () => {
        cy.visit("admin/get-started/manual");
        cy.contains("a", "Back to Get Started").should("have.attr", "href").and("include", "admin/get-started");
    });

    it("should display quick tips on the manual page", () => {
        cy.visit("admin/get-started/manual");
        cy.contains("Families share an address and phone number.");
        cy.contains("Each person can have their own email and mobile number.");
        cy.contains("You can always import more data later via CSV.");
    });
});
