/// <reference types="cypress" />

/**
 * API tests for the admin-only Donation Funds CRUD + reorder endpoints.
 *
 * All write operations require Admin role:
 *   POST   /finance/api/funds          → Create fund
 *   PUT    /finance/api/funds/{id}     → Update fund
 *   DELETE /finance/api/funds/{id}     → Delete fund (409 if pledges exist)
 *   PATCH  /finance/api/funds/{id}/order → Reorder up/down
 *
 * Read operations remain on /api/donation-funds (FinanceRole).
 */

const BASE = "/finance/api/funds";

describe("API Admin Finance Funds - POST (create)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Creates a fund and returns 201", () => {
        const name = `Cypress Fund ${Date.now()}`;
        cy.makePrivateAdminAPICall("POST", BASE, { name, description: "Test desc", active: true }, 201).then(
            (response) => {
                const fund = response.body.fund;
                expect(fund.id).to.be.a("number");
                expect(fund.name).to.equal(name);
                expect(fund.description).to.equal("Test desc");
                expect(fund.active).to.equal(true);
                expect(fund.order).to.be.a("number");

                // Cleanup
                cy.makePrivateAdminAPICall("DELETE", `${BASE}/${fund.id}`, null, 200);
            },
        );
    });

    it("Returns 400 when name is empty", () => {
        cy.makePrivateAdminAPICall("POST", BASE, { name: "", description: "x" }, 400);
    });

    it("Returns 400 when name is a duplicate", () => {
        const name = `Cypress Dup ${Date.now()}`;
        cy.makePrivateAdminAPICall("POST", BASE, { name }, 201).then((resp) => {
            const id = resp.body.fund.id;
            cy.makePrivateAdminAPICall("POST", BASE, { name }, 400);
            cy.makePrivateAdminAPICall("DELETE", `${BASE}/${id}`, null, 200);
        });
    });
});

describe("API Admin Finance Funds - PUT (update)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Returns 404 for non-existent fund", () => {
        cy.makePrivateAdminAPICall("PUT", `${BASE}/999999`, { name: "nope" }, 404);
    });

    it("Updates name, description, and active", () => {
        const orig = `Cypress Orig ${Date.now()}`;
        const updated = `Cypress Upd ${Date.now()}`;
        cy.makePrivateAdminAPICall("POST", BASE, { name: orig, active: true }, 201).then((createResp) => {
            const id = createResp.body.fund.id;
            cy.makePrivateAdminAPICall(
                "PUT",
                `${BASE}/${id}`,
                { name: updated, description: "desc2", active: false },
                200,
            ).then((updateResp) => {
                expect(updateResp.body.fund.name).to.equal(updated);
                expect(updateResp.body.fund.description).to.equal("desc2");
                expect(updateResp.body.fund.active).to.equal(false);
            });
            cy.makePrivateAdminAPICall("DELETE", `${BASE}/${id}`, null, 200);
        });
    });

    it("Returns 400 when renaming to a name used by another fund", () => {
        const nameA = `Cypress DupRename A ${Date.now()}`;
        const nameB = `Cypress DupRename B ${Date.now()}`;
        cy.makePrivateAdminAPICall("POST", BASE, { name: nameA }, 201).then((rA) => {
            const idA = rA.body.fund.id;
            cy.makePrivateAdminAPICall("POST", BASE, { name: nameB }, 201).then((rB) => {
                const idB = rB.body.fund.id;
                // Attempt to rename B to A's name — should fail with 400
                cy.makePrivateAdminAPICall("PUT", `${BASE}/${idB}`, { name: nameA }, 400);
                // Cleanup
                cy.makePrivateAdminAPICall("DELETE", `${BASE}/${idA}`, null, 200);
                cy.makePrivateAdminAPICall("DELETE", `${BASE}/${idB}`, null, 200);
            });
        });
    });

    it("Allows keeping the same name without triggering duplicate check", () => {
        const name = `Cypress SameName ${Date.now()}`;
        cy.makePrivateAdminAPICall("POST", BASE, { name }, 201).then((createResp) => {
            const id = createResp.body.fund.id;
            cy.makePrivateAdminAPICall("PUT", `${BASE}/${id}`, { name, description: "updated" }, 200);
            cy.makePrivateAdminAPICall("DELETE", `${BASE}/${id}`, null, 200);
        });
    });

    it("Returns 400 when name is blank", () => {
        cy.makePrivateAdminAPICall("POST", BASE, { name: `Cypress PutEmpty ${Date.now()}` }, 201).then(
            (createResp) => {
                const id = createResp.body.fund.id;
                cy.makePrivateAdminAPICall("PUT", `${BASE}/${id}`, { name: "" }, 400);
                cy.makePrivateAdminAPICall("DELETE", `${BASE}/${id}`, null, 200);
            },
        );
    });
});

