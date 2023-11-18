/// <reference types="cypress" />

context("API Public User", () => {
    it("Login", () => {
        let user = {
            userName: "admin",
            password: "changeme",
        };

        cy.request({
            method: "POST",
            url: "/api/public/user/login",
            headers: { "content-type": "application/json" },
            body: user,
        }).then((resp) => {
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(resp.status).to.eq(200);
            expect(result.apiKey).to.eq(Cypress.env("admin.api.key"));
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
