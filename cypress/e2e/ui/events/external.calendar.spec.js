/// <reference types="cypress" />

/**
 * External Calendar routes — the "not logged in" surface used when a
 * church shares a public calendar URL with someone outside the system.
 * Covers both the HTML browser view (/external/calendars/{token}) and
 * the JSON API used by FullCalendar and other clients
 * (/api/public/calendar/{token}/events).
 *
 * The four error states in PublicCalendarMiddleware render friendly
 * error pages when the request expects HTML and structured JSON when
 * the request expects JSON:
 *   - 403 External calendar sharing is disabled (config off)
 *   - 400 Missing calendar access token
 *   - 404 Calendar not found (bad token)
 *   - 400 Invalid date format (bad ?start=/?end=)
 */

/** Toggle bEnableExternalCalendarAPI via the admin config API. The
 *  route is mounted under /admin by src/admin/index.php — NOT /api
 *  — so the path is `/admin/api/system/config/{configName}`. Requires
 *  an active admin session cookie. */
function setExternalCalendarApi(enabled) {
    cy.setupAdminSession();
    cy.request({
        method: "POST",
        url: "/admin/api/system/config/bEnableExternalCalendarAPI",
        body: { value: enabled ? "1" : "0" },
        headers: { "Content-Type": "application/json" },
    });
}

/**
 * Helper: assert that a response from the external-calendar route has the
 * correct framing headers — no X-Frame-Options restriction and a CSP
 * frame-ancestors directive that permits embedding from any origin.
 *
 * We check whichever CSP flavour the server sends
 * (Content-Security-Policy or Content-Security-Policy-Report-Only —
 *  determined by the bEnforceCSP system config, defaulting to report-only).
 */
function assertFramingHeadersOpen(response) {
    // Must NOT send X-Frame-Options — its absence means no legacy restriction.
    expect(
        response.headers['x-frame-options'],
        'x-frame-options should be absent on the external calendar route',
    ).to.be.undefined;

    // Exactly one CSP flavour should be present; pick whichever is set.
    const cspHeader =
        response.headers['content-security-policy'] ??
        response.headers['content-security-policy-report-only'];
    expect(cspHeader, 'a CSP header must be present').to.be.a('string');

    // frame-ancestors must be open to all origins…
    expect(cspHeader).to.match(
        /frame-ancestors \*/,
        'frame-ancestors should be "*" on the external calendar route',
    );
    // …and must NOT carry the same-origin restriction.
    expect(cspHeader).not.to.match(
        /frame-ancestors 'self'/,
        "frame-ancestors must not be 'self' on the external calendar route",
    );
}

describe("External calendar — HTML surface", () => {
    after(() => {
        // Leave the setting disabled so other specs aren't surprised. The
        // seed default is empty/false, and the cart-to-event spec etc.
        // don't rely on public calendar access.
        setExternalCalendarApi(false);
    });

    it("renders a friendly error page when the external calendar API is disabled", () => {
        setExternalCalendarApi(false);

        cy.visit("/external/calendars/anytoken", { failOnStatusCode: false });
        cy.contains("External calendar sharing is disabled").should("be.visible");
        cy.contains("Go to home").should("be.visible");
        // Church logo renders (either configured sChurchLogoURL or the
        // bundled fallback served via Images/logo-churchcrm-350.jpg).
        cy.get("img[alt]").should("have.length.at.least", 1);
    });

    it("renders 'Calendar not found' for an invalid token when the API is enabled", () => {
        setExternalCalendarApi(true);

        cy.visit("/external/calendars/notarealtoken12345", { failOnStatusCode: false });
        cy.contains("Calendar not found").should("be.visible");
        cy.contains("Go to home").should("be.visible");
    });

    it("omits X-Frame-Options and sets frame-ancestors * when the API is disabled (error page)", () => {
        // Framing headers are emitted before content is rendered, so even
        // the 403 error page (API disabled) must carry the open headers.
        setExternalCalendarApi(false);

        cy.request({
            url: "/external/calendars/anytoken",
            failOnStatusCode: false,
        }).then(assertFramingHeadersOpen);
    });

    it("omits X-Frame-Options and sets frame-ancestors * for a 404 (bad token)", () => {
        setExternalCalendarApi(true);

        cy.request({
            url: "/external/calendars/notarealtoken12345",
            failOnStatusCode: false,
        }).then(assertFramingHeadersOpen);
    });
});

describe("External calendar — JSON surface", () => {
    after(() => setExternalCalendarApi(false));

    it("returns a structured JSON 403 when the external calendar API is disabled", () => {
        setExternalCalendarApi(false);

        cy.request({
            url: "/api/public/calendar/anytoken/events",
            failOnStatusCode: false,
            headers: { Accept: "application/json" },
        }).then((response) => {
            expect(response.status).to.eq(403);
            expect(response.headers["content-type"]).to.match(/application\/json/);
            expect(response.body).to.have.property("error");
            expect(response.body).to.have.property("message");
        });
    });

    it("returns a structured JSON 404 for an unknown calendar token when the API is enabled", () => {
        setExternalCalendarApi(true);

        cy.request({
            url: "/api/public/calendar/nottherealtoken/events",
            failOnStatusCode: false,
            headers: { Accept: "application/json" },
        }).then((response) => {
            expect(response.status).to.eq(404);
            expect(response.headers["content-type"]).to.match(/application\/json/);
            expect(response.body).to.have.property("error");
            expect(response.body.error).to.match(/not found/i);
        });
    });

    it("routes /api/public/... paths to JSON even without an explicit Accept header", () => {
        setExternalCalendarApi(false);

        cy.request({
            url: "/api/public/calendar/anytoken/ics",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.eq(403);
            expect(response.headers["content-type"]).to.match(/application\/json/);
        });
    });
});

describe("Framing-header regression — non-external routes still protected", () => {
    // Verifies that the $allowFraming opt-in in the external calendar
    // templates does NOT affect any other route.
    it("sends X-Frame-Options: SAMEORIGIN on the public login page", () => {
        // The setup / login page is always accessible without credentials
        // and goes through HeaderNotLoggedIn.php without $allowFraming set.
        cy.request({
            url: "/",
            followRedirect: true,
            failOnStatusCode: false,
        }).then((response) => {
            expect(
                (response.headers['x-frame-options'] ?? '').toLowerCase(),
                'non-external pages must still block cross-origin framing',
            ).to.eq('sameorigin');
        });
    });
});
