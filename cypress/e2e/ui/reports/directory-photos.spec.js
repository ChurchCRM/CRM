/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";
import { buildBlankPng } from "../../../support/synthetic-png";

/**
 * GitHub Issue #10416: a directory photo is drawn 20 mm wide at its own aspect
 * ratio, so a portrait photo is taller than 20 mm, and the entry's text starts
 * below it. mPDF flows the block, so the checks are the photo being embedded,
 * the text order and the entry staying on one page.
 */
describe("Directory report - photos (#10416)", () => {
    // Kennedy has no file under cypress/data/images/family, which the test
    // stack bind-mounts as Images/Family; the after hook deletes the upload.
    const familyId = 19;
    const fontSize = 10;

    const cartRequest = (method, body) =>
        cy.request({
            method,
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(body),
        });

    const directory = (layout, extra = []) =>
        cy.request({
            method: "POST",
            url: "people/reports/directory",
            form: true,
            encoding: "binary",
            body: new URLSearchParams([
                ...extra,
                ["sDirRoleHead[]", "1"],
                ["sDirRoleSpouse[]", "2"],
                ["sDirRoleChild[]", "3"],
                ["bDirAddress", "1"],
                ["bDirFamilyPhone", "1"],
                ["bDirPersonalPhone", "1"],
                ["bDirPhoto", "1"],
                ["sDirLayout", layout],
                ["NumCols", "1"],
                ["PageSize", "letter"],
                ["FSize", String(fontSize)],
                ["Submit", "Create Directory"],
            ]).toString(),
        });

    const cartDirectory = (layout) => directory(layout, [["cartdir", "M"]]);

    const pageCount = (pdf) => Number(/\/Type \/Pages[\s\S]*?\/Count (\d+)/.exec(pdf)[1]);

    before(() => {
        cy.setupAdminSession();
        cy.wrap(buildBlankPng(200, 300)).then((imgBase64) => {
            cy.request("POST", `/api/family/${familyId}/photo`, { imgBase64 });
        });
    });

    beforeEach(() => {
        cy.setupAdminSession();
        cartRequest("DELETE", {});
        cartRequest("POST", { Family: familyId });
    });

    after(() => {
        cy.setupAdminSession();
        cartRequest("DELETE", {});
        cy.request("DELETE", `/api/family/${familyId}/photo`);
    });

    ["pages", "booklet"].forEach((layout) => {
        it(`prints the family photo and then the entry's text (${layout})`, () => {
            cartDirectory(layout).then((response) => {
                expect(response.body).to.include("/Subtype /Image");
                return pdfText(response.body).then((lines) => {
                    const name = lines.indexOf("Kennedy, Bruce and Katie");
                    expect(name, "the family name").to.be.greaterThan(-1);
                    expect(lines[name + 1], "the first line under the photo").to.equal("9481 Wycliff Ave");
                });
            });
        });
    });

    it("keeps an entry with a photo on the page it fits on", () => {
        cartDirectory("pages").then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(pageCount(response.body)).to.equal(1);
        });
    });
});
