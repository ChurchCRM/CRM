/// <reference types="cypress" />

describe("Member chooses a contribution statement year", () => {
    beforeEach(() => {
        cy.setupLoginSession("giving-member", "lena.black.editself.notes@example.com", "changeme");
    });

    it("lists family years and distinguishes years without contributions", () => {
        cy.visit("/portal/giving");
        cy.get("#year option").then((options) => {
            const years = [...options].map((option) => Number(option.value));
            expect(years[0]).to.equal(new Date().getFullYear());
            expect(years.at(-1)).to.equal(2014);
            expect(years).to.have.length(years[0] - 2014 + 1);
        });
        cy.get('#year option[value="2018"]').should("have.text", "2018");
        cy.get('#year option[value="2025"]').should("contain.text", "No contributions");
        cy.get("#year").select("2025");
        cy.get("#portal-giving form").then((form) => {
            cy.request({ url: form.attr("action"), qs: { year: 2025 }, encoding: "binary" }).then((response) => {
                expect(response.headers["content-type"]).to.contain("application/pdf");
                expect(response.body).to.match(/^%PDF-/);
            });
        });
    });
});
