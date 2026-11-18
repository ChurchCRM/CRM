/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

/**
 * GitHub Issue #11645: the Church Directory cover prints the letterhead uploaded
 * in Report Settings, through the same resolution PDF letters use
 * (MpdfRenderer::graphicLetterhead()). With no upload and no bDirLetterHead path
 * (the test stack's default), the cover prints only the church details.
 */
describe("Directory report - uploaded letterhead (#11645)", () => {
    // 120x40 solid PNG, the same image admin.report-settings.spec.js uploads.
    const LETTERHEAD_PNG_BASE64 =
        "iVBORw0KGgoAAAANSUhEUgAAAHgAAAAoCAIAAAC6iKlyAAAAUklEQVR42u3QQQ0AAAgEoOtjJhsZ2hbOBxsJSPVwIApEi0a0aNEWRItGtGjRFkSLRrRo0YgWjWjRohEtGtGiRSNaNKJFi0a0aESLFo1o0Yj+ZwGy/zgts+HrHQAAAABJRU5ErkJggg==";
    const LETTERHEAD_API_URL = "/admin/api/system/letterhead";
    const CHURCH_NAME = "Letterhead Test Church";
    // Photos are off, so the only image the directory can hold is the cover letterhead.
    const familyId = 19;

    const removeStoredLetterhead = () => cy.makePrivateAdminAPICall("DELETE", LETTERHEAD_API_URL, null, 200);
    const storeLetterhead = () =>
        cy.makePrivateAdminAPICall("POST", LETTERHEAD_API_URL, { imgBase64: `data:image/png;base64,${LETTERHEAD_PNG_BASE64}` }, 200);

    const cartRequest = (method, body) =>
        cy.request({
            method,
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(body),
        });

    // cy.request() with the API key replaces the PHP session's authentication, so sign in
    // with a fresh form login after the letterhead calls and before the session requests.
    const signInWithFamilyInCart = () => {
        cy.freshAdminFormLogin({ enabled: true });
        cartRequest("DELETE", {});
        cartRequest("POST", { Family: familyId });
    };

    const coverDirectory = () =>
        cy
            .request({
                method: "POST",
                url: "people/reports/directory",
                form: true,
                encoding: "binary",
                body: {
                    cartdir: "M",
                    "sDirRoleHead[]": "1",
                    "sDirRoleSpouse[]": "2",
                    "sDirRoleChild[]": "3",
                    bDirAddress: "1",
                    sDirLayout: "pages",
                    NumCols: "1",
                    PageSize: "letter",
                    FSize: "10",
                    bDirUseTitlePage: "1",
                    sChurchName: CHURCH_NAME,
                    sChurchAddress: "1 Letterhead Way",
                    Submit: "Create Directory",
                },
            })
            .then((response) => {
                expect(response.headers["content-type"]).to.include("application/pdf");
                return response.body;
            });

    const imageCount = (pdf) => (pdf.match(/\/Subtype \/Image/g) || []).length;

    afterEach(() => {
        removeStoredLetterhead();
    });

    after(() => {
        cy.freshAdminFormLogin({ enabled: true });
        cartRequest("DELETE", {});
    });

    it("prints only the church details on the cover when no letterhead is set", () => {
        removeStoredLetterhead();
        signInWithFamilyInCart();
        coverDirectory().then((pdf) => {
            expect(imageCount(pdf), "images on a cover with no letterhead").to.equal(0);
            return pdfText(pdf).then((lines) => {
                expect(lines, "the cover's church name").to.include(CHURCH_NAME);
                expect(lines, "the cover's church address").to.include("1 Letterhead Way");
            });
        });
    });

    it("prints the letterhead uploaded in Report Settings on the cover", () => {
        storeLetterhead();
        signInWithFamilyInCart();
        coverDirectory().then((pdf) => {
            expect(imageCount(pdf), "the uploaded letterhead image").to.equal(1);
            return pdfText(pdf).then((lines) => {
                expect(lines, "the cover's church name under the letterhead").to.include(CHURCH_NAME);
            });
        });
    });
});
