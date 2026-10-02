/// <reference types="cypress" />

/**
 * GitHub Issue #10248: the Directory report's classification list.
 *
 * sDirClassifications only preselects the form. An empty selection used to print
 * everyone; it is now rejected. Unassigned (0) stays selectable on the form.
 */
const SELECT = 'select[name="sDirClassifications[]"]';

// One classification id as a plain string: an array value is form-encoded as a nested array, which PHP rejects.
const directoryPost = (classification) => ({
    method: "POST",
    url: "Reports/DirectoryReport.php",
    form: true,
    encoding: "binary",
    failOnStatusCode: false,
    body: {
        ...(classification === undefined ? {} : { "sDirClassifications[]": classification }),
        "sDirRoleHead[]": "1",
        "sDirRoleSpouse[]": "2",
        "sDirRoleChild[]": "3",
        bDirAddress: "1",
        NumCols: "2",
        PageSize: "letter",
        FSize: "10",
        Submit: "Create Directory",
    },
});

describe("Directory report - classification selection (#10248)", () => {
    let originalDirectory;

    before(() => {
        cy.getSystemConfig("sDirClassifications").then((value) => {
            originalDirectory = value;
        });
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    after(() => {
        cy.restoreSystemConfig("sDirClassifications", originalDirectory);
    });

    it("preselects the configured classifications", () => {
        cy.restoreSystemConfig("sDirClassifications", "1,2");
        cy.setupAdminSession();
        cy.visit("DirectoryReports.php");
        cy.get(SELECT).invoke("val").should("deep.equal", ["1", "2"]);
    });

    it("requires at least one classification on the form", () => {
        cy.visit("DirectoryReports.php");
        cy.get(SELECT).should("have.attr", "required");
    });

    it("rejects an empty classification selection instead of printing everyone", () => {
        cy.request(directoryPost()).then((response) => {
            expect(response.status).to.eq(400);
            expect(response.headers["content-type"] || "").to.not.include("application/pdf");
            expect(response.body).to.include("Select at least one classification");
        });
    });

    it("accepts Unassigned alone as a selection", () => {
        cy.request(directoryPost("0")).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
        });
    });
});
