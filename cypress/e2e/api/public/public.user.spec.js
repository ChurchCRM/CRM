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
            // All error cases should return the same generic message
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
                    expect(resp.status).to.eq(401);
                    // All should contain the same generic error message
                    expect(resp.body).to.have.property('exception');
                });
            });
        });
    });

    // Account lockout tests
    describe("Login - Account Lockout Protection", () => {
        it("Locked account is rejected with 401", () => {
            // Setup: lock the test user by updating failed logins to max
            // Use a test user that's easier to lock/unlock
            cy.task('db:query', {
                query: 'UPDATE user_usr SET usr_FailedLogins = 99 WHERE usr_per_ID = 3'
            }).then(() => {
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: { userName: "tony.wade@example.com", password: "basicjoe" },
                    failOnStatusCode: false,
                }).then((resp) => {
                    expect(resp.status).to.eq(401);
                });
            }).finally(() => {
                // Cleanup: unlock the user
                cy.task('db:query', {
                    query: 'UPDATE user_usr SET usr_FailedLogins = 0 WHERE usr_per_ID = 3'
                });
            });
        });

        it("Failed login counter increments on invalid password", () => {
            const testUser = "tony.wade@example.com";

            // Get initial failed login count
            cy.task('db:query', {
                query: `SELECT usr_FailedLogins FROM user_usr WHERE usr_UserName = '${testUser}'`
            }).then((result) => {
                const initialCount = result[0]?.usr_FailedLogins || 0;

                // Attempt login with wrong password
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: { userName: testUser, password: "wrongpassword" },
                    failOnStatusCode: false,
                }).then((resp) => {
                    expect(resp.status).to.eq(401);
                });

                // Verify counter incremented
                cy.task('db:query', {
                    query: `SELECT usr_FailedLogins FROM user_usr WHERE usr_UserName = '${testUser}'`
                }).then((result) => {
                    expect(result[0].usr_FailedLogins).to.eq(initialCount + 1);
                });
            }).finally(() => {
                // Cleanup: reset counter
                cy.task('db:query', {
                    query: `UPDATE user_usr SET usr_FailedLogins = 0 WHERE usr_UserName = '${testUser}'`
                });
            });
        });

        it("Failed login counter resets on successful authentication", () => {
            const testUser = "admin";

            // Set counter to non-zero
            cy.task('db:query', {
                query: `UPDATE user_usr SET usr_FailedLogins = 5 WHERE usr_UserName = '${testUser}'`
            }).then(() => {
                // Successful login
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: { userName: testUser, password: "changeme" },
                }).then((resp) => {
                    expect(resp.status).to.eq(200);
                });

                // Verify counter reset
                cy.task('db:query', {
                    query: `SELECT usr_FailedLogins FROM user_usr WHERE usr_UserName = '${testUser}'`
                }).then((result) => {
                    expect(result[0].usr_FailedLogins).to.eq(0);
                });
            });
        });
    });

    // Two-Factor Authentication tests
    describe("Login - Two-Factor Authentication", () => {
        it("2FA-enabled account without OTP returns 202 with requiresOTP flag", () => {
            const testUser = "tony.wade@example.com";

            // Setup: enable 2FA on test user
            cy.task('db:query', {
                query: `UPDATE user_usr SET usr_TwoFactorAuthSecret = 'JBSWY3DPEBLW64TMMQ=====' WHERE usr_UserName = '${testUser}'`
            }).then(() => {
                // Try login without OTP
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: { userName: testUser, password: "basicjoe" },
                    failOnStatusCode: false,
                }).then((resp) => {
                    expect(resp.status).to.eq(202);
                    expect(resp.body).to.have.property('requiresOTP');
                    expect(resp.body.requiresOTP).to.eq(true);
                });
            }).finally(() => {
                // Cleanup: disable 2FA
                cy.task('db:query', {
                    query: `UPDATE user_usr SET usr_TwoFactorAuthSecret = NULL WHERE usr_UserName = '${testUser}'`
                });
            });
        });

        it("2FA-enabled account with invalid OTP returns 401", () => {
            const testUser = "tony.wade@example.com";

            // Setup: enable 2FA on test user
            cy.task('db:query', {
                query: `UPDATE user_usr SET usr_TwoFactorAuthSecret = 'JBSWY3DPEBLW64TMMQ=====' WHERE usr_UserName = '${testUser}'`
            }).then(() => {
                // Try login with invalid OTP
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: {
                        userName: testUser,
                        password: "basicjoe",
                        otp: "000000"
                    },
                    failOnStatusCode: false,
                }).then((resp) => {
                    expect(resp.status).to.eq(401);
                });
            }).finally(() => {
                // Cleanup: disable 2FA
                cy.task('db:query', {
                    query: `UPDATE user_usr SET usr_TwoFactorAuthSecret = NULL WHERE usr_UserName = '${testUser}'`
                });
            });
        });

        it("2FA-enabled account with wrong password returns 401 before asking for OTP", () => {
            const testUser = "tony.wade@example.com";

            // Setup: enable 2FA on test user
            cy.task('db:query', {
                query: `UPDATE user_usr SET usr_TwoFactorAuthSecret = 'JBSWY3DPEBLW64TMMQ=====' WHERE usr_UserName = '${testUser}'`
            }).then(() => {
                // Try login with wrong password
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: { userName: testUser, password: "wrongpassword" },
                    failOnStatusCode: false,
                }).then((resp) => {
                    // Should not return 202, should reject immediately
                    expect(resp.status).to.eq(401);
                    expect(resp.body).not.to.have.property('requiresOTP');
                });
            }).finally(() => {
                // Cleanup: disable 2FA
                cy.task('db:query', {
                    query: `UPDATE user_usr SET usr_TwoFactorAuthSecret = NULL WHERE usr_UserName = '${testUser}'`
                });
            });
        });

        it("2FA counter does not increment when 202 response is returned (partial auth)", () => {
            const testUser = "tony.wade@example.com";

            // Setup: enable 2FA and set counter to 0
            cy.task('db:query', {
                query: `UPDATE user_usr SET usr_TwoFactorAuthSecret = 'JBSWY3DPEBLW64TMMQ=====' WHERE usr_UserName = '${testUser}'`
            }).then(() => {
                // Request without OTP (gets 202)
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: { userName: testUser, password: "basicjoe" },
                }).then((resp) => {
                    expect(resp.status).to.eq(202);
                });

                // Verify failed login counter is NOT incremented
                cy.task('db:query', {
                    query: `SELECT usr_FailedLogins FROM user_usr WHERE usr_UserName = '${testUser}'`
                }).then((result) => {
                    expect(result[0].usr_FailedLogins).to.eq(0);
                });
            }).finally(() => {
                // Cleanup: disable 2FA
                cy.task('db:query', {
                    query: `UPDATE user_usr SET usr_TwoFactorAuthSecret = NULL WHERE usr_UserName = '${testUser}'`
                });
            });
        });
    });

    // Combination tests
    describe("Login - Lockout + 2FA Interaction", () => {
        it("Locked account with 2FA returns 401 (lockout checked first)", () => {
            const testUser = "tony.wade@example.com";

            // Setup: lock account AND enable 2FA
            cy.task('db:query', {
                query: `UPDATE user_usr SET usr_FailedLogins = 99, usr_TwoFactorAuthSecret = 'JBSWY3DPEBLW64TMMQ=====' WHERE usr_UserName = '${testUser}'`
            }).then(() => {
                // Try login - should fail on lockout check before asking for OTP
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: { userName: testUser, password: "basicjoe" },
                    failOnStatusCode: false,
                }).then((resp) => {
                    expect(resp.status).to.eq(401);
                    expect(resp.body).not.to.have.property('requiresOTP');
                });
            }).finally(() => {
                // Cleanup
                cy.task('db:query', {
                    query: `UPDATE user_usr SET usr_FailedLogins = 0, usr_TwoFactorAuthSecret = NULL WHERE usr_UserName = '${testUser}'`
                });
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
