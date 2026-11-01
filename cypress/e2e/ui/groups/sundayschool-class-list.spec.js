/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

const ANGELS_CLASS_GROUP_ID = 1;
const CLASS_NAMES = ["Angels class", "Class 1-3", "Class 4-5", "Class 6-7"];
const classList = (groupIds) => `groups/sundayschool/reports/class-list?GroupID=${groupIds}&FYID=&pictures=1`;

describe("Sunday School class list", () => {
    it("a group manager gets the PDF", () => {
        cy.setupAdminSession();
        cy.request({ url: classList(ANGELS_CLASS_GROUP_ID), encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("prints the church header on every class's page", () => {
        cy.setupAdminSession();
        cy.request("/admin/api/system/config/sChurchName").its("body.value").then((churchName) => {
            cy.request({ url: classList("1,2,3,4"), encoding: "binary" })
                .then((response) => pdfText(response.body))
                .then((lines) => {
                    const classes = CLASS_NAMES.filter((name) => lines.some((line) => line.includes(name)));
                    expect(classes.length).to.be.greaterThan(1);
                    expect(lines.filter((line) => line.includes(churchName)).length).to.be.at.least(classes.length);
                });
        });
    });

    it("a user without group management is sent to access denied", () => {
        cy.setupPersonaSession("finance-only");
        cy.request({ url: classList(ANGELS_CLASS_GROUP_ID), followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied");
        });
    });
});
