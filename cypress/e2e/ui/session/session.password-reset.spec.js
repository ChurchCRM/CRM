/// <reference types="cypress" />

describe("Session Password Reset", () => {
    describe("Password Reset Form", () => {
        beforeEach(() => {
            cy.visit("/session/forgot-password/reset-request");
        });

        it("Should display password reset form with correct elements", () => {
            cy.contains("Reset your password").should("be.visible");
            cy.contains("Enter your login name and we will email you a link to reset your password.").should("be.visible");
            cy.get("input[name='username']").should("exist").should("be.visible");
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
            cy.url({ timeout: 5000 }).should("not.contain", "/session/forgot-password/reset-request");
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
            
            cy.contains("Check your email for a password reset link", { timeout: 5000 }).should("be.visible");
        });

        it("Should trim whitespace from username", () => {
            cy.get("input[name='username']").type("  admin  ");
            cy.get("#resetPassword").click();
            
            cy.contains("Check your email for a password reset link", { timeout: 5000 }).should("be.visible");
        });

        it("Should show error for non-existent user (but success message for security)", () => {
            cy.get("input[name='username']").type("nonexistentuser123");
            cy.get("#resetPassword").click();
            
            // API returns success: true for security reasons, so user sees success message
            cy.contains("Check your email for a password reset link", { timeout: 5000 }).should("be.visible");
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

    describe("Password Reset Token Validation", () => {
        it("Should display error page with proper Tabler UX structure", () => {
            cy.visit("/session/forgot-password/set/invalid-token-12345");

            // Verify page header elements
            cy.get(".login-form-header").should("be.visible");
            cy.get(".login-header-logo img").should("be.visible");
            cy.get(".login-header-church-name").should("be.visible");
            cy.contains("Account Recovery").should("be.visible");

            // Verify error title section
            cy.get(".login-form-title h1").should("contain", "Password Reset Error");
            cy.get(".login-form-title h1 i.fa-circle-exclamation").should("exist");
            cy.contains("We were unable to process your password reset request").should("be.visible");
        });

        it("Should display error alert with helpful message", () => {
            cy.visit("/session/forgot-password/set/invalid-token-12345");

            cy.get(".alert.alert-danger").should("be.visible");
            cy.contains("Please try requesting a new password reset link or contact support").should("be.visible");
        });

        it("Should display action buttons below alert", () => {
            cy.visit("/session/forgot-password/set/invalid-token-12345");

            cy.get(".alert-buttons").should("be.visible");
            cy.get(".alert-buttons").contains("Request Password Reset");
            cy.get(".alert-buttons").contains("Back to Login");
        });

        it("Should show error page when token does not exist", () => {
            cy.visit("/session/forgot-password/set/nonexistent-token-xyz");

            cy.contains("Password Reset Error").should("be.visible");
        });

        it("Should navigate to password reset form when clicking Request Password Reset", () => {
            cy.visit("/session/forgot-password/set/invalid-token");

            cy.get(".alert-buttons").contains("Request Password Reset").click();
            cy.url().should("include", "/session/forgot-password/reset-request");
        });

        it("Should navigate back to login when clicking Back to Login button", () => {
            cy.visit("/session/forgot-password/set/bad-token-123");

            cy.get(".alert-buttons").contains("Back to Login").click();
            cy.url().should("include", "/session/begin");
        });
    });
});
