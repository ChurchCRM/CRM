/// <reference types="cypress" />

/**
 * API tests for the church logo endpoints (issue #9717)
 *
 * Covers:
 *   GET    /api/system/church-logo
 *   POST   /api/system/church-logo
 *   DELETE /api/system/church-logo
 *
 * The logo is global state (a file at src/Images/church-logo.png), so every
 * test deletes it up front and the suite deletes it again at the end — a stray
 * logo would change the sidebar and login page for every other spec.
 */

// 1x1 transparent PNG — the canonical synthetic image payload used by the
// person photo specs.
const VALID_PNG_DATA_URI =
    "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";

const LOGO_URL = "/api/system/church-logo";

describe("API Private Admin Church Logo", () => {
    beforeEach(() => {
        // Clean up anything a prior (possibly failed) run left behind. DELETE is
        // idempotent, so 200 is the only expected status.
        cy.makePrivateAdminAPICall("DELETE", LOGO_URL, null, 200);
    });

    after(() => {
        cy.makePrivateAdminAPICall("DELETE", LOGO_URL, null, 200);
    });

    describe("GET /api/system/church-logo", () => {
        it("Reports no custom logo and the bundled default URL", () => {
            cy.makePrivateAdminAPICall("GET", LOGO_URL, null, 200).then(
                (response) => {
                    expect(response.body).to.have.property(
                        "hasCustomLogo",
                        false,
                    );
                    expect(response.body.url).to.include(
                        "logo-churchcrm-350.jpg",
                    );
                },
            );
        });
    });

    describe("POST /api/system/church-logo", () => {
        it("Stores an uploaded PNG and reports it through GET", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_URL,
                { imgBase64: VALID_PNG_DATA_URI },
                200,
            ).then((response) => {
                expect(response.body).to.have.property("success", true);
                expect(response.body).to.have.property("hasCustomLogo", true);
                expect(response.body.url).to.include("church-logo.png");
            });

            cy.makePrivateAdminAPICall("GET", LOGO_URL, null, 200).then(
                (response) => {
                    expect(response.body).to.have.property(
                        "hasCustomLogo",
                        true,
                    );
                    // Cache-busting version token so a re-upload is picked up.
                    expect(response.body.url).to.match(
                        /church-logo\.png\?v=\d+$/,
                    );
                },
            );
        });

        it("Serves the stored logo over HTTP", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_URL,
                { imgBase64: VALID_PNG_DATA_URI },
                200,
            ).then((response) => {
                cy.request({ url: response.body.url }).then((imageResponse) => {
                    expect(imageResponse.status).to.equal(200);
                    expect(imageResponse.headers["content-type"]).to.include(
                        "image/png",
                    );
                });
            });
        });

        it("Rejects a non-image payload with 400", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_URL,
                { imgBase64: "data:text/plain;base64,SGVsbG8gV29ybGQ=" },
                400,
            ).then((response) => {
                expect(response.body).to.have.property("success", false);
            });

            cy.makePrivateAdminAPICall("GET", LOGO_URL, null, 200).then(
                (response) => {
                    expect(response.body).to.have.property(
                        "hasCustomLogo",
                        false,
                    );
                },
            );
        });

        it("Rejects a malformed data URI with 400", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_URL,
                { imgBase64: "not-a-data-uri" },
                400,
            );
        });

        it("Rejects a request with no image data with 400", () => {
            cy.makePrivateAdminAPICall("POST", LOGO_URL, {}, 400);
        });
    });

    describe("DELETE /api/system/church-logo", () => {
        it("Removes an uploaded logo and falls back to the default", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_URL,
                { imgBase64: VALID_PNG_DATA_URI },
                200,
            );

            cy.makePrivateAdminAPICall("DELETE", LOGO_URL, null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("success", true);
                    expect(response.body).to.have.property(
                        "hasCustomLogo",
                        false,
                    );
                },
            );

            cy.makePrivateAdminAPICall("GET", LOGO_URL, null, 200).then(
                (response) => {
                    expect(response.body).to.have.property(
                        "hasCustomLogo",
                        false,
                    );
                    expect(response.body.url).to.include(
                        "logo-churchcrm-350.jpg",
                    );
                },
            );
        });

        it("Is idempotent when no logo is stored", () => {
            cy.makePrivateAdminAPICall("DELETE", LOGO_URL, null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("success", true);
                },
            );
        });
    });

    describe("Access control", () => {
        it("Returns 401 when no API key is provided", () => {
            // cy.clearCookies() removes the session cookie from Cypress's cookie jar
            // so the request is truly unauthenticated (no API key, no session cookie).
            cy.clearCookies();
            cy.request({
                method: "GET",
                url: LOGO_URL,
                failOnStatusCode: false,
                headers: { "content-type": "application/json" },
            }).then((response) => {
                expect(response.status).to.equal(401);
            });
        });

        it("Returns 403 for a non-admin user on GET", () => {
            cy.makePrivateUserAPICall("GET", LOGO_URL, null, 403);
        });

        it("Returns 403 for a non-admin user on POST", () => {
            cy.makePrivateUserAPICall(
                "POST",
                LOGO_URL,
                { imgBase64: VALID_PNG_DATA_URI },
                403,
            );
        });

        it("Returns 403 for a non-admin user on DELETE", () => {
            cy.makePrivateUserAPICall("DELETE", LOGO_URL, null, 403);
        });
    });
});
