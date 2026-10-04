/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

// Unassigned (0) is the classification a public registration lands in, so it is the one that would print it.
const directoryText = () =>
    cy
        .request({
            method: "POST",
            url: "Reports/DirectoryReport.php",
            form: true,
            encoding: "binary",
            body: {
                "sDirClassifications[]": "0",
                "sDirRoleHead[]": "1",
                "sDirRoleSpouse[]": "2",
                "sDirRoleChild[]": "3",
                bDirAddress: "1",
                NumCols: "2",
                PageSize: "letter",
                FSize: "10",
                Submit: "Create Directory",
            },
        })
        .then((response) => pdfText(response.body))
        .then((lines) => lines.join(" ").toLowerCase());

describe("Directory report - pending self-registrations", () => {
    const lastName = `Dirpending${Date.now()}`;
    let personId;

    before(() => {
        cy.request({
            method: "POST",
            url: "/api/public/register/person",
            body: { firstName: "Cypress", lastName, email: "dir-pending@example.com", gender: 1 },
        }).then((response) => {
            personId = response.body.Id;
        });
    });

    beforeEach(() => cy.setupAdminSession());

    after(() => {
        if (personId) {
            cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, [200, 404]);
        }
    });

    it("leaves a person awaiting review out of the printed directory", () => {
        directoryText().should("not.include", lastName.toLowerCase());
    });

    it("prints the person once they are approved", () => {
        cy.makePrivateAdminAPICall("POST", `/api/person/${personId}/approve-review`, null, 200);
        cy.setupAdminSession();
        directoryText().should("include", lastName.toLowerCase());
    });
});
