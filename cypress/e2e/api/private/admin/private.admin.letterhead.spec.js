/// <reference types="cypress" />

import { absoluteUrl } from "../../../../support/urls";

/**
 * API tests for the letterhead endpoints
 *
 *   GET / POST / DELETE /admin/api/system/letterhead
 *
 * The letterhead is global state (a file at src/Images/church-letterhead.png).
 * Every test starts without one, and the instance's own letterhead is put back
 * at the end.
 */

const VALID_PNG_DATA_URI =
    "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";

const LETTERHEAD_URL = "/admin/api/system/letterhead";

describe("API Private Admin Letterhead", () => {
    let originalBase64 = null;

    before(() => {
        cy.makePrivateAdminAPICall("GET", LETTERHEAD_URL, null, 200).then((response) => {
            if (response.body.hasLetterhead) {
                cy.request({ url: absoluteUrl(response.body.url), encoding: "base64" }).then((image) => {
                    originalBase64 = image.body;
                });
            }
        });
    });

    beforeEach(() => {
        cy.makePrivateAdminAPICall("DELETE", LETTERHEAD_URL, null, 200);
    });

    after(() => {
        if (originalBase64 !== null) {
            cy.makePrivateAdminAPICall("POST", LETTERHEAD_URL, { imgBase64: `data:image/png;base64,${originalBase64}` }, 200);
        } else {
            cy.makePrivateAdminAPICall("DELETE", LETTERHEAD_URL, null, 200);
        }
    });

    it("reports no letterhead when none is uploaded", () => {
        cy.makePrivateAdminAPICall("GET", LETTERHEAD_URL, null, 200).then((response) => {
            expect(response.body).to.have.property("hasLetterhead", false);
            expect(response.body.url).to.equal(null);
        });
    });

    it("stores an uploaded PNG, serves it, and removes it", () => {
        cy.makePrivateAdminAPICall("POST", LETTERHEAD_URL, { imgBase64: VALID_PNG_DATA_URI }, 200).then((response) => {
            expect(response.body).to.have.property("hasLetterhead", true);
            expect(response.body.url).to.match(/church-letterhead\.png\?v=[0-9a-f]+$/);
            cy.request({ url: absoluteUrl(response.body.url), encoding: "base64" }).then((image) => {
                expect(image.status).to.eq(200);
                expect(image.headers["content-type"]).to.include("image/png");
            });
        });

        cy.makePrivateAdminAPICall("DELETE", LETTERHEAD_URL, null, 200).then((response) => {
            expect(response.body).to.have.property("hasLetterhead", false);
        });
        cy.makePrivateAdminAPICall("DELETE", LETTERHEAD_URL, null, 200);
    });

    it("refuses a non-image payload and a request without image data", () => {
        cy.makePrivateAdminAPICall("POST", LETTERHEAD_URL, { imgBase64: "data:text/plain;base64,SGVsbG8gV29ybGQ=" }, 400).then((response) => {
            expect(response.body).to.have.property("success", false);
        });
        cy.makePrivateAdminAPICall("POST", LETTERHEAD_URL, {}, 400);
        cy.makePrivateAdminAPICall("GET", LETTERHEAD_URL, null, 200).its("body.hasLetterhead").should("eq", false);
    });

    it("returns 401 when no API key is provided", () => {
        cy.clearCookies();
        cy.request({ method: "GET", url: LETTERHEAD_URL, failOnStatusCode: false, headers: { "content-type": "application/json" } })
            .its("status")
            .should("eq", 401);
    });
});
