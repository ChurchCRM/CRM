/// <reference types="cypress" />

import { buildBlankPng } from "../../../../support/synthetic-png";

/**
 * API tests for the church logo endpoints (issue #9717)
 *
 * Covers:
 *   GET    /api/system/church-logo
 *   POST   /api/system/church-logo
 *   DELETE /api/system/church-logo
 *
 * The logo is global state (a file at src/Images/church-logo.png), so every
 * test deletes it up front, and whatever logo the instance had before the suite
 * ran is put back at the end: a stray logo would change the sidebar and login
 * page for every other spec, and wiping one would reset the branding of a
 * customised development instance.
 */

// 1x1 transparent PNG — the canonical synthetic image payload used by the
// person photo specs.
const VALID_PNG_DATA_URI =
    "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";

const LOGO_URL = "/api/system/church-logo";
const LOGO_CONFIG_URL = "/admin/api/system/config/sChurchLogoURL";

// Source-pixel budget the server enforces before decoding
// (ImageSupportUtils::MAX_SOURCE_PIXELS = 50 000 000). The boundary images
// below are 1-bit PNGs of a few KB, so only their dimensions are in play.
const AT_PIXEL_BUDGET = [10000, 5000]; // exactly 50 000 000 pixels
const ONE_ROW_OVER_PIXEL_BUDGET = [10000, 5001]; // 50 010 000 pixels
const FAR_OVER_PIXEL_BUDGET = [12000, 12000]; // 144 000 000 pixels, ~18 KB on the wire

/**
 * The API returns a root-relative URL that already carries the install's base
 * path ("/churchcrm/Images/..." on a subdirectory install). Cypress prefixes
 * relative URLs with baseUrl, which would double the base path there, so
 * resolve against the origin instead — a root-relative path drops the base
 * path of the URL it is resolved against.
 */
function resolveLogoUrl(url) {
    return new URL(url, Cypress.config("baseUrl")).href;
}

/** Fetch the served logo bytes (base64) so two versions can be compared. */
function fetchLogoBytes(url) {
    return cy
        .request({ url: resolveLogoUrl(url), encoding: "base64" })
        .then((imageResponse) => {
            expect(imageResponse.status).to.equal(200);
            expect(imageResponse.headers["content-type"]).to.include(
                "image/png",
            );
            return imageResponse.body;
        });
}

