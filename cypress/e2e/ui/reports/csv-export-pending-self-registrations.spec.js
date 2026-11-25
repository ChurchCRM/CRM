/// <reference types="cypress" />

const exportCsv = () =>
    cy.request({
        method: "POST",
        url: "/admin/export/csv",
        form: true,
        body: { Format: "Default", Source: "filters" },
    });

describe("CSV export - pending self-registrations", () => {
    const lastName = `Csvpending${Date.now()}`;
    let personId;

    before(() => {
        cy.request({
            method: "POST",
            url: "/api/public/register/person",
            body: { firstName: "Cypress", lastName, email: "csv-pending@example.com", gender: 1 },
        }).then((response) => {
            personId = response.body.Id;
        });
    });

    beforeEach(() => cy.setupAdminSession());

    after(() => {
        if (personId) {
            cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, [200, 404]);
        }
    });

    it("leaves a person awaiting review out of the export", () => {
        exportCsv().then((response) => {
            expect(response.headers["content-type"]).to.include("text/csv");
            expect(response.body).not.to.include(lastName);
        });
    });

    it("exports the person once they are approved", () => {
        cy.makePrivateAdminAPICall("POST", `/api/person/${personId}/approve-review`, null, 200);
        cy.setupAdminSession();
        exportCsv().then((response) => {
            expect(response.body).to.include(lastName);
        });
    });
});
