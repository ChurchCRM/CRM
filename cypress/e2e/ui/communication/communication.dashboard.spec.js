/// <reference types="cypress" />

describe("Communication Menu and Dashboards", () => {
    beforeEach(() => cy.setupAdminSession());

    it("Communication menu is visible in sidebar", () => {
        cy.visit("v2/dashboard");
        cy.get("#sidebar-menu").contains("Communication").should("be.visible");
    });

    it("Communication menu has Email and Text sub-items", () => {
        cy.visit("v2/dashboard");
        // Expand the Communication menu
        cy.get("#sidebar-menu").contains("Communication").click();
        cy.get("#sidebar-menu").contains("Email").should("be.visible");
        cy.get("#sidebar-menu").contains("Text").should("be.visible");
    });

    describe("Email Dashboard", () => {
        it("loads email dashboard", () => {
            cy.visit("v2/email/dashboard");
            cy.contains("Email Dashboard");
        });

        it("shows integration status cards", () => {
            cy.visit("v2/email/dashboard");
            cy.contains("Email Integrations");
            // SMTP status card
            cy.contains("SMTP");
            // Mailchimp status card
            cy.contains("Mailchimp");
        });

        it("shows email tools", () => {
            cy.visit("v2/email/dashboard");
            cy.contains("Email Tools");
            cy.contains("Duplicates");
            cy.contains("People Without Emails");
        });

        it("admin can open settings panel", () => {
            cy.visit("v2/email/dashboard");
            cy.contains("Email Settings").click();
            // Settings panel should appear with SMTP fields
            cy.get("#emailSettings").should("be.visible");
            cy.get("#emailSettings").within(() => {
                cy.contains("Enable Email");
                cy.contains("SMTP Host");
            });
        });

        it("settings panel has Do Not Email property dropdown", () => {
            cy.visit("v2/email/dashboard");
            cy.contains("Email Settings").click();
            cy.get("#emailSettings", { timeout: 10000 }).should("be.visible");
            // The ajax-type dropdown for Do Not Email property
            cy.get("#emailSettings").contains("Do Not Email Property");
        });
    });

    describe("Text Dashboard", () => {
        it("loads text dashboard", () => {
            cy.visit("v2/text/dashboard");
            cy.contains("Text Dashboard");
        });

        it("shows Vonage integration status", () => {
            cy.visit("v2/text/dashboard");
            cy.contains("SMS Integration");
            // Either configured or not configured status
            cy.get(".alert").should("exist");
        });

        it("shows text tools info", () => {
            cy.visit("v2/text/dashboard");
            cy.contains("Text Tools");
        });

        it("admin can open settings panel", () => {
            cy.visit("v2/text/dashboard");
            cy.contains("Text Settings").click();
            cy.get("#textSettings").should("be.visible");
            cy.get("#textSettings").contains("Do Not SMS Property");
        });
    });
});

describe("Group Contact Dropdowns", () => {
    beforeEach(() => cy.setupAdminSession());

    it("Group view has Email and Text dropdowns", () => {
        cy.visit("groups/view/9");
        cy.get("#group-view-toolbar").within(() => {
            cy.contains("Email").should("exist");
            cy.contains("Text").should("exist");
        });
    });

    it("Email dropdown populates on click", () => {
        cy.visit("groups/view/9");
        cy.get("#emailDropdownBtn").click();
        // Should show loading then populate
        cy.get("#emailDropdownMenu", { timeout: 10000 }).within(() => {
            cy.contains("Copy All Emails").should("exist");
            cy.contains("Email All").should("exist");
            cy.contains("BCC All").should("exist");
        });
    });

    it("Text dropdown populates on click", () => {
        cy.visit("groups/view/9");
        cy.get("#textDropdownBtn").click();
        cy.get("#textDropdownMenu", { timeout: 10000 }).within(() => {
            // Either shows phone options or "No phone numbers"
            cy.get(".dropdown-item").should("have.length.at.least", 1);
        });
    });
});

describe("Photo Gallery Action Icons", () => {
    beforeEach(() => cy.setupStandardSession());

    it("shows 3 action icons per card", () => {
        cy.visit("people/photos?photosOnly=0");
        cy.get("#photo-grid .card", { timeout: 10000 }).first().within(() => {
            // All 3 icons should be present (some may be disabled)
            cy.get(".ti-phone").should("exist");
            cy.get(".ti-message").should("exist");
            cy.get(".ti-mail").should("exist");
        });
    });

    it("shows Not Classified for unclassified people", () => {
        cy.visit("people/photos?photosOnly=0");
        // At least one person should show "Not Classified"
        cy.contains("Not Classified").should("exist");
    });
});
