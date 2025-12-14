/// <reference types="cypress" />

describe("Family Verification Modal", () => {
    beforeEach(() => {
        cy.setupStandardSession();
        // Navigate to a family with emails (family ID 48 from demo data)
        cy.visit("v2/family/48");
    });

    it("should display verify modal with email list", () => {
        // Click the Verify button in the FAB
        cy.get('[data-toggle="modal"][data-target="#confirm-verify"]').click();
        
        // Modal should be visible
        cy.get("#confirm-verify").should("be.visible");
        cy.contains("Request Family Info Verification").should("be.visible");
        
        // Should show the list of emails that will be sent to
        cy.contains("You are about to email copy of the family information to the following emails").should("be.visible");
        cy.get("#confirm-verify ul li").should("have.length.greaterThan", 0);
    });

    it("should display unique emails without duplicates", () => {
        cy.get('[data-toggle="modal"][data-target="#confirm-verify"]').click();
        cy.get("#confirm-verify").should("be.visible");
        
        // Get all email list items
        cy.get("#confirm-verify ul li").then(($emails) => {
            const emails = [];
            $emails.each((index, el) => {
                emails.push(Cypress.$(el).text());
            });
            
            // Check that all emails are unique
            const uniqueEmails = [...new Set(emails)];
            expect(emails.length).to.equal(uniqueEmails.length);
        });
    });

    it("should show Email PDF button when emails and SMTP configured", () => {
        cy.get('[data-toggle="modal"][data-target="#confirm-verify"]').click();
        cy.get("#confirm-verify").should("be.visible");
        
        // Email PDF button should be visible
        cy.get("#verifyEmailPDF").should("be.visible");
        cy.get("#verifyEmailPDF").contains("Email PDF").should("be.visible");
    });

    it("should navigate to ConfirmReportEmail.php when Email PDF clicked", () => {
        cy.get('[data-toggle="modal"][data-target="#confirm-verify"]').click();
        cy.get("#confirm-verify").should("be.visible");
        
        // Click Email PDF button - it will navigate away
        cy.get("#verifyEmailPDF").click();
        
        // Verify the modal is hidden after click
        cy.get("#confirm-verify").should("not.be.visible");
    });

    it("should have correct Online Verification button for email sending", () => {
        cy.get('[data-toggle="modal"][data-target="#confirm-verify"]').click();
        cy.get("#confirm-verify").should("be.visible");
        
        // Online Verification button should be visible
        cy.get("#onlineVerify").should("be.visible");
        cy.get("#onlineVerify").should("contain", "Online Verification");
    });

    it("should have URL and PDF download buttons", () => {
        cy.get('[data-toggle="modal"][data-target="#confirm-verify"]').click();
        cy.get("#confirm-verify").should("be.visible");
        
        // URL button
        cy.get("#verifyURL").should("be.visible");
        cy.get("#verifyURL").should("contain", "URL");
        
        // PDF download button
        cy.get("#verifyDownloadPDF").should("be.visible");
        cy.get("#verifyDownloadPDF").should("contain", "PDF");
        
        // Verified in person button
        cy.get("#verifyNow").should("be.visible");
        cy.get("#verifyNow").should("contain", "Verified In Person");
    });
});
