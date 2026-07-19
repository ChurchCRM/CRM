/// <reference types="cypress" />

/**
 * API tests for the POST /people/cart/to-family route.
 *
 * Focuses on:
 *   T8 — CSRF protection: POST without CSRF token and without API-key → 403
 *   T9 — Validate-before-write: blank FamilyName must not create an orphan family row
 *
 * Session-management note
 * ───────────────────────
 * This route lives in the People MVC module, not the /api/ module. It is
 * protected by AuthMiddleware (cookie-based session) and CSRFMiddleware (skips
 * CSRF when X-API-Key is present). makePrivateAdminAPICall() (withCredentials:
 * false) causes the server to return a Set-Cookie that Cypress stores in its
 * browser cookie jar, overwriting any active browser session. To recover a
 * usable browser session after any makePrivateAdminAPICall() call, each test
 * must call freshStandardLogin() before making browser-cookie-based requests.
 *
 * Seed data:
 *   Free person: 36 (Kathryn Robertson, per_fam_ID = 0)
 */
describe("API — Cart to Family", () => {
    const ROUTE = "/people/cart/to-family";

    /**
     * Re-establishes a valid browser session for the standard user by POSTing
     * directly to the login endpoint. Must be called AFTER any
     * makePrivateAdminAPICall() that clobbered the session cookie.
     *
     * This is the same pattern used in standard.cart-to-family.spec.js T1-T7
     * to recover from API-key-induced session clobbering.
     */
    function freshStandardLogin() {
        cy.request({
            method: "POST",
            url: "/session/begin",
            form: true,
            body: {
                User: Cypress.env("standard.username"),
                Password: Cypress.env("standard.password"),
            },
            followRedirect: false, // 302 → /v2/dashboard on success
        });
    }

    beforeEach(() => {
        // Clear any leftover cart state. This call clobbers the browser session
        // cookie (makes it an API-token session); each test must call
        // freshStandardLogin() before making browser-cookie-based requests.
        cy.makePrivateAdminAPICall("DELETE", "/api/cart/", null, 200);
        cy.on("uncaught:exception", () => false);
    });

    // ── T8: No CSRF token → 403 ─────────────────────────────────────────────
    it("T8 — POST without CSRF token (no X-API-Key) returns 403", () => {
        // Restore a valid browser session after beforeEach clobbered it with
        // an API-token session. Without this, AuthMiddleware would return 302
        // (not yet a browser session) before CSRFMiddleware even runs.
        freshStandardLogin();

        // POST with browser session cookies but WITHOUT X-API-Key and WITHOUT
        // a CSRF token. CSRFMiddleware should reject with 403.
        // followRedirect: false ensures we capture the raw 403 and do not
        // silently follow a 302-to-login that would yield 200.
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
            followRedirect: false,
        }).then((resp) => {
            expect(resp.status).to.equal(403);
        });
    });

    // ── T9: Validate-before-write — no orphan family on validation failure ───
    it("T9 — validation failure (blank FamilyName) does not create an orphan family", () => {
        // Restore a valid browser session so subsequent cy.request() calls use
        // the same cookie-based session (needed for cart state to persist).
        freshStandardLogin();

        // Record the current family count using the same browser session.
        // We use cy.request() (not makePrivateAdminAPICall) so the session
        // cookie is shared with the cart-add and form-POST below.
        cy.request({
            method: "GET",
            url: "/api/families/",
            failOnStatusCode: false,
        }).then((resp) => {
            const familyCountBefore = resp.body.Families
                ? resp.body.Families.length
                : resp.body.data
                  ? resp.body.data.length
                  : 0;

            // Add a free person to the cart using the current browser session
            // (same session that the form POST will use, so Cart::hasPeople()
            // returns true inside the handler).
            cy.request({
                method: "POST",
                url: "/api/cart/",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ Persons: [36] }),
                failOnStatusCode: false,
            });

            // POST to the MVC route with X-API-Key (bypasses CSRF) and the
            // current browser session cookie (which has person 36 in the cart).
            // FamilyName is deliberately blank to trigger the validation error.
            // The handler must re-render (200) without creating a family row.
            cy.request({
                method: "POST",
                url: ROUTE,
                form: true,
                headers: { "X-API-Key": Cypress.env("admin.api.key") },
                body: {
                    FamilyID: "0",
                    FamilyName: "",
                    role36: "1",
                },
                failOnStatusCode: false,
                followRedirect: true,
            }).then((postResp) => {
                // 200 = form re-rendered with validation error (no family created)
                expect(postResp.status).to.equal(200);
            });

            // Family count must be unchanged — no orphan row was created.
            cy.request({
                method: "GET",
                url: "/api/families/",
                failOnStatusCode: false,
            }).then((afterResp) => {
                const familyCountAfter = afterResp.body.Families
                    ? afterResp.body.Families.length
                    : afterResp.body.data
                      ? afterResp.body.data.length
                      : 0;
                expect(familyCountAfter).to.equal(familyCountBefore);
            });
        });
    });
});
