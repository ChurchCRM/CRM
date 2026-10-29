/// <reference types="cypress" />

const ANGELS_CLASS_GROUP_ID = 1;
const photoBook = (groupIds) => `groups/sundayschool/reports/photo-book?GroupID=${groupIds}&FYID=`;

describe("Sunday School photo book", () => {
    it("a group manager gets the PDF", () => {
        cy.setupAdminSession();
        cy.request({ url: photoBook(ANGELS_CLASS_GROUP_ID), encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("an unknown class still returns a PDF instead of an error", () => {
        cy.setupAdminSession();
        cy.request({ url: photoBook(999999), encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
        });
    });

    it("a user without group management are sent to access denied", () => {
        cy.setupPersonaSession("finance-only");
        cy.request({ url: photoBook(ANGELS_CLASS_GROUP_ID), followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied");
        });
    });
});
