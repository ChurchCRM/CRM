/// <reference types="cypress" />

// GHSA-3xq9-c86x-cwpp — CSRF guard on fundraiser delete routes.
// Delete operations are now POST-only on the /fundraiser/ MVC module;
// confirmation is client-side (JS confirm dialog) rather than a server-rendered form.
// Uses setupAdminSession() because the admin role bypasses all individual permission
// flags via isAdmin(), making tests resilient to future seed-data changes.
// (The standard seed user also has DeleteRecords=1 today, but admin is more reliable.)
describe("Fundraiser Delete Operations", () => {
    beforeEach(() => cy.setupAdminSession());

    describe("/fundraiser/{id}/donated-items/{itemId}/delete", () => {
        it("rejects POST without a valid CSRF token", () => {
            cy.request({
                method: "POST",
                url: "/fundraiser/1/donated-items/1/delete",
                form: true,
                body: {
                    csrf_token: "bogus",
                },
                failOnStatusCode: false,
            }).its("status").should("eq", 403);
        });

        it("does not accept GET (delete requires POST)", () => {
            // GET to a POST-only route must return 405 Method Not Allowed (Slim / FastRoute).
            cy.request({
                method: "GET",
                url: "/fundraiser/1/donated-items/1/delete",
                followRedirect: false,
                failOnStatusCode: false,
            }).its("status").should("eq", 405);
        });
    });

    describe("/fundraiser/{id}/paddle-numbers/{paddleId}/delete", () => {
        it("rejects POST without a valid CSRF token", () => {
            cy.request({
                method: "POST",
                url: "/fundraiser/1/paddle-numbers/1/delete",
                form: true,
                body: {
                    csrf_token: "bogus",
                },
                failOnStatusCode: false,
            }).its("status").should("eq", 403);
        });

        it("does not accept GET (delete requires POST)", () => {
            // GET to a POST-only route must return 405 Method Not Allowed (Slim / FastRoute).
            cy.request({
                method: "GET",
                url: "/fundraiser/1/paddle-numbers/1/delete",
                followRedirect: false,
                failOnStatusCode: false,
            }).its("status").should("eq", 405);
        });
    });
});
