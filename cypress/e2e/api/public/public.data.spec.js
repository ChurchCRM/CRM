/// <reference types="cypress" />

context("API Public Data", () => {
    it("Countries", () => {
        cy.request({
            method: "GET",
            url: "/api/public/data/countries",
        }).then((resp) => {
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(resp.status).to.eq(200);
            expect(result.length).be.greaterThan(250);
        });
    });

    it("US States", () => {
        cy.request({
            method: "GET",
            url: "/api/public/data/countries/us/states",
        }).then((resp) => {
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(resp.status).to.eq(200);
            expect(result["AL"]).to.eq("Alabama");
            expect(result["WA"]).to.eq("Washington");
        });
    });

    it("States Canada", () => {
        cy.request({
            method: "GET",
            url: "/api/public/data/countries/ca/states",
        }).then((resp) => {
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(resp.status).to.eq(200);
            expect(result["BC"]).to.eq("British Columbia");
        });
    });
});
