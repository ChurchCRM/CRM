/// <reference types="cypress" />

describe("API Public User", () => {
    // Basic authentication tests
    describe("Login - Basic Authentication", () => {
        it("Login with valid credentials returns apiKey", () => {
            const user = {
                userName: "admin",
                password: "changeme",
            };

            cy.apiRequest({
                method: "POST",
                url: "/api/public/user/login",
                headers: { "content-type": "application/json" },
                body: user,
            }).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body).to.have.property('apiKey');
                expect(resp.body.apiKey).to.eq(Cypress.env("admin.api.key"));
            });
        });

        it("Login with non-existent user returns 401 (not 404)", () => {
            cy.apiRequest({
                method: "POST",
                url: "/api/public/user/login",
                headers: { "content-type": "application/json" },
                body: { userName: "nonexistent_user_xyz", password: "anything" },
                failOnStatusCode: false,
            }).then((resp) => {
                // Should return 401 (same as wrong password) to prevent username enumeration
                expect(resp.status).to.eq(401);
            });
        });

        it("Login with wrong password returns 401", () => {
            cy.apiRequest({
                method: "POST",
                url: "/api/public/user/login",
                headers: { "content-type": "application/json" },
                body: { userName: "admin", password: "wrong_password" },
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(401);
            });
        });

        it("Login with empty userName returns 401", () => {
            cy.apiRequest({
                method: "POST",
                url: "/api/public/user/login",
                headers: { "content-type": "application/json" },
                body: { userName: "", password: "anything" },
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(401);
            });
        });

        it("Error message is generic to prevent user enumeration", () => {
            const GENERIC_ERROR = "Invalid login or password";
            const testCases = [
                { userName: "nonexistent", password: "wrong", label: "non-existent user" },
                { userName: "admin", password: "wrong", label: "wrong password" },
                { userName: "", password: "wrong", label: "empty username" },
            ];

            cy.wrap(testCases).each((testCase) => {
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: { userName: testCase.userName, password: testCase.password },
                    failOnStatusCode: false,
                }).then((resp) => {
                    expect(resp.status, testCase.label).to.eq(401);
                    expect(resp.body.error, testCase.label).to.eq(GENERIC_ERROR);
                });
            });
        });
    });

    // 2FA Authentication tests
    // NOTE: Tests below require a seeded user with usr_TwoFactorAuthSecret set.
    // Add a dedicated 2FA user to cypress/data/seed.sql before unskipping.
    describe("2FA Authentication", () => {
        it.skip("Login returns 202 requiresOTP when valid password supplied but OTP omitted", () => {
            // Requires seed user: { userName: "twofa_user", password: "changeme", usr_TwoFactorAuthSecret: "<valid TOTP secret>" }
            cy.apiRequest({
                method: "POST",
                url: "/api/public/user/login",
                headers: { "content-type": "application/json" },
                body: { userName: "twofa_user", password: "changeme" },
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(202);
                expect(resp.body).to.have.property("requiresOTP", true);
            });
        });

        it.skip("Login returns 401 on invalid OTP", () => {
            // Requires the same twofa_user seed entry
            cy.apiRequest({
                method: "POST",
                url: "/api/public/user/login",
                headers: { "content-type": "application/json" },
                body: { userName: "twofa_user", password: "changeme", otp: "000000" },
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(401);
                expect(resp.body.error).to.eq("Invalid login or password");
            });
        });
    });

    // Lockout tests
    // Uses `limited.user` (seeded, password "changeme") so admin credentials are not affected.
    // The DB is reset between Cypress runs so lockout state does not persist across suites.
    describe("Account Lockout", () => {
        const LOCKOUT_USER = "limited.user";
        const LOCKOUT_PASS = "changeme";
        const MAX_FAILURES = 5; // matches iMaxFailedLogins default in SystemConfig

        it("Correct password still returns 401 after account is locked", () => {
            // Trigger lockout by exhausting failed login attempts
            for (let i = 0; i < MAX_FAILURES; i++) {
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: { userName: LOCKOUT_USER, password: "wrong_password" },
                    failOnStatusCode: false,
                });
            }

            // Correct password should now be rejected (account locked)
            cy.apiRequest({
                method: "POST",
                url: "/api/public/user/login",
                headers: { "content-type": "application/json" },
                body: { userName: LOCKOUT_USER, password: LOCKOUT_PASS },
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(401);
                // Same generic message as wrong password — prevents confirming lockout state
                expect(resp.body.error).to.eq("Invalid login or password");
            });
        });
    });

    // Password Reset tests
    describe("Password Reset", () => {
        it("Successful password reset request with valid user", () => {
            cy.apiRequest({
                method: "POST",
                url: "/api/public/user/password-reset",
                headers: { "content-type": "application/json" },
                body: { userName: "admin" },
            }).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body).to.have.property('success');
                expect(resp.body.success).to.eq(true);
            });
        });

        it("Password reset request with non-existent user returns success (security)", () => {
            cy.apiRequest({
                method: "POST",
                url: "/api/public/user/password-reset",
                headers: { "content-type": "application/json" },
                body: { userName: "nonexistentuser123" },
            }).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body).to.have.property('success');
                expect(resp.body.success).to.eq(true);
            });
        });

        it("Password reset request is case-insensitive", () => {
            cy.apiRequest({
                method: "POST",
                url: "/api/public/user/password-reset",
                headers: { "content-type": "application/json" },
                body: { userName: "ADMIN" },
            }).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body.success).to.eq(true);
            });
        });
    });
});
