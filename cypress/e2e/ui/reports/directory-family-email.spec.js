/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

/**
 * Member sign-in row 11 (design §10.2): families have no email of their own, so
 * the printed directory has no "Family Email" option and prints each person's
 * own email only.
 */
describe("Directory report - email belongs to people", () => {
    const hartFamilyId = 2;

    const cartRequest = (method, body) =>
        cy.request({ method, url: "/api/cart/", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    after(() => {
        cy.setupAdminSession();
        cartRequest("DELETE", {});
    });

    it("offers no Family Email option", () => {
        cy.visit("DirectoryReports.php");
        cy.get("#bDirFamilyEmail").should("not.exist");
        cy.get("#bDirPersonalEmail").should("be.checked");
        cy.contains("label", "Family Email").should("not.exist");
    });

    it("prints each adult's own email", () => {
        cartRequest("DELETE", {});
        cartRequest("POST", { Family: hartFamilyId });
        cy.request({
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
                bDirPersonalEmail: "1",
                sDirLayout: "pages",
                NumCols: "1",
                PageSize: "letter",
                FSize: "10",
                Submit: "Create Directory",
            },
        })
            .then((response) => pdfText(response.body))
            .then((lines) => {
                const text = lines.join("\n");
                expect(text).to.include("constance.hart@example.com");
                expect(text).to.include("marion.sutton@example.com");
            });
    });
});
