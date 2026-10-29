/// <reference types="cypress" />

// Seed July birthdays give the Birthdays report rows to print.
const labels = (labelType, extra = "") =>
    `/people/labels?report=birthdays&month=7&labeltype=${labelType}&labelfont=Helvetica&labelfontsize=default&startrow=1&startcol=1&filetype=PDF${extra}`;

describe("Mailing label sheet", () => {
    it("a staff user gets a PDF sheet for each label stock", () => {
        cy.setupPersonaSession("zero-permission-staff");
        ["5160", "5164", "L7163"].forEach((labelType) => {
            cy.request({ url: labels(labelType), encoding: "binary" }).then((response) => {
                expect(response.headers["content-type"]).to.include("application/pdf");
                expect(response.body.slice(0, 5)).to.eq("%PDF-");
            });
        });
    });

    it("prints from a start row and column", () => {
        cy.setupAdminSession();
        cy.request({ url: labels("5160", "&startrow=3&startcol=2"), encoding: "binary" }).then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("rejects an unknown label stock", () => {
        cy.setupAdminSession();
        cy.request({ url: labels("nope"), failOnStatusCode: false }).its("status").should("eq", 400);
    });

    it("a self-service user is sent to access denied", () => {
        cy.setupPersonaSession("portal-only");
        cy.request({ url: labels("5160"), followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied");
        });
    });
});
