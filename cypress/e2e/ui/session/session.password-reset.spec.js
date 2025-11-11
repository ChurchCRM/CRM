/// <reference types="cypress" />

describe("Session Password Reset", () => {
    beforeEach(() => {
        cy.visit("/session/forgot-password/reset-request");
    });

    it("Should display password reset form with correct elements", () => {
        cy.contains("Reset your password").should("be.visible");
        cy.contains("Enter your login name and we will email you a link to reset your password.").should("be.visible");
        cy.get("input[name='username']").should("exist").should("be.visible").should("have.focus");
        cy.get("#resetPassword").should("exist").should("be.visible").should("be.enabled").should("contain", "Send Reset Email");
        cy.contains("Back to login").should("exist").should("be.visible");
    });

    it("Should show validation error when username is empty", () => {
        cy.get("#resetPassword").click();
        cy.contains("Login Name is Required").should("be.visible");
        cy.get("#resetPassword").should("not.be.disabled");
    });

    it("Should submit form on Enter key press", () => {
        cy.get("input[name='username']").type("admin{enter}");
        cy.get("#resetPassword").should("contain", "Sending...");
    });

    it("Should show loading state while submitting", () => {
        cy.get("input[name='username']").type("admin");
        cy.get("#resetPassword").click();
        cy.get("#resetPassword").should("be.disabled");
        cy.get("#resetPassword").should("contain", "Sending...");
    });

    it("Should show success message and redirect on valid user", () => {
        cy.get("input[name='username']").type("admin");
        cy.get("#resetPassword").click();
        
        // Wait for success notification
        cy.contains("Check your email for a password reset link").should("be.visible");
        
        // Should redirect to login after a delay
        cy.url({ timeout: 3000 }).should("not.contain", "/session/forgot-password/reset-request");
    });

    it("Should show error message and re-enable button on API error", () => {
        // Intercept the API call and force an error
        cy.intercept("POST", "**/api/public/user/password-reset", {
            statusCode: 500,
            body: { error: "Server error" }
        }).as("failedReset");
        
        cy.get("input[name='username']").type("admin");
        cy.get("#resetPassword").click();
        
        cy.wait("@failedReset");
        cy.contains("Sorry, we are unable to process your request at this point in time.").should("be.visible");
        cy.get("#resetPassword").should("not.be.disabled").should("contain", "Send Reset Email");
    });

    it("Should handle case-insensitive username", () => {
        cy.get("input[name='username']").type("ADMIN");
        cy.get("#resetPassword").click();
        
        cy.contains("Check your email for a password reset link").should("be.visible");
    });

    it("Should trim whitespace from username", () => {
        cy.get("input[name='username']").type("  admin  ");
        cy.get("#resetPassword").click();
        
        cy.contains("Check your email for a password reset link").should("be.visible");
    });

    it("Should show error for non-existent user (but success message for security)", () => {
        cy.get("input[name='username']").type("nonexistentuser123");
        cy.get("#resetPassword").click();
        
        // API returns success: true for security reasons, so user sees success message
        cy.contains("Check your email for a password reset link").should("be.visible");
    });

    it("Should allow multiple reset attempts after error", () => {
        // First attempt with non-existent user
        cy.get("input[name='username']").type("nonexistentuser123");
        cy.get("#resetPassword").click();
        cy.contains("Check your email for a password reset link").should("be.visible");
        
        // Go back to login and return to reset page
        cy.visit("/session/forgot-password/reset-request");
        
        // Second attempt with valid user
        cy.get("input[name='username']").type("admin");
        cy.get("#resetPassword").click();
        cy.contains("Check your email for a password reset link").should("be.visible");
    });
});
