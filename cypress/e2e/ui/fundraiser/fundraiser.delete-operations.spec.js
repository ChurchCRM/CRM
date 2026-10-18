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

// Issue #10684: fundraiser resources use ManageFundraisers; person/family
// DeleteRecords is not an additional requirement. Positive tests use the
// existing standard account (ManageFundraisers=1) and a non-existent record
// ID (0), so they verify the guarded route without deleting seeded data.
// A ManageFundraisers=1/DeleteRecords=0 fixture is not currently seeded;
// that cross-role integration case must be verified once such a fixture exists.
describe("Fundraiser delete routes use the shared ManageFundraisers gate", () => {
    beforeEach(() => cy.setupStandardSession());

    const extractCsrf = (html) => {
        const match = html.match(/name="csrf_token"\s+value="([a-f0-9]+)"/i);
        expect(match, "CSRF synchronizer token in the fundraiser editor").to.not.be.null;
        return match[1];
    };

    [
        {
            url: "/fundraiser/1/donated-items/0/delete",
            target: "/fundraiser/editor/1",
        },
        {
            url: "/fundraiser/1/paddle-numbers/0/delete",
            target: "/fundraiser/1/paddle-numbers",
        },
    ].forEach(({ url, target }) => {
        it(`accepts a manager's POST on ${url} with a valid CSRF token`, () => {
            cy.request("/fundraiser/1/donated-items/editor").then(({ body }) => {
                cy.request({
                    method: "POST",
                    url,
                    form: true,
                    followRedirect: false,
                    failOnStatusCode: false,
                    body: { csrf_token: extractCsrf(body) },
                }).then((resp) => {
                    expect(resp.status).to.eq(302);
                    expect(resp.headers.location).to.include(target);
                });
            });
        });
    });
});
