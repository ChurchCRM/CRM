/// <reference types="cypress" />

describe("Admin Debug", () => {
    let installSource;

    before(() => {
        cy.getSystemConfig("sInstallSource").then((value) => { installSource = value; });
    });

    after(() => {
        cy.restoreSystemConfig("sInstallSource", installSource);
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("View system debug", () => {
        cy.visit("/admin/system/debug");

        // Environment card subsumed the old "ChurchCRM Installation" card;
        // its tabs include Database, PHP, Web Server, Locale.
        cy.contains("Environment");
        cy.contains("Database");

        // Status banner is always rendered; it either shows "All checks
        // passing" or "Issues detected" depending on fixture state.
        cy.contains(/All checks passing|Issues detected/);

        // Timezone card keeps the stable selectors the JS relies on.
        cy.get("#headingTimezone").should("exist").invoke("text").should("not.be.empty");
        cy.get("#browser-timezone").should("exist").invoke("text").should("not.be.empty");
        cy.get("#browser-time").should("exist").invoke("text").should("not.be.empty");
        cy.get("#timezone-summary").should("exist").invoke("text").should("not.be.empty");
    });

    it("lets an administrator identify and copy the installer for support", () => {
        cy.setSystemConfig("sInstallSource", "custom-panel");
        cy.setupAdminSession();
        cy.visit("/admin/system/debug#collapseEnvironment");
        cy.contains("#env-app tr", "Install Source").within(() => {
            cy.get("code").should("have.text", "custom-panel");
            cy.get("button[data-copy]").should("have.attr", "data-copy", "custom-panel");
        });
        cy.setSystemConfig("sInstallSource", "");
        cy.setupAdminSession();
        cy.visit("/admin/system/debug#collapseEnvironment");
        cy.contains("#env-app tr", "Install Source").find("code").should("have.text", "Unknown");
    });

    it("View email debug", () => {
        cy.visit("admin/email/debug");

        // Page title was "Debug Email Connection"; it's now "Email Debug"
        // and the page always renders one of three status cards (config
        // error / success / failure). Assert on structural elements
        // present in every state.
        cy.contains("Email Debug");
        cy.contains("SMTP Configuration");
    });

    it("Gen DirectoryReports", () => {
        cy.visit("DirectoryReports.php");
        cy.contains("Directory reports");
        cy.contains("Select classifications to include");
    });
});
