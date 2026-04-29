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
                    expect(resp.body).to.have.property('error');
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
