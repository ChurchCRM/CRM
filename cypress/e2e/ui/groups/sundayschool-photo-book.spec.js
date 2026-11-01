/// <reference types="cypress" />

const ANGELS_CLASS_GROUP_ID = 1;
const photoBook = (groupIds) => `groups/reports/photo-book?GroupID=${groupIds}`;

describe("Sunday School photo book", () => {
    it("a group manager gets the PDF", () => {
        cy.setupAdminSession();
        cy.request({ url: photoBook(ANGELS_CLASS_GROUP_ID), encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
            expect(response.headers["content-disposition"]).to.include("Angels-class");
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
        cy.request({ url: "groups/reports/photo-book", encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
        });
    });

    it("the class list shortcut returns the class list PDF for every class", () => {
        cy.setupAdminSession();
        cy.request({ url: "groups/sundayschool/reports/class-list", encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("the class page Reports menu offers the class list and photo book for that class", () => {
        cy.setupAdminSession();
        cy.visit("/groups/sundayschool/class/1");
        cy.get("#ssReportsDropdownBtn").click();
        cy.get("#reportClassList").should("be.visible").and("have.attr", "href").and("include", "reports/class-list?GroupID=1");
        cy.get("#reportPhotoBook").should("be.visible").and("have.attr", "href").and("include", "reports/photo-book?GroupID=1");
    });

    it("the attendance sheet form opens with only that class selected", () => {
        cy.setupAdminSession();
        cy.visit("/groups/sundayschool/reports?GroupID=1");
        cy.get('input[name="GroupID[]"][value="1"]').should("be.checked");
        cy.get('input[name="GroupID[]"]:checked').should("have.length", 1);
    });

    it("the class page reports do not open the browser print dialog", () => {
        cy.setupAdminSession();
        cy.visit("/groups/sundayschool/class/2", {
            onBeforeLoad(win) {
                cy.stub(win, "print").as("print");
            },
        });
        cy.get("#ssReportsDropdownBtn").click();
        cy.get("#reportPhotoBook").invoke("removeAttr", "target").then(($link) => {
            $link.on("click", (event) => event.preventDefault());
        });
        cy.get("#reportPhotoBook").click();
        cy.get("@print").should("not.have.been.called");
    });

    it("the class page opens the full group view from the About card", () => {
        cy.setupAdminSession();
        cy.visit("/groups/sundayschool/class/1");
        cy.get("#fullGroupView").should("be.visible").and("have.attr", "href").and("include", "/groups/view/1");
    });
});
