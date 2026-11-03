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
    before(() => {
        cy.rememberTestEnv(["selfedit.api.key"]);
    });
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

    // get2faqrcode needs an enrolled secret; this spec only checks its access gate.
    const notBlocked = (method, url, body) => {
        cy.apiRequest({
            method,
            url,
            body,
            headers: { "x-api-key": Cypress.testEnv("selfedit.api.key") },
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status, `${method} ${url}`).to.not.eq(403);
        });
    };

    it("GET /api/user/current/get2faqrcode is not blocked by the limited-access gate", () => {
        notBlocked("GET", "/api/user/current/get2faqrcode");
    });

    it("POST /api/user/current/test2FAEnrollmentCode rejects an unprovisioned code without throwing", () => {
        cy.makePrivateEditSelfAPICall(
            "POST",
            "/api/user/current/test2FAEnrollmentCode",
            { enrollmentCode: "000000" },
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("IsEnrollmentCodeValid", false);
        });
    });

    it("the exemption stays narrow — other internal APIs are still 403", () => {
        cy.makePrivateEditSelfAPICall("GET", "/api/person/99", null, 403);
        cy.makePrivateEditSelfAPICall("GET", "/api/family/20", null, 403);
        cy.makePrivateEditSelfAPICall("GET", "/api/user/99/setting/ui.style", null, 403);
    });
});
