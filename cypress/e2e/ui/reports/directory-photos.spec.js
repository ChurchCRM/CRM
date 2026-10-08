/// <reference types="cypress" />

import { pdfDrawing } from "../../../support/pdf-text";
import { buildBlankPng } from "../../../support/synthetic-png";

/**
 * GitHub Issue #10416: a directory photo is drawn 20 mm wide at its own aspect
 * ratio, so a portrait photo is taller than 20 mm. The entry's text starts
 * below the photo, and the room kept before a page break is the photo as drawn.
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
            url: "Reports/DirectoryReport.php",
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
        it(`starts the entry's text below a portrait photo (${layout})`, () => {
            cartDirectory(layout)
                .then((response) => pdfDrawing(response.body))
                .then((drawing) => {
                    const at = drawing.findIndex((item) => item.image);
                    expect(at, "the family photo").to.be.greaterThan(0);
                    const photo = drawing[at];
                    expect(photo.height, "a portrait photo").to.be.greaterThan(photo.width);
                    expect(drawing[at - 1].text).to.equal("Kennedy, Bruce and Katie");

                    const firstLine = drawing[at + 1];
                    expect(firstLine.text).to.equal("9481 Wycliff Ave");
                    expect(firstLine.y + fontSize, "top of the first line under the photo").to.be.at.most(photo.y);
                });
        });
    });

    it("keeps an entry with a photo on the page it fits on", () => {
        cartDirectory("pages").then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(pageCount(response.body)).to.equal(1);
        });
    });
    it("keeps every entry above the bottom margin", () => {
        const bottomMargin = (27 * 72) / 25.4;
        directory("pages", ["0", "1", "2", "3", "4", "5", "6"].map((id) => ["sDirClassifications[]", id]))
            .then((response) => pdfDrawing(response.body))
            .then((drawing) => {
                const low = drawing.filter((item) => item.text && !item.text.startsWith("Page ") && item.y < bottomMargin);
                expect(low.map((item) => item.text), "lines below the 27 mm bottom margin").to.deep.equal([]);
            });
    });
});
