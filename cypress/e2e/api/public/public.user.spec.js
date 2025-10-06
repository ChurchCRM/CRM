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

    it("Reset Password", () => {
        cy.request({
            method: "POST",
            url: "/session/forgot-password/reset-request",
            headers: { "content-type": "application/json" },
            body: {
                userName: "tony.wade@example.com",
            },
        }).then((resp) => {
            expect(resp.status).to.eq(200);
        });
    });
});
