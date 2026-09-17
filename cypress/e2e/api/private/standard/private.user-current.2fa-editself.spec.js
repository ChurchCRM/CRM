/// <reference types="cypress" />

/**
 * Regression tests for issue #9886.
 *
 * EditSelf is an exclusive permission mode: AuthMiddleware blocks an
 * EditSelf-only user from the whole internal API surface
 * (User::isEditSelfExclusive()). The 2FA self-service endpoints under
 * /api/user/current/ are the documented exception — they only ever act on
 * AuthenticationManager::getCurrentUser(), and the page that drives them
 * (/v2/user/current/manage2fa) is already exempt. Without the API exemption
 * the QR code never renders and a user under bRequire2FA is locked out
 * permanently.
 *
 * Test user: amanda.black (user ID 99, `selfedit.api.key`)
 *   - Permissions: EditSelf=1 ONLY (all others = 0)
 *
 * The companion spec private.selfedit.family-scope.spec.js asserts that every
 * OTHER internal endpoint still returns 403 for this user; the last test here
 * repeats a sample of that so a regression that widens the exemption fails
 * inside this file too.
 */
describe("Issue #9886 - EditSelf-only users can manage their own 2FA", () => {
    it("GET /api/user/current/2fa-status → 200", () => {
        cy.makePrivateEditSelfAPICall(
            "GET",
            "/api/user/current/2fa-status",
            null,
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("IsEnabled");
            expect(resp.body.IsEnabled).to.be.a("boolean");
        });
    });

    it("POST /api/user/current/refresh2fasecret → 200 and returns a QR code", () => {
        cy.makePrivateEditSelfAPICall(
            "POST",
            "/api/user/current/refresh2fasecret",
            {},
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("TwoFAQRCodeDataUri");
            expect(resp.body.TwoFAQRCodeDataUri).to.match(/^data:image\/png;base64,/);
        });
    });

    it("POST /api/user/current/refresh2farecoverycodes → 200 and returns codes", () => {
        cy.makePrivateEditSelfAPICall(
            "POST",
            "/api/user/current/refresh2farecoverycodes",
            {},
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("TwoFARecoveryCodes");
            expect(resp.body.TwoFARecoveryCodes).to.be.an("array").and.not.be.empty;
        });
    });

    it("POST /api/user/current/remove2fasecret → 200", () => {
        cy.makePrivateEditSelfAPICall(
            "POST",
            "/api/user/current/remove2fasecret",
            {},
            200,
        );
    });

    // The remaining two exempt endpoints cannot return 200 under API-key auth:
    // get2faqrcode needs an enrolled secret and test2FAEnrollmentCode needs the
    // provisional key that refresh2fasecret leaves on the SESSION user object.
    // Assert only what this spec is about — that AuthMiddleware no longer
    // answers 403 — rather than whitelisting the handler's own status code.
    const notBlocked = (method, url, body) => {
        cy.apiRequest({
            method,
            url,
            body,
            headers: { "x-api-key": Cypress.env("selfedit.api.key") },
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status, `${method} ${url}`).to.not.eq(403);
        });
    };

    it("GET /api/user/current/get2faqrcode is not blocked by the limited-access gate", () => {
        notBlocked("GET", "/api/user/current/get2faqrcode");
    });

    it("POST /api/user/current/test2FAEnrollmentCode is not blocked by the limited-access gate", () => {
        // Guards the AUTH_FLOW_EXEMPT_PATHS entry for the TOTP verification step
        // that completes enrollment. The happy path (200 + "Code is invalid" for a
        // wrong code) is covered through a real session in
        // cypress/e2e/ui/security/limited-access.spec.js.
        notBlocked("POST", "/api/user/current/test2FAEnrollmentCode", {
            enrollmentCode: "000000",
        });
    });

    it("the exemption stays narrow — other internal APIs are still 403", () => {
        cy.makePrivateEditSelfAPICall("GET", "/api/person/99", null, 403);
        cy.makePrivateEditSelfAPICall("GET", "/api/family/20", null, 403);
        cy.makePrivateEditSelfAPICall("GET", "/api/user/99/setting/ui.style", null, 403);
    });
});
