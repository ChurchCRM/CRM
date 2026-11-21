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
const directoryJsonPost = (classifications, groups = []) => ({
    method: "POST",
    url: "people/reports/directory",
    headers: {
        "content-type": "application/json",
    },
    body: {
        sDirClassifications: classifications,
        GroupID: groups,
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

    it("preserves saved options when an oversized group list is submitted (#11587)", () => {
        cy.visit("DirectoryReports.php");

        cy.get('select[name="sDirClassifications[]"]').then(($select) => {
            const availableClassifications = Array.from($select[0].options).filter(
                (option) => !option.selected,
            );

            expect(availableClassifications.length).to.be.at.least(2);

            const previousClassification = String(availableClassifications[0].value);
            const changedClassification = String(availableClassifications[1].value);

            return cy.get('select[name="GroupID[]"]').then(($groupSelect) => {
                const previousGroupOption = Array.from($groupSelect[0].options).find(
                    (option) => !option.selected,
                );

                expect(previousGroupOption, "an available group option").to.exist;

                const previousGroup = String(previousGroupOption.value);

                // Save valid preferences that must survive the oversized request.
                return cy
                    .request(
                        directoryJsonPost(
                            [previousClassification],
                            [previousGroup],
                        ),
                    )
                    .then((response) => {
                        expect(response.status).to.eq(200);
                        expect(response.headers["content-type"]).to.include(
                            "application/pdf",
                        );
                    })
                    .then(() => {
                        // 804 ten-digit IDs require 201 chunks (four IDs per chunk).
                        const oversizedGroups = Array.from(
                            { length: 804 },
                            (_, index) => String(1500000000 + index),
                        );

                        // Change classifications while submitting an oversized group list.
                        return cy.request(
                            directoryJsonPost(
                                [changedClassification],
                                oversizedGroups,
                            ),
                        );
                    })
                    .then((response) => {
                        // Preference saving may fail, but directory generation must succeed.
                        expect(response.status).to.eq(200);
                        expect(response.headers["content-type"]).to.include(
                            "application/pdf",
                        );
                    })
                    .then(() => cy.visit("DirectoryReports.php"))
                    .then(() => {
                        // Neither the classification nor group preference may be partially saved.
                        cy.get(
                            `select[name="sDirClassifications[]"] option[value="${previousClassification}"]`,
                        ).should("be.selected");

                        cy.get(
                            `select[name="GroupID[]"] option[value="${previousGroup}"]`,
                        ).should("be.selected");

                        cy.get("#bDirBirthday").should("not.be.checked");
                        cy.get("#bExcludeInactive").should("not.be.checked");
                    });
            });
        });
    });
});

