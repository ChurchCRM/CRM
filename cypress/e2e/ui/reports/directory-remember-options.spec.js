/// <reference types="cypress" />

/**
 * GitHub Issue #11465: remember last-used Directory Report Options per user.
 *
 * Only DirectoryReportService keys are stored (classifications, groups,
 * excludeInactive, head/spouse roles, show, customFieldOrders). Layout, title
 * page, paper, font, and child roles stay at their form defaults.
 */

const directoryPost = (extra = {}) => ({
    method: "POST",
    url: "people/reports/directory",
    form: true,
    encoding: "binary",
    failOnStatusCode: false,
    body: {
        "sDirClassifications[]": "0",
        "sDirRoleHead[]": "1",
        "sDirRoleSpouse[]": "2",
        "sDirRoleChild[]": "3",
        bDirAddress: "1",
        bDirWedding: "1",
        // birthday intentionally omitted so show.birthday restores as off
        bDirFamilyPhone: "1",
        bDirPersonalPhone: "1",
        bDirPersonalEmail: "1",
        // excludeInactive omitted so it restores unchecked
        sDirLayout: "booklet",
        NumCols: "3",
        PageSize: "legal",
        FSize: "14",
        bDirUseTitlePage: "1",
        Submit: "Create Directory",
        ...extra,
    },
});

describe("Directory report - remember options (#11465)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.clearDirectoryReportPrefs();
    });

    after(() => {
        cy.setupAdminSession();
        cy.clearDirectoryReportPrefs();
    });

    it("restores DirectoryReportService options after generate, not layout or title", () => {
        cy.request(directoryPost()).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
        });

        cy.visit("DirectoryReports.php");
        cy.get('select[name="sDirClassifications[]"]').invoke("val").should("deep.equal", ["0"]);
        cy.get("#bExcludeInactive").should("not.be.checked");
        cy.get("#bDirBirthday").should("not.be.checked");
        cy.get("#bDirAddress").should("be.checked");
        cy.get("#bDirWedding").should("be.checked");

        // Layout / paper / font / title page are outside the remembered set.
        cy.get("#sDirLayoutPages").should("be.checked");
        cy.get("#sDirLayoutBooklet").should("not.be.checked");
        cy.get("#NumCols2").should("be.checked");
        cy.get("#PageSizeletter").should("be.checked");
        cy.get("#FSize10").should("be.checked");
        cy.get("#bDirUseTitlePage").should("not.be.checked");
    });

    it("Reset Options clears stored preferences back to form defaults", () => {
        cy.request(directoryPost()).its("status").should("eq", 200);

        cy.visit("DirectoryReports.php");
        cy.get("#bExcludeInactive").should("not.be.checked");
        cy.get("#bDirBirthday").should("not.be.checked");

        cy.get("#resetDirectoryOptions").click();
        cy.location("pathname").should("match", /DirectoryReports\.php$/);

        cy.get("#bExcludeInactive").should("be.checked");
        cy.get("#bDirBirthday").should("be.checked");
        cy.get("#sDirLayoutPages").should("be.checked");
    });
});
