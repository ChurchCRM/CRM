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
 * API-key session clobbering (T9)
 * ───────────────────────────────
 * Sending X-API-Key on any request causes AuthMiddleware to call
 * AuthenticationManager::authenticate(new APITokenAuthenticationRequest(...)),
 * which MUTATES the current PHP session — replacing the cookie-based user auth
 * state with API-token auth state. Any subsequent request using the same
 * browser session cookie without an X-API-Key header will then return 401
 * (the session no longer carries valid cookie-auth). This is why the final
 * GET /api/families/latest check in T9 must also send X-API-Key.
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
        // Restore a valid browser session so the POST /api/cart/ call below
        // uses the same cookie-based session (needed for cart state to persist
        // into the /people/cart/to-family handler via $_SESSION['aPeopleCart']).
        freshStandardLogin();

        // Record the most recently created family ID before the test.
        // GET /api/families/latest returns the 10 most recently entered
        // families as {families: [{FamilyId, Name, ...}, ...]}.
        // Use X-API-Key so this request is independent of session state.
        cy.request({
            method: "GET",
            url: "/api/families/latest",
            headers: { "X-API-Key": Cypress.env("admin.api.key") },
            failOnStatusCode: false,
        }).then((resp) => {
            const latestFamilies = (resp.body && resp.body.families) ? resp.body.families : [];
            const latestFamilyIdBefore = latestFamilies.length > 0
                ? latestFamilies[0].FamilyId
                : null;

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
            //
            // IMPORTANT: this POST mutates the PHP session (replaces cookie-auth
            // state with API-token auth state). Any subsequent request using the
            // same browser cookie without X-API-Key will return 401.
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

            // Most-recently-created family must be unchanged — no orphan row was created.
            // Must use X-API-Key because the POST above mutated the session: the
            // browser cookie now carries API-token auth state, not cookie-user auth,
            // so bare cookie requests return 401 until a new login is performed.
            cy.request({
                method: "GET",
                url: "/api/families/latest",
                headers: { "X-API-Key": Cypress.env("admin.api.key") },
                failOnStatusCode: false,
            }).then((afterResp) => {
                const latestFamiliesAfter = (afterResp.body && afterResp.body.families) ? afterResp.body.families : [];
                const latestFamilyIdAfter = latestFamiliesAfter.length > 0
                    ? latestFamiliesAfter[0].FamilyId
                    : null;
                expect(latestFamilyIdAfter).to.equal(latestFamilyIdBefore);
            });
        });
    });
});