describe("API Private Admin Church Logo", () => {
    // Base64 of the logo the instance had before this suite ran, or null.
    let originalLogoBase64 = null;

    before(() => {
        cy.makePrivateAdminAPICall("GET", LOGO_URL, null, 200).then(
            (response) => {
                if (!response.body.hasCustomLogo) {
                    return;
                }
                fetchLogoBytes(response.body.url).then((bytes) => {
                    originalLogoBase64 = bytes;
                });
            },
        );
    });

    beforeEach(() => {
        // Clean up anything a prior (possibly failed) run left behind. DELETE is
        // idempotent, so 200 is the only expected status.
        cy.makePrivateAdminAPICall("DELETE", LOGO_URL, null, 200);
    });

    after(() => {
        // Leave the instance as it was found: put the original logo back (it is
        // already a <= 1200x400 PNG, so the re-encode is lossless in practice)
        // or make sure none is left behind.
        if (originalLogoBase64 !== null) {
            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_URL,
                { imgBase64: `data:image/png;base64,${originalLogoBase64}` },
                200,
            );
        } else {
            cy.makePrivateAdminAPICall("DELETE", LOGO_URL, null, 200);
        }
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
                        "churchcrm-logo-ink-blue.svg",
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
                        /\/Images\/churchcrm-logo-ink-blue\.svg$/,
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
                    // Content-hash cache-busting token so a re-upload is
                    // picked up.
                    expect(response.body.url).to.match(
                        /church-logo\.png\?v=[0-9a-f]+$/,
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
                fetchLogoBytes(response.body.url);
            });
        });

        it("Changes the cache-busting URL when, and only when, the content changes", () => {
            // The version token is a hash of the stored bytes, not the mtime:
            // two replacements inside the same second still get distinct URLs,
            // and re-uploading identical content gets the identical URL back.
            let firstUrl;
            let secondUrl;

            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_URL,
                { imgBase64: VALID_PNG_DATA_URI },
                200,
            ).then((response) => {
                firstUrl = response.body.url;
                expect(firstUrl).to.match(/church-logo\.png\?v=[0-9a-f]+$/);
            });

            cy.wrap(buildBlankPng(2, 2)).then((otherPng) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    LOGO_URL,
                    { imgBase64: otherPng },
                    200,
                ).then((response) => {
                    secondUrl = response.body.url;
                    expect(secondUrl).to.match(
                        /church-logo\.png\?v=[0-9a-f]+$/,
                    );
                    expect(secondUrl).to.not.equal(firstUrl);
                });
            });

            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_URL,
                { imgBase64: VALID_PNG_DATA_URI },
                200,
            ).then((response) => {
                expect(response.body.url).to.equal(firstUrl);
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

    describe("Decode pixel budget", () => {
        it("Rejects a small file with huge dimensions with 413 before decoding it", () => {
            // ~18 KB on the wire but 144 million pixels once decoded: the
            // compressed-size limit alone would let this through and GD would
            // allocate a raster of several hundred MB. The server must read the
            // header, refuse, and never reach imagecreatefromstring().
            cy.wrap(buildBlankPng(...FAR_OVER_PIXEL_BUDGET)).then((hugePng) => {
                expect(hugePng.length).to.be.lessThan(64 * 1024);

                cy.makePrivateAdminAPICall(
                    "POST",
                    LOGO_URL,
                    { imgBase64: hugePng },
                    413,
                ).then((response) => {
                    expect(response.body).to.have.property("success", false);
                    expect(response.body.message).to.include("12000x12000");
                    expect(response.body.message).to.include("pixels");
                });
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

        it("Accepts an image exactly at the budget and rejects one a single row over", () => {
            // Ordinary large camera images (24 MP is 6000x4000) sit well inside
            // the budget; this pins the exact edge so a change to the constant
            // is a deliberate one.
            cy.wrap(buildBlankPng(...AT_PIXEL_BUDGET)).then((atBudgetPng) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    LOGO_URL,
                    { imgBase64: atBudgetPng },
                    200,
                ).then((response) => {
                    expect(response.body).to.have.property("success", true);
                    expect(response.body).to.have.property(
                        "hasCustomLogo",
                        true,
                    );
                });
            });

            cy.wrap(buildBlankPng(...ONE_ROW_OVER_PIXEL_BUDGET)).then(
                (overBudgetPng) => {
                    cy.makePrivateAdminAPICall(
                        "POST",
                        LOGO_URL,
                        { imgBase64: overBudgetPng },
                        413,
                    ).then((response) => {
                        expect(response.body).to.have.property(
                            "success",
                            false,
                        );
                        expect(response.body.message).to.include(
                            "10000x5001",
                        );
                    });
                },
            );
        });
    });

    describe("Replacing a stored logo", () => {
        it("Keeps the existing logo byte-for-byte when a replacement is rejected", () => {
            let storedUrl;
            let storedBytes;

            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_URL,
                { imgBase64: VALID_PNG_DATA_URI },
                200,
            ).then((response) => {
                storedUrl = response.body.url;
                fetchLogoBytes(storedUrl).then((bytes) => {
                    storedBytes = bytes;
                });
            });

            // Over the pixel budget: refused before decoding.
            cy.wrap(buildBlankPng(...FAR_OVER_PIXEL_BUDGET)).then((hugePng) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    LOGO_URL,
                    { imgBase64: hugePng },
                    413,
                );
            });

            // Not an image at all: refused by the MIME allow-list.
            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_URL,
                { imgBase64: "data:text/plain;base64,SGVsbG8gV29ybGQ=" },
                400,
            );

            cy.makePrivateAdminAPICall("GET", LOGO_URL, null, 200).then(
                (response) => {
                    expect(response.body).to.have.property(
                        "hasCustomLogo",
                        true,
                    );
                    // Same content hash, so the same URL...
                    expect(response.body.url).to.equal(storedUrl);
                    // ...serving the same bytes.
                    fetchLogoBytes(response.body.url).then((bytes) => {
                        expect(bytes).to.equal(storedBytes);
                    });
                },
            );
        });

        it("Serves only the completed new image after a successful replacement", () => {
            let firstUrl;
            let firstBytes;

            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_URL,
                { imgBase64: VALID_PNG_DATA_URI },
                200,
            ).then((response) => {
                firstUrl = response.body.url;
                fetchLogoBytes(firstUrl).then((bytes) => {
                    firstBytes = bytes;
                });
            });

            cy.wrap(buildBlankPng(120, 40)).then((replacementPng) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    LOGO_URL,
                    { imgBase64: replacementPng },
                    200,
                ).then((response) => {
                    expect(response.body).to.have.property(
                        "hasCustomLogo",
                        true,
                    );
                    expect(response.body.url).to.not.equal(firstUrl);

                    fetchLogoBytes(response.body.url).then((bytes) => {
                        expect(bytes).to.not.equal(firstBytes);
                        // A complete PNG: signature at the start, IEND at the end.
                        const png = Cypress.Buffer.from(bytes, "base64");
                        expect(png.subarray(0, 8).toString("hex")).to.equal(
                            "89504e470d0a1a0a",
                        );
                        expect(png.subarray(-8).toString("ascii")).to.include(
                            "IEND",
                        );
                    });
                });
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
                        "churchcrm-logo-ink-blue.svg",
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