describe("API Admin Finance Funds - DELETE", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Returns 404 for non-existent fund", () => {
        cy.makePrivateAdminAPICall("DELETE", `${BASE}/999999`, null, 404);
    });

    it("Returns 409 when deleting a fund referenced by pledges", () => {
        // Fund id=1 ("Pledges") is referenced by rows in pledge_plg in seed data
        cy.makePrivateAdminAPICall("DELETE", `${BASE}/1`, null, 409).then((resp) => {
            expect(resp.body).to.have.property("success", false);
            expect(resp.body).to.have.property("message");
            expect(resp.body.message).to.match(/pledge/i);
        });
    });

    it("Deletes an existing fund and confirms 404 on re-fetch", () => {
        cy.makePrivateAdminAPICall("POST", BASE, { name: `Cypress Del ${Date.now()}` }, 201).then((createResp) => {
            const id = createResp.body.fund.id;
            cy.makePrivateAdminAPICall("DELETE", `${BASE}/${id}`, null, 200).then((delResp) => {
                expect(delResp.body).to.have.property("success", true);
            });
            cy.makePrivateAdminAPICall("GET", `/api/donation-funds/${id}`, null, 404);
        });
    });
});

describe("API Admin Finance Funds - PATCH order (reorder)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Returns 400 for invalid direction", () => {
        cy.makePrivateAdminAPICall("POST", BASE, { name: `Cypress Reorder ${Date.now()}` }, 201).then((resp) => {
            const id = resp.body.fund.id;
            cy.makePrivateAdminAPICall("PATCH", `${BASE}/${id}/order`, { direction: "sideways" }, 400);
            cy.makePrivateAdminAPICall("DELETE", `${BASE}/${id}`, null, 200);
        });
    });

    it("Returns 404 for non-existent fund", () => {
        cy.makePrivateAdminAPICall("PATCH", `${BASE}/999999/order`, { direction: "up" }, 404);
    });

    it("Moves a fund down successfully", () => {
        // Create two funds so the first can move down
        const nameA = `Cypress OrderA ${Date.now()}`;
        const nameB = `Cypress OrderB ${Date.now()}`;
        cy.makePrivateAdminAPICall("POST", BASE, { name: nameA }, 201).then((rA) => {
            const idA = rA.body.fund.id;
            cy.makePrivateAdminAPICall("POST", BASE, { name: nameB }, 201).then((rB) => {
                const idB = rB.body.fund.id;
                // Move first fund down
                cy.makePrivateAdminAPICall("PATCH", `${BASE}/${idA}/order`, { direction: "down" }, 200).then(
                    (moveResp) => {
                        expect(moveResp.body).to.have.property("success", true);
                    },
                );
                // Cleanup
                cy.makePrivateAdminAPICall("DELETE", `${BASE}/${idA}`, null, 200);
                cy.makePrivateAdminAPICall("DELETE", `${BASE}/${idB}`, null, 200);
            });
        });
    });
});

describe("API Admin Finance Funds - Access control", () => {
    it("Returns 401 when unauthenticated", () => {
        cy.clearCookies();
        cy.request({
            method: "GET",
            url: BASE,
            failOnStatusCode: false,
            headers: { "Content-Type": "application/json" },
        }).then((response) => {
            expect(response.status).to.equal(401);
        });
    });

    it("Returns 401 or 403 for a caller without Finance permission", () => {
        cy.makePrivateNoFinanceAPICall("POST", BASE, { name: "x" }, [401, 403]);
    });

    it("Returns 401 or 403 for a caller without Admin or Finance permission", () => {
        // makePrivateUserAPICall uses a standard (non-admin, non-finance) key.
        // The /finance module applies FinanceRoleAuthMiddleware at module level,
        // so a non-finance user is blocked before AdminRoleAuthMiddleware is reached.
        // Either 401 (unauthenticated key) or 403 (insufficient role) is acceptable.
        cy.makePrivateUserAPICall("POST", BASE, { name: "x" }, [401, 403]);
    });

    // NOTE: Testing the Finance-role-but-not-Admin boundary is not covered here
    // because there is no seeded Finance-only API key in the test fixtures.
    // AdminRoleAuthMiddleware rejection in that case is covered by the
    // middleware unit tests.
});
