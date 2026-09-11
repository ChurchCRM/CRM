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
const LOGO_CONFIG_URL = "/admin/api/system/config/sChurchLogoURL";

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

    describe("sChurchLogoURL is not used by in-app pages", () => {
        let originalLogoConfig = "";

        before(() => {
            cy.makePrivateAdminAPICall("GET", LOGO_CONFIG_URL, null, 200).then(
                (response) => {
                    originalLogoConfig = response.body.value ?? "";
                },
            );
        });

        after(() => {
            // Leave the dev database exactly as it was found.
            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_CONFIG_URL,
                { value: originalLogoConfig },
                200,
            );
        });

        it("Falls back to the bundled default, not the configured remote URL", () => {
            // In-app pages are served with a CSP whose img-src is 'self', so a
            // remote sChurchLogoURL must never reach them — it is an email-only
            // fallback.
            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_CONFIG_URL,
                { value: "https://example.com/logo.png" },
                200,
            );

            cy.makePrivateAdminAPICall("GET", LOGO_URL, null, 200).then(
                (response) => {
                    expect(response.body).to.have.property(
                        "hasCustomLogo",
                        false,
                    );
                    expect(response.body.url).to.match(
                        /\/Images\/logo-churchcrm-350\.jpg$/,
                    );
                    expect(response.body.url).to.not.include("example.com");
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
                // The API returns a root-relative URL that already carries the
                // install's base path ("/churchcrm/Images/..." on a subdirectory
                // install). Cypress prefixes relative URLs with baseUrl, which
                // would double the base path there, so resolve against the
                // origin instead — a root-relative path drops the base path of
                // the URL it is resolved against.
                const logoUrl = new URL(
                    response.body.url,
                    Cypress.config("baseUrl"),
                ).href;

                cy.request({ url: logoUrl }).then((imageResponse) => {
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

        it("Rejects an oversized body with no image data with 400, not 413", () => {
            // The 413 branch exists only for the case where PHP discarded the
            // request body because it was bigger than the server accepts. A body
            // that actually arrived but carries no imgBase64 is a malformed
            // request, so Content-Length alone must never turn it into a size
            // error. 3 MB is comfortably over the 2 MB upload_max_filesize the
            // project's PHP images set, and far under their 2 GB post_max_size,
            // so the body is fully received and parsed.
            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_URL,
                { notImgBase64: "a".repeat(3 * 1024 * 1024) },
                400,
            ).then((response) => {
                expect(response.body).to.have.property("success", false);
                expect(response.body.message).to.include("Missing image data");
            });
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

        // The unlink() failure path (HTTP 500) has no test: making the stored
        // logo undeletable requires changing ownership or mount flags of the
        // Images directory, which the web user cannot do, and any half-measure
        // (chmod 0444 on the file) is still deletable because the *directory*
        // stays writable. The idempotent path below covers delete() returning
        // true with no logo present.
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
