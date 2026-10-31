/// <reference types="cypress" />

const ANGELS_CLASS_GROUP_ID = 1;
const classList = (groupIds) => `groups/sundayschool/reports/class-list?GroupID=${groupIds}&FYID=&pictures=1`;

describe("Sunday School class list", () => {
    it("a group manager gets the PDF", () => {
        cy.setupAdminSession();
        cy.request({ url: classList(ANGELS_CLASS_GROUP_ID), encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
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
