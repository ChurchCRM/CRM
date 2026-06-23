/// <reference types="cypress" />

describe("API Public Data", () => {
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

    // --- Regression guard: route must accept uppercase codes (frontend lowercases, but
    //     the regex [A-Za-z]{2} should also allow uppercase for robustness) ---
    it("US States uppercase country code returns 200", () => {
        cy.request({
            method: "GET",
            url: "/api/public/data/countries/US/states",
        }).then((resp) => {
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(resp.status).to.eq(200);
            expect(result["AL"]).to.eq("Alabama");
        });
    });

    // --- Valid ISO code with no states file returns 200 with empty object ---
    // XK (Kosovo) is in the Countries list but has no locale/states/xk.json file.
    it("Valid country code with no states file returns 200 empty object", () => {
        cy.request({
            method: "GET",
            url: "/api/public/data/countries/xk/states",
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.deep.equal({});
        });
    });

    // --- Path traversal and invalid inputs must all 404 ---
    it("Path traversal attempt returns 404", () => {
        cy.request({
            method: "GET",
            url: "/api/public/data/countries/../locale/messages/states",
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(404);
        });
    });

    it("Country code too short (1 char) returns 404", () => {
        cy.request({
            method: "GET",
            url: "/api/public/data/countries/u/states",
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(404);
        });
    });

    it("Country code too long (3 chars) returns 404", () => {
        cy.request({
            method: "GET",
            url: "/api/public/data/countries/usa/states",
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(404);
        });
    });

    it("Country code with digit returns 404", () => {
        cy.request({
            method: "GET",
            url: "/api/public/data/countries/u1/states",
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(404);
        });
    });

    it("Country code with special character returns 404", () => {
        cy.request({
            method: "GET",
            url: "/api/public/data/countries/u./states",
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(404);
        });
    });
});
