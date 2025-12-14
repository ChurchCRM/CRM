/// <reference types="cypress" />

describe("Family Verification Modal (Admin/Staff View)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        // Navigate to a family view with verification modal
        cy.visit("v2/family/1");
    });

    it("should display verify button in actions", () => {
        // The Verify button should exist in the Actions section
        cy.get('.card-title').contains('Actions').should('be.visible');
        cy.get('a[data-toggle="modal"][data-target="#confirm-verify"]').should('be.visible');
        cy.contains('button, a', 'Verify').should('be.visible');
    });

    it("should open confirmation modal when verify button clicked", () => {
        // Click the Verify button
        cy.get('a[data-toggle="modal"][data-target="#confirm-verify"]').click();
        
        // Modal should be visible
        cy.get("#confirm-verify").should("be.visible");
        cy.get("#confirm-verify-label").contains("Request Family Info Verification").should("be.visible");
    });

    it("should display email list in modal body", () => {
        cy.get('a[data-toggle="modal"][data-target="#confirm-verify"]').click();
        cy.get("#confirm-verify").should("be.visible");
        
        // Should show instruction text about emailing
        cy.get(".modal-body").should("contain", "email");
    });

    it("should display URL button for verification", () => {
        cy.get('a[data-toggle="modal"][data-target="#confirm-verify"]').click();
        cy.get("#confirm-verify").should("be.visible");
        
        // URL button should always be present
        cy.get("#verifyURL").should("be.visible").should("contain", "URL");
    });

    it("should display all verification action buttons", () => {
        cy.get('a[data-toggle="modal"][data-target="#confirm-verify"]').click();
        cy.get("#confirm-verify").should("be.visible");
        
        // Check for verification action buttons
        cy.get("#verifyURL").should("be.visible");
        cy.get("#verifyDownloadPDF").should("be.visible").should("contain", "PDF");
        cy.get("#verifyNow").should("be.visible").should("contain", "Verified");
    });

    it("should display email PDF button when SMTP configured and emails present", () => {
        cy.get('a[data-toggle="modal"][data-target="#confirm-verify"]').click();
        cy.get("#confirm-verify").should("be.visible");
        
        // Email buttons should be visible for families with emails
        cy.get("#onlineVerify").should("be.visible").should("contain", "Online");
        cy.get("#verifyEmailPDF").should("be.visible").should("contain", "Email");
    });
});
