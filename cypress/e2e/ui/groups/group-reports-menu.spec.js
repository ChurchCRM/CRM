/// <reference types="cypress" />

const SCOUTS_GROUP_ID = 7;

describe("Group page Reports menu", () => {
    it("offers the photo book and the group directory for that group", () => {
        cy.setupAdminSession();
        cy.visit(`/groups/view/${SCOUTS_GROUP_ID}`);
        cy.get("#groupReportsDropdownBtn").click();
        cy.get("#reportPhotoBook").should("have.attr", "href").and("include", `photo-book?GroupID=${SCOUTS_GROUP_ID}`);
        cy.get("#reportGroupDirectory").click();
        cy.location("pathname").should("include", "/groups/reports");
        cy.get('input[name="GroupID"]').should("have.value", String(SCOUTS_GROUP_ID));
    });

    it("the photo book works for a group that is not a Sunday School class", () => {
        cy.setupAdminSession();
        cy.request({ url: `groups/reports/photo-book?GroupID=${SCOUTS_GROUP_ID}`, encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
            expect(response.headers["content-disposition"]).to.include("Boys-Scouts");
        });
    });

    it("the page no longer has a browser Print button", () => {
        cy.setupAdminSession();
        cy.visit(`/groups/view/${SCOUTS_GROUP_ID}`);
        cy.get("#printGroup").should("not.exist");
    });
});
