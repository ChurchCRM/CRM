/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

const confirmLabelsText = () =>
    cy
        .request({
            url: "/people/labels/confirm?labeltype=Tractor&labelfont=Helvetica&labelfontsize=default&recipientnamingmethod=familyname",
            encoding: "binary",
        })
        .then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body);
        })
        .then((lines) => lines.join(" "));

describe("Family labels - pending self-registrations", () => {
    const familyName = `Pendlabel${Cypress._.random(0, 1e6)}`;
    let familyId;

    before(() => {
        cy.request({
            method: "POST",
            url: "/api/public/register/family",
            body: {
                Name: familyName,
                Address1: "1 Pending Way",
                City: "Testville",
                State: "TS",
                Country: "US",
                Zip: "00000",
                people: [{ firstName: "Cypress", lastName: familyName, gender: 1, role: 1 }],
            },
        }).then((response) => {
            familyId = response.body.Id;
            expect(familyId).to.be.greaterThan(0);
        });
    });

    beforeEach(() => cy.setupAdminSession());

    after(() => {
        if (familyId) {
            cy.cleanupFamilies([familyId]);
        }
    });

    it("leaves a family awaiting review off the family labels", () => {
        confirmLabelsText().should("not.include", familyName);
    });

    it("prints the family label once the family is approved", () => {
        cy.makePrivateAdminAPICall("POST", `/api/family/${familyId}/approve-review`, null, 200);
        cy.setupAdminSession();
        confirmLabelsText().should("include", familyName);
    });
});
