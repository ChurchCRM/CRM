/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

/**
 * The printed directory only includes custom fields the signed-in user may see.
 *
 * The form offers a checkbox for each visible field, but the report is a plain POST, so
 * a request can name any `bCustomN`. Seed custom fields: `bCustom1`/`bCustom2` ("Father
 * of confession", "Father of confession - Other") are administrator-only, and
 * `bCustom3` ("Highest Degree Received") is open to every user. Judy Cooper holds the
 * administrator-only value "Fr. Join Smith"; Sherri Gordon holds "Law School".
 */
const directoryText = () => {
    const body = new URLSearchParams();
    ["1", "2"].forEach((classification) => body.append("sDirClassifications[]", classification));
    body.append("sDirRoleHead[]", "1");
    body.append("sDirRoleSpouse[]", "2");
    body.append("sDirRoleChild[]", "3");
    body.append("bDirAddress", "1");
    body.append("NumCols", "2");
    body.append("PageSize", "letter");
    body.append("FSize", "10");
    body.append("Submit", "Create Directory");
    // Every custom field is requested, whether or not the form would have offered it.
    ["bCustom1", "bCustom2", "bCustom3", "bCustom4"].forEach((field) => body.append(field, "1"));

    return cy
        .request({
            method: "POST",
            url: "people/reports/directory",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString(),
            encoding: "binary",
        })
        .then((response) => pdfText(response.body))
        .then((lines) => lines.join(" "));
};

describe("Directory report - custom field security", () => {
    it("church-admin gets the administrator-only fields and the open ones", () => {
        cy.setupPersonaSession("church-admin");
        directoryText().then((text) => {
            expect(text).to.include("Father of confession");
            expect(text).to.include("Fr. Join Smith");
            expect(text).to.include("Law School");
        });
    });

    ["office-staff", "staff-all-but-admin"].forEach((persona) => {
        it(`${persona} gets the open field but not the administrator-only fields`, () => {
            cy.setupPersonaSession(persona);
            directoryText().then((text) => {
                expect(text).to.include("Highest Degree Received");
                expect(text).to.include("Law School");
                expect(text).to.not.include("Father of confession");
                expect(text).to.not.include("Fr. Join Smith");
            });
        });
    });
});
