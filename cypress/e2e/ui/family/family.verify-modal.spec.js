/// <reference types="cypress" />

describe("Family Verification Modal (Admin/Staff View)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        // Navigate to a family view with verification modal
        cy.visit("v2/family/1");
    });

    it("should display verify option in Actions dropdown", () => {
        // Open the Actions dropdown
        cy.get("#family-actions-dropdown").click();
        // Verify Info should be in the dropdown
        cy.contains(".dropdown-item", "Verify Info").should("be.visible");
    });

    it("should open confirmation modal when verify clicked from Actions menu", () => {
        // Open Actions dropdown and click Verify Info
        cy.get("#family-actions-dropdown").click();
        cy.contains(".dropdown-item", "Verify Info").click();

        // Modal should be visible
        cy.get("#confirm-verify").should("be.visible");
        cy.get("#confirm-verify-label").contains("Request Family Info Verification").should("be.visible");
    });

    it("should display email list in modal body", () => {
        cy.get("#family-actions-dropdown").click();
        cy.contains(".dropdown-item", "Verify Info").click();
        cy.get("#confirm-verify").should("be.visible");

        // Should show instruction text about emailing
        cy.get(".modal-body").should("contain", "email");
    });

    it("should display URL button for verification", () => {
        cy.get("#family-actions-dropdown").click();
        cy.contains(".dropdown-item", "Verify Info").click();
        cy.get("#confirm-verify").should("be.visible");

        // URL button should always be present
        cy.get("#verifyURL").should("be.visible").should("contain", "URL");
    });

    it("should display all verification action buttons", () => {
        cy.get("#family-actions-dropdown").click();
        cy.contains(".dropdown-item", "Verify Info").click();
        cy.get("#confirm-verify").should("be.visible");

        // Check for verification action buttons
        cy.get("#verifyURL").should("be.visible");
        cy.get("#verifyDownloadPDF").should("be.visible").should("contain", "PDF");
        cy.get("#verifyNow").should("be.visible").should("contain", "Verified");
    });

    it("should display email PDF button when SMTP configured and emails present", () => {
        cy.get("#family-actions-dropdown").click();
        cy.contains(".dropdown-item", "Verify Info").click();
        cy.get("#confirm-verify").should("be.visible");

        // Email buttons should be visible for families with emails
        cy.get("#onlineVerify").should("be.visible").should("contain", "Online");
        cy.get("#verifyEmailPDF").should("be.visible").should("contain", "Email");
    });
});
