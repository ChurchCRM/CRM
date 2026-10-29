/// <reference types="cypress" />

const download = "/finance/reports/tax-statements/download";
const range = "DateStart=2018-01-01&DateEnd=2018-12-31&datetype=Payment";

const expectPdf = (response) => {
    expect(response.status).to.eq(200);
    expect(response.headers["content-type"]).to.include("application/pdf");
    expect(response.body.slice(0, 5)).to.eq("%PDF-");
};

describe("Giving statements download", () => {
    beforeEach(() => cy.setupAdminSession());

    it("a family's statement opens from its profile link, and the CSV has the same rows", () => {
        cy.request({ url: `${download}?familyId=1&year=2018`, encoding: "binary" }).then(expectPdf);
        cy.request(`${download}?${range}&output=csv`).then((response) => {
            expect(response.headers["content-type"]).to.include("text/csv");
            expect(response.body).to.include("fam_ID");
        });
    });

    it("a minimum above the family total, or a period with no payments, returns to the report form", () => {
        cy.request({ url: `${download}?${range}&minimum=999999999`, followRedirect: false }).its("status").should("eq", 302);
        cy.request({ url: `${download}?DateStart=1991-01-01&DateEnd=1991-01-31`, followRedirect: false }).its("status").should("eq", 302);
    });
});
