/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";
import { buildBlankPng } from "../../../support/synthetic-png";

/**
 * GitHub Issue #10417: a letter heading is never the last thing in a column.
 * The heading is printed in the same unbreakable block as the first entry under
 * it, so the text order shows the entry right after its heading.
 */
describe("Directory report - letter headings (#10417)", () => {
    // Lewis has a tall photo, which makes the heading and entry a tall block. Neither family has a file
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
                url: "people/reports/directory",
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

    it("keeps a letter heading with the first entry under it", () => {
        cartDirectory("1").then((pdf) => pdfText(pdf)).then((lines) => {
            const heading = lines.indexOf("L");
            expect(heading, 'the "L" heading').to.be.greaterThan(-1);
            expect(lines[heading + 1], "the line after the heading").to.equal("Lewis, Nathan and Vivan");
        });
    });
    it("keeps the heading and its entry together with two columns", () => {
        cartDirectory("2").then((pdf) => pdfText(pdf)).then((lines) => {
            const heading = lines.indexOf("L");
            expect(heading, 'the "L" heading').to.be.greaterThan(-1);
            expect(lines[heading + 1], "the line after the heading").to.equal("Lewis, Nathan and Vivan");
        });
    });
});
