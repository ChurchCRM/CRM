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

    it("stores classification lists that exceed one user_settings value across chunks", () => {
        // 40 ids → CSV longer than VARCHAR(50); prefs must split and restore every id.
        const many = Array.from({ length: 40 }, (_, i) => String(i));
        const params = new URLSearchParams();
        many.forEach((id) => params.append("sDirClassifications[]", id));
        params.set("sDirRoleHead[]", "1");
        params.set("sDirRoleSpouse[]", "2");
        params.set("bDirAddress", "1");
        params.set("NumCols", "2");
        params.set("PageSize", "letter");
        params.set("FSize", "10");
        params.set("Submit", "Create Directory");

        cy.request({
            method: "POST",
            url: "people/reports/directory",
            headers: { "content-type": "application/x-www-form-urlencoded" },
            body: params.toString(),
            encoding: "binary",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
        });

        cy.makePrivateAdminAPICall("GET", "/api/user/1/setting/reports.directory.classifications", null, 200).then(
            ({ body }) => {
                expect(Number(body.value)).to.be.greaterThan(1);
            },
        );
        cy.makePrivateAdminAPICall("GET", "/api/user/1/setting/reports.directory.classifications.1", null, 200).then(
            ({ body }) => {
                expect(String(body.value).length).to.be.at.most(50);
                expect(String(body.value).length).to.be.greaterThan(0);
            },
        );
        cy.makePrivateAdminAPICall("GET", "/api/user/1/setting/reports.directory.classifications.2", null, 200).then(
            ({ body }) => {
                expect(String(body.value).length).to.be.at.most(50);
                expect(String(body.value).length).to.be.greaterThan(0);
            },
        );

        cy.visit("DirectoryReports.php");
        cy.get('select[name="sDirClassifications[]"]').then(($select) => {
            const available = [...$select.find("option")].map((opt) => opt.value);
            const expected = many.filter((id) => available.includes(id));
            cy.wrap($select).invoke("val").should("deep.equal", expected);
            expect(expected.length).to.be.greaterThan(1);
        });
    });
});
