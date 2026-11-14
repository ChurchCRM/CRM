/// <reference types="cypress" />

describe("Communication Menu and Dashboards", () => {
    beforeEach(() => cy.setupAdminSession());

    it("Communication menu opens to Email and Text", () => {
        cy.visit("v2/dashboard");
        cy.get("#sidebar-menu").contains("Communication").click();
        cy.get("#sidebar-menu").contains("Email").should("be.visible");
        cy.get("#sidebar-menu").contains("Text").should("be.visible");
    });

    it("Email page shows the email tools and links an administrator to Email Settings", () => {
        cy.visit("people/email");
        cy.contains("h2", "Email");
        cy.contains("Email Tools");
        cy.contains("Duplicates");
        cy.contains("People Without Emails");
        cy.contains("a", "Email Settings").should("have.attr", "href").and("include", "/admin/email");
    });

    it("Email Settings shows the integrations and the settings form", () => {
        cy.visit("admin/email");
        cy.contains("Email Integrations");
        cy.contains("SMTP");
        cy.contains("Mailchimp");
        cy.get("#email-settings-form").within(() => {
            cy.contains("Enable Email");
            cy.contains("SMTP Host");
        });
        cy.get("#iDoNotEmailPropertyId").should("exist");
        cy.contains("label", "Do Not Email Property");
    });

    it("Text page shows the SMS status and opens the settings panel for an admin", () => {
        cy.visit("people/text");
        cy.contains("h2", "Text");
        cy.contains("SMS Integration");
        cy.get(".alert:not(.crm-system-notification)").should("exist");
        cy.contains("Text Tools");
        cy.contains("Text Settings").click();
        cy.get("#textSettings").should("be.visible");
        cy.get("#textSettings").contains("Do Not SMS Property");
    });
});

describe("Group Contact Dropdowns", () => {
    beforeEach(() => cy.setupAdminSession());

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

    it("shows the contact icons on each card and Not Classified for unclassified people", () => {
        cy.visit("people/photos?photosOnly=0");
        cy.get("#photo-grid .card", { timeout: 10000 }).first().within(() => {
            // Icons stay on the card even when disabled
            cy.get(".fa-phone").should("exist");
            cy.get(".fa-envelope").should("exist");
        });
        cy.contains("Not Classified").should("exist");
    });
});

describe("Text page auto-save", () => {
    beforeEach(() => cy.setupAdminSession());

    let originalProperty;

    before(() => {
        cy.getSystemConfig("iDoNotSmsPropertyId").then((value) => { originalProperty = value; });
    });

    after(() => cy.restoreSystemConfig("iDoNotSmsPropertyId", originalProperty));

    it("an administrator auto-saves the Do Not SMS property", () => {
        cy.intercept("POST", "**/admin/api/system/config/iDoNotSmsPropertyId").as("saveProperty");
        cy.visit("/people/text");
        cy.contains("Text Settings").click();
        cy.get("#textSettings").should("be.visible");
        cy.get("#textSettings").contains("Do Not SMS Property");
        cy.get("#textSettings .settings-panel-save").should("not.exist");
        cy.get("#textSettings .settings-panel-fields").should("not.be.disabled");
        cy.get("#textSettings select[name='iDoNotSmsPropertyId'] option").then(($options) => {
            const next = [...$options].find((option) => option.value !== originalProperty && option.value !== "").value;
            cy.get("#textSettings select[name='iDoNotSmsPropertyId']").select(next);
            cy.wait("@saveProperty").then(({ request, response }) => {
                expect(request.body.value).to.eq(next);
                expect(response.statusCode).to.eq(200);
            });
            cy.request("/admin/api/system/config/iDoNotSmsPropertyId").its("body.value").should("eq", next);
            cy.reload();
            cy.contains("Text Settings").click();
            cy.get("#textSettings .settings-panel-fields").should("not.be.disabled");
            cy.get("#textSettings select[name='iDoNotSmsPropertyId']").should("have.value", next);
        });
    });
});
