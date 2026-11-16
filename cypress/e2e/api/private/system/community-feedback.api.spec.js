/// <reference types="cypress" />

describe("Community feedback API", () => {
    it("returns a count and items for a signed-in user", () => {
        cy.setupAdminSession();
        cy.request("/api/community-feedback").then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.count).to.be.a("number");
            expect(response.body.items).to.be.an("array");
        });
    });

    it("refuses a caller who is not signed in", () => {
        cy.request({
            method: "GET",
            url: "/api/community-feedback",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.eq(401);
        });
    });
});
