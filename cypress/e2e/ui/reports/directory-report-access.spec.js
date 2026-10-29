/// <reference types="cypress" />

const directoryPost = () => ({
    method: "POST",
    url: "people/reports/directory",
    form: true,
    encoding: "binary",
    failOnStatusCode: false,
    followRedirect: false,
    body: {
        "sDirClassifications[]": "1",
        "sDirRoleHead[]": "1",
        "sDirRoleSpouse[]": "2",
        "sDirRoleChild[]": "3",
        bDirAddress: "1",
        bDirFamilyPhone: "1",
        NumCols: "2",
        PageSize: "letter",
        FSize: "10",
        Submit: "Create Directory",
    },
});

describe("Church directory report - access", () => {
    it("a staff user gets the PDF", () => {
        cy.setupPersonaSession("office-staff");
        cy.request(directoryPost()).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.headers["content-disposition"]).to.include("Directory-");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("an administrator gets the PDF", () => {
        cy.setupAdminSession();
        cy.request(directoryPost()).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("a self-service account does not get the directory", () => {
        cy.setupPersonaSession("portal-only");
        cy.request(directoryPost()).then((response) => {
            expect(response.headers["content-type"] || "").to.not.include("application/pdf");
            expect([302, 401, 403]).to.include(response.status);
        });
    });
});
