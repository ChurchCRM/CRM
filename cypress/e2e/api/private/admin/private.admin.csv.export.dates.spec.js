/// <reference types="cypress" />

/**
 * CSV export date filters read the configured date-picker format (#5966).
 * Seed people were all entered between 2009 and 2016, so an Enter Date From in 1900
 * keeps them and one in 2090 leaves only the header row.
 */
describe("API Private Admin — CSV export date filters", () => {
    let originalFormat;

    const pad = (n) => String(n).padStart(2, "0");
    const typed = (format, year) => (format === "d/m/Y" ? `01/12/${year}` : format === "m/d/Y" ? `12/01/${year}` : `${year}-12-01`);
    const today = (format) => {
        const now = new Date();
        const [d, m, y] = [pad(now.getDate()), pad(now.getMonth() + 1), now.getFullYear()];
        return format === "d/m/Y" ? `${d}/${m}/${y}` : format === "m/d/Y" ? `${m}/${d}/${y}` : `${y}-${m}-${d}`;
    };

    const exportRows = (format, enterFrom) =>
        cy
            .request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    FirstName: 1,
                    Source: "all",
                    Gender: 0,
                    Format: "Default",
                    Submit: "Create File",
                    EnterDate1: enterFrom,
                    EnterDate2: today(format),
                    MembershipDate2: today(format),
                    BirthDate2: today(format),
                    AnniversaryDate2: today(format),
                },
            })
            .then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
                expect(response.body).to.not.include("Fatal error");
                return response.body.trim().split("\n").length - 1;
            });

    before(() => {
        cy.setupAdminSession();
        cy.useChurchTimeZone();
        cy.getSystemConfig("sDatePickerFormat").then((value) => {
            originalFormat = value;
        });
    });

    after(() => {
        cy.setupAdminSession();
        cy.restoreSystemConfig("sDatePickerFormat", originalFormat);
        cy.useHostTimeZone();
    });

    ["m/d/Y", "d/m/Y", "Y-m-d"].forEach((format) => {
        it(`filters on Enter Date typed as ${format}`, () => {
            cy.setupAdminSession();
            cy.setSystemConfig("sDatePickerFormat", format);
            exportRows(format, typed(format, 1900)).should("be.greaterThan", 0);
            exportRows(format, typed(format, 2090)).should("eq", 0);
        });
    });
});
