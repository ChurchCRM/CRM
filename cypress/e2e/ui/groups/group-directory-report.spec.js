/// <reference types="cypress" />

const SEEDED_GROUP_ID = 1;
const groupDirectory = (body) => ({
    method: "POST",
    url: "groups/reports/group-directory",
    form: true,
    encoding: "binary",
    failOnStatusCode: false,
    followRedirect: false,
    body,
});

describe("Group directory report", () => {
    it("a group manager gets the PDF with the chosen fields", () => {
        cy.setupAdminSession();
        cy.request(groupDirectory({ GroupID: SEEDED_GROUP_ID, GroupRole: "", AddressEnable: "1", EmailEnable: "1", GroupRoleEnable: "1" })).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.headers["content-disposition"]).to.include("GroupDirectory-");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("an unknown group goes back to the report selector", () => {
        cy.setupAdminSession();
        cy.request(groupDirectory({ GroupID: 999999 })).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("groups/reports");
        });
    });

    it("a user without group management is sent to access denied", () => {
        cy.setupPersonaSession("finance-only");
        cy.request(groupDirectory({ GroupID: SEEDED_GROUP_ID, EmailEnable: "1" })).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied");
        });
    });
});
