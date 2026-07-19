/// <reference types="cypress" />

/**
 * API tests for the POST /people/cart/to-family route.
 *
 * Focuses on:
 *   T8 — CSRF protection: POST without CSRF token and without API-key → 403
 *   T9 — Validate-before-write: validation failure must not create an orphan family row
 *
 * Seed data used:
 *   Free person: 36 (Kathryn Robertson, per_fam_ID = 0)
 */
describe("API — Cart to Family", () => {
    const ROUTE = "/people/cart/to-family";

    beforeEach(() => {
        cy.setupStandardSession();
        // Ensure cart is empty before each test
        cy.makePrivateAdminAPICall("DELETE", "/api/cart/", null, 200);
        cy.on("uncaught:exception", () => false);
    });

    // ── T8: No CSRF token → 403 ─────────────────────────────────────────────
    it("T8 — POST without CSRF token (no X-API-Key) returns 403", () => {
        // A plain cy.request() sends cookies (from the active session) but NO X-API-Key
        // and NO _token in the body → CSRFMiddleware should reject it.
        cy.request({
            method: "POST",
            url: ROUTE,
            form: true,
            body: {
                FamilyID: "0",
                FamilyName: "CSRFTestFamily",
                role36: "1",
            },
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.equal(403);
        });
    });

    // ── T9: Validate-before-write — no orphan family on validation failure ───
    it("T9 — validation failure (blank FamilyName) does not create an orphan family", () => {
        // Add a free person to the cart first
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/",
            JSON.stringify({ Persons: [36] }),
            200,
        );

        // Record the current number of families
        cy.makePrivateAdminAPICall("GET", "/api/families/", null, 200).then(
            (resp) => {
                const familyCountBefore = resp.body.Families
                    ? resp.body.Families.length
                    : (resp.body.data ? resp.body.data.length : 0);

                // POST with an API key (skips CSRF) but with blank FamilyName
                // The handler should reject with 200 (re-render) — no family is created.
                cy.makePrivateAdminAPICall(
                    "POST",
                    ROUTE,
                    JSON.stringify({
                        FamilyID: "0",
                        FamilyName: "", // deliberately blank
                        "role36": "1",
                    }),
                    200, // re-render (validation error)
                );

                // Family count must be unchanged
                cy.makePrivateAdminAPICall("GET", "/api/families/", null, 200).then(
                    (afterResp) => {
                        const familyCountAfter = afterResp.body.Families
                            ? afterResp.body.Families.length
                            : (afterResp.body.data ? afterResp.body.data.length : 0);
                        expect(familyCountAfter).to.equal(familyCountBefore);
                    },
                );
            },
        );
    });
});
