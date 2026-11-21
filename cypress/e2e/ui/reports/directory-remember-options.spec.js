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

// Use JSON to avoid PHP's max_input_vars limit for large regression-test payloads.
const directoryJsonPost = (classifications) => ({
    method: "POST",
    url: "people/reports/directory",
    headers: {
        "content-type": "application/json",
    },
    body: {
        sDirClassifications: classifications,
        GroupID: [],
        sDirRoleHead: ["1"],
        sDirRoleSpouse: ["2"],
        sDirRoleChild: ["3"],
        bDirAddress: "1",
        bDirWedding: "1",
        bDirFamilyPhone: "1",
        bDirPersonalPhone: "1",
        bDirPersonalEmail: "1",
        sDirLayout: "booklet",
        NumCols: "3",
        PageSize: "legal",
        FSize: "14",
        bDirUseTitlePage: "1",
        Submit: "Create Directory",
    },
    encoding: "binary",
    failOnStatusCode: false,
});

const getUnselectedOptionValue = (selector) =>
    cy.get(selector).then(($select) => {
        const option = Array.from($select[0].options).find(
            (item) => !item.selected,
        );

        expect(option, "an unselected option").to.exist;

        return option.value;
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

    it("restores long classification lists without truncation (#11587)", () => {
    cy.visit("DirectoryReports.php");

        getUnselectedOptionValue('select[name="sDirClassifications[]"]').then(
            (sentinelId) => {
                // 164 ten-digit IDs create 41 chunks.
                // The valid option is appended at the end to detect truncation.
                const classificationIds = [
                    ...Array.from({ length: 164 }, (_, index) =>
                        String(1000000000 + index),
                    ),
                    String(sentinelId),
                ];

                return cy
                    .request(directoryJsonPost(classificationIds))
                    .then((response) => {
                        expect(response.status).to.eq(200);
                        expect(response.headers["content-type"]).to.include(
                            "application/pdf",
                        );
                    })
                    .then(() => cy.visit("DirectoryReports.php"))
                    .then(() => {
                        cy.get(
                            `select[name="sDirClassifications[]"] option[value="${sentinelId}"]`,
                        ).should("be.selected");
                    });
            },
        );
    });

    it("rejects classification lists exceeding 1000 chunks (#11587)", () => {
        // Four ten-digit IDs fit in each 50-character chunk.
        // 5004 IDs therefore exceed the 1000-chunk limit.
        const oversizedClassifications = Array.from(
            { length: 5004 },
            (_, index) => String(1500000000 + index),
        );

        cy.request(directoryJsonPost(oversizedClassifications)).then(
            (response) => {
                expect(response.status).to.eq(400);
                expect(response.headers["content-type"]).to.include("text/plain");
                expect(response.body).to.eq(
                    "The selected directory report options exceed the maximum supported size.",
                );
            },
        );
    });
});

