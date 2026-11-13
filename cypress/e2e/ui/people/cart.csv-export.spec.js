/// <reference types="cypress" />

const defaultFields = ["name", "address", "email", "classification", "familyRole"];
const selectedFields = ["firstName", "birthDate", "mobilePhone"];

describe("Cart CSV export", () => {
    beforeEach(() => {
        cy.setupStandardSession();
        cy.request({
            method: "DELETE",
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: {},
        });
        cy.request({
            method: "POST",
            url: "/api/cart/",
            body: { Persons: [1] },
        }).then((response) => {
            expect(response.status).to.equal(200);
            expect(response.body.added).to.deep.equal([1]);
        });
        cy.request("/api/cart/").its("body.PeopleCart").should("deep.equal", [1]);
        cy.visit("/people/cart");
        cy.get("#cart-listing-table tbody tr").should("have.length", 1);
    });

    afterEach(() => {
        cy.request({
            method: "DELETE",
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: {},
        });
    });

    it("keeps the existing default columns and exports a selected field set", () => {
        cy.get("#cartCsvExportModal").should("exist");
        defaultFields.forEach((field) => {
            cy.get(`#cartCsvExportModal input[value="${field}"]`).should("be.checked");
        });

        const defaultFieldsQuery = defaultFields
            .map((field) => `fields%5B%5D=${encodeURIComponent(field)}`)
            .join("&");
        cy.request(`/people/cart/csv?${defaultFieldsQuery}`).then((response) => {
            expect(response.status).to.equal(200);
            expect(response.body.split(/\r?\n/)[0]).to.equal(
                'Name,Address,Email,Classification,"Family Role"'
            );
        });

        cy.get('.dt-buttons button[title="Export CSV"]').click();
        cy.get("#cartCsvExportModal").should("be.visible");
        defaultFields.forEach((field) => {
            cy.get(`#cartCsvExportModal input[value="${field}"]`)
                .parent()
                .click();
            cy.get(`#cartCsvExportModal input[value="${field}"]`).should("not.be.checked");
        });
        selectedFields.forEach((field) => {
            cy.get(`#cartCsvExportModal input[value="${field}"]`)
                .parent()
                .click();
            cy.get(`#cartCsvExportModal input[value="${field}"]`).should("be.checked");
        });
        cy.get("#cartCsvExportModal button[type=submit]").click();

        cy.readFile(`${Cypress.config("downloadsFolder")}/Cart.csv`, { timeout: 10000 }).then((csv) => {
            const rows = csv.trim().split(/\r?\n/);
            expect(rows[0]).to.equal('"First Name","Birth Date","Mobile Phone"');
            expect(rows[1].replace(/"/g, "").split(",")).to.deep.equal(["Church", "", ""]);
            expect(rows).to.have.length(2);
            expect(rows[0]).not.to.contain("Actions");
        });
    });
});
