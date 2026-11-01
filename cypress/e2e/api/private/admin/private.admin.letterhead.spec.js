/// <reference types="cypress" />

/**
 * API tests for the letterhead endpoints and the PDFs that print it
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
const ZERO_GIVERS = "/finance/reports/zero-givers/download";
const resolveUrl = (url) => new URL(url, Cypress.config("baseUrl")).href;

const countImages = (letterhead) =>
    cy
        .request({
            method: "POST",
            url: ZERO_GIVERS,
            form: true,
            body: { DateStart: "1990-01-01", DateEnd: "1990-12-31", letterhead, output: "pdf" },
            encoding: "binary",
        })
        .then((response) => (response.body.match(/\/Subtype \/Image/g) || []).length);

describe("API Private Admin Letterhead", () => {
    let originalBase64 = null;

    before(() => {
        cy.makePrivateAdminAPICall("GET", LETTERHEAD_URL, null, 200).then((response) => {
            if (response.body.hasLetterhead) {
                cy.request({ url: resolveUrl(response.body.url), encoding: "base64" }).then((image) => {
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
            cy.request({ url: resolveUrl(response.body.url), encoding: "base64" }).then((image) => {
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

    it("prints the uploaded image on a graphic-letterhead PDF and falls back to the address block when removed", () => {
        countImages("none").then((withoutImage) => {
            cy.makePrivateAdminAPICall("POST", LETTERHEAD_URL, { imgBase64: VALID_PNG_DATA_URI }, 200);
            countImages("graphic").then((withLetterhead) => {
                expect(withLetterhead).to.be.greaterThan(withoutImage);
            });
            cy.makePrivateAdminAPICall("DELETE", LETTERHEAD_URL, null, 200);
            countImages("address").then((addressImages) => {
                countImages("graphic").then((afterRemoval) => {
                    expect(afterRemoval).to.eq(addressImages);
                });
            });
        });
    });

    it("returns 401 when no API key is provided", () => {
        cy.clearCookies();
        cy.request({ method: "GET", url: LETTERHEAD_URL, failOnStatusCode: false, headers: { "content-type": "application/json" } })
            .its("status")
            .should("eq", 401);
    });
});
