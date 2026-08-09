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
    // Uses the seeded `twofa_user` (password "changeme", usr_TwoFactorAuthSecret
    // is a Defuse-encrypted TOTP secret that decrypts to JBSWY3DPEBLW64TMMQ======).
    describe("2FA Authentication", () => {
        it("Login returns 202 requiresOTP when valid password supplied but OTP omitted", () => {
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

        it("Login returns 401 on invalid OTP", () => {
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

        // Regression test for GHSA-f2fq-4rmp-9x8c: repeated wrong OTP codes
        // must count toward usr_FailedLogins and eventually lock the account.
        // Uses dedicated `twofa_lockout_user` (password "changeme", 2FA enrolled)
        // so the shared `twofa_user` fixture is not corrupted for other tests.
        describe("2FA OTP brute-force lockout (GHSA-f2fq-4rmp-9x8c)", () => {
            const LOCKOUT_USER = "twofa_lockout_user";
            const LOCKOUT_PASS = "changeme";
            const MAX_FAILURES = 5; // must match iMaxFailedLogins in SystemConfig

            before(() => {
                // Reset twofa_lockout_user's FailedLogins to 0 so re-runs against
                // the same DB don't silently pass via the top-level isLocked() gate
                // instead of exercising the OTP-failure counter code path.
                cy.makePrivateAdminAPICall(
                    "POST",
                    "/admin/api/user/903/login/reset",
                    null,
                    200,
                );
                // Guard: assert the server's iMaxFailedLogins matches MAX_FAILURES.
                // If this assertion fails, the loop below may trigger lockout at the
                // password gate before exhausting OTP failures, silently testing the
                // wrong code path.
                cy.makePrivateAdminAPICall(
                    "GET",
                    "/api/system/config/iMaxFailedLogins",
                    null,
                    200,
                ).then((resp) => {
                    expect(
                        Number(resp.body.value),
                        "iMaxFailedLogins must equal MAX_FAILURES so the OTP-lockout code path is exercised",
                    ).to.eq(MAX_FAILURES);
                });
            });

            it("Account is locked after iMaxFailedLogins wrong OTP submissions", () => {
                // Submit MAX_FAILURES wrong OTP codes — each should increment usr_FailedLogins
                for (let i = 0; i < MAX_FAILURES; i++) {
                    cy.apiRequest({
                        method: "POST",
                        url: "/api/public/user/login",
                        headers: { "content-type": "application/json" },
                        body: { userName: LOCKOUT_USER, password: LOCKOUT_PASS, otp: String(i).padStart(6, "0") },
                        failOnStatusCode: false,
                    }).then((resp) => {
                        expect(resp.status).to.eq(401);
                    });
                }

                // After MAX_FAILURES wrong OTPs the account should be locked.
                // A fresh login with the correct password (which would normally return 202
                // requiresOTP) must now return 401 because isLocked() fires first.
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: { userName: LOCKOUT_USER, password: LOCKOUT_PASS },
                    failOnStatusCode: false,
                }).then((resp) => {
                    expect(resp.status).to.eq(401);
                    expect(resp.body.error).to.eq("Invalid login or password");
                });
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

        it("Correct password returns 401 for a pre-locked seeded account", () => {
            // `locked.user` is seeded with usr_FailedLogins = 99 (well over
            // iMaxFailedLogins), so it is locked from the first request — no need
            // to exhaust attempts. Correct credentials must still return the
            // generic 401 so lockout state can't be probed.
            cy.apiRequest({
                method: "POST",
                url: "/api/public/user/login",
                headers: { "content-type": "application/json" },
                body: { userName: "locked.user", password: "changeme" },
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(401);
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
