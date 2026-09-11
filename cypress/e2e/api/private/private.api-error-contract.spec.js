/// <reference types="cypress" />

/**
 * The /api error contract (#9737).
 *
 * (a) One JSON shape. Every error response — route handler, entity middleware,
 *     role middleware, auth middleware, and the Slim error handlers — carries
 *     `success`, `message`, `error` and `code`. `message` is canonical;
 *     `error` is kept as an alias so the existing `responseJSON.error`
 *     consumers keep working.
 *
 * (b) Redaction removes secret *values*, not English words. The old rule was an
 *     unanchored word list that matched "user", "host", "token" and any two
 *     decimals, so `GET /api/user/<unknown>/setting/theme` answered "An error
 *     occurred…" instead of "User not found". Genuinely sensitive content — a
 *     raw SQL statement from a failed ORM write — must still be suppressed.
 */

const CANONICAL_KEYS = ["success", "message", "error", "code"];

/**
 * The install's base path — "" at the root profile, "/churchcrm" on the
 * subdirectory profile.
 *
 * The Slim error handlers echo `$request->getUri()->getPath()`, which is the
 * real request path and therefore carries the base path. Relative URLs handed
 * to cy.request() get baseUrl concatenated onto them, so a literal
 * "/api/no-such-route" is genuinely requested at "/churchcrm/api/no-such-route"
 * there, and asserting the bare "/api/..." only ever held at the root.
 */
const basePath = new URL(Cypress.config("baseUrl")).pathname.replace(/\/+$/, "");

/** Assert a body follows the canonical error shape. */
function expectCanonicalErrorShape(body, expectedCode) {
    CANONICAL_KEYS.forEach((key) => {
        expect(body, `error payload should carry "${key}"`).to.have.property(key);
    });
    expect(body.success).to.eq(false);
    expect(body.error).to.eq(body.message);
    expect(body.message).to.be.a("string").and.not.be.empty;
    if (expectedCode !== undefined) {
        expect(body.code).to.eq(expectedCode);
    }
}

describe("API error contract — one JSON shape (#9737)", () => {
    it("entity middleware 404 (renderErrorJSON)", () => {
        cy.makePrivateAdminAPICall("GET", "/api/volunteer-opportunities/999999", null, 404).then(
            (response) => {
                expectCanonicalErrorShape(response.body, 404);
                expect(response.body.message).to.eq("Volunteer opportunity not found");
            },
        );
    });

    it("role middleware 403", () => {
        cy.makePrivateNoFinanceAPICall("GET", "/api/deposits/dashboard", null, 403).then(
            (response) => {
                expectCanonicalErrorShape(response.body, 403);
                expect(response.body.message).to.eq(
                    "User must be an Admin or have Finance permission",
                );
            },
        );
    });

    it("auth middleware 401", () => {
        cy.makePrivateAPICall("not-a-real-api-key", "GET", "/api/volunteer-opportunities/1", null, 401).then(
            (response) => {
                expectCanonicalErrorShape(response.body, 401);
                expect(response.body.message).to.eq("Invalid API key");
            },
        );
    });

    it("Slim not-found handler 404 keeps its request context", () => {
        cy.makePrivateAdminAPICall("GET", "/api/no-such-route", null, 404).then((response) => {
            expectCanonicalErrorShape(response.body, 404);
            expect(response.body.request).to.deep.eq({
                method: "GET",
                path: `${basePath}/api/no-such-route`,
            });
        });
    });
});

describe("API error redaction — values, not words (#9737)", () => {
    it('returns "User not found" instead of redacting the word "User"', () => {
        cy.makePrivateAdminAPICall("GET", "/api/user/99999999/setting/theme", null, 404).then(
            (response) => {
                expectCanonicalErrorShape(response.body, 404);
                expect(response.body.message).to.eq("User not found");
                expect(response.body.message).to.not.match(/An error occurred/i);
            },
        );
    });

    it("still suppresses the raw SQL of a failed ORM write", () => {
        // event_title is varchar(255); a longer title fails the INSERT under
        // MySQL/MariaDB strict mode. Before the fix the client received the
        // whole statement — table name, column list and bound parameters.
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events",
            {
                Title: "x".repeat(300),
                Type: 1,
                Start: "2099-09-20T09:00:00",
                End: "2099-09-20T10:00:00",
                PinnedCalendars: [1],
            },
            500,
        ).then((response) => {
            expectCanonicalErrorShape(response.body, 500);
            expect(response.body.message).to.eq(
                "A database error occurred. Please contact your system administrator.",
            );

            const body = JSON.stringify(response.body);
            expect(body).to.not.match(/INSERT INTO/i);
            expect(body).to.not.match(/events_event/i);
            expect(body).to.not.match(/SQLSTATE/i);
            expect(body).to.not.match(/:p\d/);
            expect(body).to.not.match(/Stack trace/i);
            expect(body).to.not.match(/\.php:\d+/);
        });

        // The failed write must not have left a row behind.
        cy.makePrivateAdminAPICall("GET", "/api/events", null, 200).then((response) => {
            const oversized = response.body.Events.filter((event) => event.Title.length > 255);
            expect(oversized, "no oversized event row was created").to.have.length(0);
        });
    });
});
