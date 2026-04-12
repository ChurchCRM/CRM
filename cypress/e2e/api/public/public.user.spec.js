/// <reference types="cypress" />

describe("API Public User", () => {
    it("Login", () => {
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

    it("Login with non-existent user returns error", () => {
        cy.apiRequest({
            method: "POST",
            url: "/api/public/user/login",
            headers: { "content-type": "application/json" },
            body: { userName: "nonexistent_user_xyz", password: "wrong" },
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.be.oneOf([401, 404]);
        });
    });

    it("Login with wrong password returns 401", () => {
        cy.apiRequest({
            method: "POST",
            url: "/api/public/user/login",
            headers: { "content-type": "application/json" },
            body: { userName: "admin", password: "wrongpassword" },
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(401);
        });
    });

    it("Login with empty userName returns error", () => {
        cy.apiRequest({
            method: "POST",
            url: "/api/public/user/login",
            headers: { "content-type": "application/json" },
            body: { userName: "", password: "test" },
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.be.oneOf([401, 404]);
        });
    });

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
