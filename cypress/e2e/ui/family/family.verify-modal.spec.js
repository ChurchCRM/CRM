/// <reference types="cypress" />

describe("Family Verification Modal (Admin/Staff View)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        // Navigate to a family view with verification modal
        cy.visit("people/family/1");
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

        // Should explain that the email links to the member portal
        cy.get("#confirm-verify .modal-body").should("contain", "member portal");
    });

    it("should display all verification action buttons", () => {
        cy.get("#family-actions-dropdown").click();
        cy.contains(".dropdown-item", "Verify Info").click();
        cy.get("#confirm-verify").should("be.visible");

        // Check for verification action buttons
        cy.get("#verifyURL").should("not.exist");
        cy.get("#verifyDownloadPDF").should("be.visible").should("contain", "PDF");
        cy.get("#verifyNow").should("be.visible").should("contain", "Verified");
    });

    it("should offer one email button, with no URL or PDF email, when SMTP is configured and emails are present", () => {
        cy.get("#family-actions-dropdown").click();
        cy.contains(".dropdown-item", "Verify Info").click();
        cy.get("#confirm-verify").should("be.visible");

        // Email buttons should be visible for families with emails
        cy.get("#onlineVerify").should("be.visible").should("contain", "Portal Link");
        cy.get("#verifyEmailPDF").should("not.exist");
    });

    it("shows when the family was last verified, invited and signed in", () => {
        cy.get("#family-verification-card").should("contain", "Last verified").and("contain", "Last invited").and("contain", "Last portal sign-in");
    });
});
