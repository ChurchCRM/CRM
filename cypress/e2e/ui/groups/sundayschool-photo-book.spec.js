/// <reference types="cypress" />

const ANGELS_CLASS_GROUP_ID = 1;
const photoBook = (groupIds) => `groups/sundayschool/reports/photo-book?GroupID=${groupIds}`;

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

    it("a user without group management is sent to access denied", () => {
        cy.setupPersonaSession("finance-only");
        cy.request({ url: photoBook(ANGELS_CLASS_GROUP_ID), followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied");
        });
    });

    it("without a class it makes the book for every Sunday School class", () => {
        cy.setupAdminSession();
        cy.request({ url: "groups/sundayschool/reports/photo-book", encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
        });
    });

    it("the class list shortcut leads to the class list report", () => {
        cy.setupAdminSession();
        cy.request({ url: "groups/sundayschool/reports/class-list", followRedirect: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("Reports/ClassList.php?GroupID=");
        });
    });

    it("the class page Print button opens that class's photo book", () => {
        cy.setupAdminSession();
        cy.visit("/groups/sundayschool/class/1");
        cy.get("#printClass").should("have.attr", "href").and("include", "reports/photo-book?GroupID=1");
    });
});
