/// <reference types="cypress" />

/**
 * GitHub Issue #8958: Booklet-style directory printing.
 *
 * The Directory Reports form gains a "Page Layout" choice. "Single pages"
 * keeps the portrait, one-page-per-sheet PDF. "Folded Booklet" lays each
 * page out as one half of a landscape sheet and imposes the pages two-up in
 * booklet order, padded to a multiple of four pages, so the sheets can be
 * printed double sided and folded in half.
 */

const directoryRequest = (layout, extra = {}) => ({
    method: "POST",
    url: "Reports/DirectoryReport.php",
    form: true,
    encoding: "binary",
    failOnStatusCode: false,
    body: {
        sDirLayout: layout,
        "sDirRoleHead[]": "1",
        "sDirRoleSpouse[]": "2",
        "sDirRoleChild[]": "3",
        bDirAddress: "1",
        bDirFamilyPhone: "1",
        bDirPersonalPhone: "1",
        bDirUseTitlePage: "1",
        NumCols: "2",
        PageSize: "letter",
        FSize: "10",
        Submit: "Create Directory",
        ...extra,
    },
});

const mediaBoxes = (pdf) => [...pdf.matchAll(/\/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/g)].map((m) => [Number(m[1]), Number(m[2])]);
const pageCount = (pdf) => Number(/\/Type \/Pages[\s\S]*?\/Count (\d+)/.exec(pdf)[1]);

describe("Directory report - page layout (#8958)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("offers a Page Layout choice with single pages selected by default", () => {
        cy.visit("DirectoryReports.php");
        cy.contains("label", "Page Layout");
        cy.get("#sDirLayoutPages").should("be.checked");
        cy.get("#sDirLayoutBooklet").should("not.be.checked");
        cy.contains("label", "Folded Booklet");
        cy.contains("label", "Columns per Page");
    });

    it("single pages layout still produces a portrait PDF", () => {
        cy.request(directoryRequest("pages")).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
            const boxes = mediaBoxes(response.body);
            expect(boxes.length).to.be.greaterThan(0);
            boxes.forEach(([w, h]) => {
                expect(w).to.be.closeTo(612, 0.5); // 8.5 in
                expect(h).to.be.closeTo(792, 0.5); // 11 in
            });
        });
    });

    it("folded booklet layout produces landscape sheets in an even count", () => {
        cy.request(directoryRequest("booklet")).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
            const boxes = mediaBoxes(response.body);
            expect(boxes.length).to.be.greaterThan(0);
            boxes.forEach(([w, h]) => {
                expect(w).to.be.closeTo(792, 0.5); // 11 in
                expect(h).to.be.closeTo(612, 0.5); // 8.5 in
            });
            // Pages are padded to a multiple of four, two per sheet.
            expect(pageCount(response.body) % 2).to.eq(0);
        });
    });

    it("folded booklet respects the paper size (A4 gives A5 pages on an A4 landscape sheet)", () => {
        cy.request(directoryRequest("booklet", { PageSize: "a4" })).then((response) => {
            expect(response.status).to.eq(200);
            mediaBoxes(response.body).forEach(([w, h]) => {
                expect(w).to.be.closeTo(841.89, 0.5);
                expect(h).to.be.closeTo(595.28, 0.5);
            });
        });
    });

    it("an unknown layout value falls back to single pages", () => {
        cy.request(directoryRequest("<script>")).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
            mediaBoxes(response.body).forEach(([w, h]) => {
                expect(w).to.be.closeTo(612, 0.5);
                expect(h).to.be.closeTo(792, 0.5);
            });
        });
    });
});
