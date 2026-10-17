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

    describe("Email page", () => {
        it("loads the email tools", () => {
            cy.visit("people/email");
            cy.contains("h2", "Email");
            cy.contains("Email Tools");
            cy.contains("Duplicates");
            cy.contains("People Without Emails");
        });

        it("links an administrator to Email Settings", () => {
            cy.visit("people/email");
            cy.contains("a", "Email Settings").should("have.attr", "href").and("include", "/admin/email");
        });
    });

    describe("Email Settings", () => {
        it("shows integration status cards", () => {
            cy.visit("admin/email");
            cy.contains("Email Integrations");
            cy.contains("SMTP");
            cy.contains("Mailchimp");
        });

        it("shows the settings form", () => {
            cy.visit("admin/email");
            cy.get("#email-settings-form").within(() => {
                cy.contains("Enable Email");
                cy.contains("SMTP Host");
            });
        });

        it("has a Do Not Email property dropdown", () => {
            cy.visit("admin/email");
            cy.get("#iDoNotEmailPropertyId").should("exist");
            cy.contains("label", "Do Not Email Property");
        });
    });

    describe("Text page", () => {
        it("loads the text page", () => {
            cy.visit("people/text");
            cy.contains("h2", "Text");
        });

        it("shows Vonage integration status", () => {
            cy.visit("people/text");
            cy.contains("SMS Integration");
            cy.get(".alert").should("exist");
        });

        it("shows text tools info", () => {
            cy.visit("people/text");
            cy.contains("Text Tools");
        });

        it("admin can open settings panel", () => {
            cy.visit("people/text");
            cy.contains("Text Settings").click();
            cy.get("#textSettings").should("be.visible");
            cy.get("#textSettings").contains("Do Not SMS Property");
        });
    });
});

describe("Group Contact Dropdowns", () => {
    beforeEach(() => cy.setupAdminSession());

    it("Group view has Email and Text buttons", () => {
        cy.visit("/groups/view/9");
        cy.get("#group-view-toolbar").within(() => {
            // Email is now a composer button (no dropdown)
            cy.get("[data-email-composer]").contains("Email").should("exist");
            // Text is still a dropdown
            cy.contains("Text").should("exist");
        });
    });

    it("Email composer button opens modal with Copy and Client actions", () => {
        cy.visit("/groups/view/9");
        cy.intercept("GET", "**/api/groups/9/emails").as("groupEmails");
        // Click the email composer button (replaces old dropdown)
        cy.get("[data-email-composer]").click();
        cy.wait("@groupEmails");
        // Modal should appear with Copy Addresses and Open in Email Client buttons
        cy.get("#crm-email-composer-modal").should("be.visible");
        cy.get("#crm-email-copy-btn").should("exist");
        cy.get("#crm-email-client-btn").should("exist");
    });

    it("Text dropdown populates on click", () => {
        cy.visit("/groups/view/9");
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
            cy.get(".fa-phone").should("exist");
            cy.get(".fa-envelope").should("exist");
            cy.get(".fa-envelope").should("exist");
        });
    });

    it("shows Not Classified for unclassified people", () => {
        cy.visit("people/photos?photosOnly=0");
        // At least one person should show "Not Classified"
        cy.contains("Not Classified").should("exist");
    });
});
