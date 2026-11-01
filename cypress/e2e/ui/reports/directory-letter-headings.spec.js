/// <reference types="cypress" />

import { pdfDrawing, pdfText } from "../../../support/pdf-text";
import { buildBlankPng } from "../../../support/synthetic-png";

/**
 * GitHub Issue #10417: a letter heading is never the last thing in a column.
 * When the heading and the first entry under it do not both fit, both move.
 * With one column every column break is a page break, so the text order shows
 * where the heading went: after the next page's title, not before its footer.
 */
describe("Directory report - letter headings (#10417)", () => {
    // Hart fills most of the first page. Lewis's tall photo then leaves room for
    // the "L" heading but not for the entry under it. Neither family has a file
    // under cypress/data/images/family, which the test stack bind-mounts as
    // Images/Family; the after hook deletes the upload.
    const hartFamilyId = 2;
    const lewisFamilyId = 3;

    const cartRequest = (method, body) =>
        cy.request({
            method,
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(body),
        });

    const cartDirectory = (numCols) =>
        cy
            .request({
                method: "POST",
                url: "Reports/DirectoryReport.php",
                form: true,
                encoding: "binary",
                body: {
                    cartdir: "M",
                    "sDirRoleHead[]": "1",
                    "sDirRoleSpouse[]": "2",
                    "sDirRoleChild[]": "3",
                    bDirAddress: "1",
                    bDirWedding: "1",
                    bDirBirthday: "1",
                    bDirFamilyPhone: "1",
                    bDirFamilyWork: "1",
                    bDirFamilyCell: "1",
                    bDirFamilyEmail: "1",
                    bDirPersonalPhone: "1",
                    bDirPersonalWork: "1",
                    bDirPersonalCell: "1",
                    bDirPersonalEmail: "1",
                    bDirPersonalWorkEmail: "1",
                    bDirPhoto: "1",
                    sDirLayout: "pages",
                    NumCols: numCols,
                    PageSize: "letter",
                    FSize: "10",
                    Submit: "Create Directory",
                },
            })
            .then((response) => {
                expect(response.headers["content-type"]).to.include("application/pdf");
                return response.body;
            });

    before(() => {
        cy.setupAdminSession();
        cy.wrap(buildBlankPng(300, 600)).then((imgBase64) => {
            cy.request("POST", `/api/family/${lewisFamilyId}/photo`, { imgBase64 });
        });
    });

    beforeEach(() => {
        cy.setupAdminSession();
        cartRequest("DELETE", {});
        cartRequest("POST", { Family: hartFamilyId });
        cartRequest("POST", { Family: lewisFamilyId });
    });

    after(() => {
        cy.setupAdminSession();
        cartRequest("DELETE", {});
        cy.request("DELETE", `/api/family/${lewisFamilyId}/photo`);
    });

    it("moves a letter heading to the next page with the first entry under it", () => {
        cartDirectory("1").then((pdf) => pdfText(pdf)).then((lines) => {
            const pageTitle = lines[0];
            const heading = lines.indexOf("L");
            expect(heading, 'the "L" heading').to.be.greaterThan(0);
            expect(lines[heading + 1], "the line after the heading").to.equal("Lewis, Nathan and Vivan");
            expect(lines[heading - 1], "the line before the heading").to.equal(pageTitle);
        });
    });
    it("keeps a letter heading in the same column as its first entry", () => {
        const halfPage = 612 / 2;
        cartDirectory("2")
            .then((pdf) => pdfDrawing(pdf))
            .then((drawing) => {
                const heading = drawing.findIndex((item) => item.text === "L");
                expect(heading, 'the "L" heading').to.be.greaterThan(-1);
                const entry = drawing.slice(heading).find((item) => item.text === "Lewis, Nathan and Vivan");
                expect(Math.floor(entry.x / halfPage), "the entry's column").to.equal(Math.floor(drawing[heading].x / halfPage));
                expect(drawing[heading].y - entry.y, "the entry sits right below the heading").to.be.within(0, 30);
            });
    });
});
