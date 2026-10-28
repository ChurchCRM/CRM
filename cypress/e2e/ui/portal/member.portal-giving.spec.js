/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

const statementLines = (year) =>
    cy.get("#portal-giving form").then((form) =>
        cy.request({ url: form.attr("action"), qs: { year }, encoding: "binary" }).then((response) => {
            expect(response.status).to.equal(200);
            expect(response.headers["content-type"]).to.contain("application/pdf");
            return pdfText(response.body);
        }),
    );

describe("Member chooses a contribution statement year", () => {
    beforeEach(() => {
        cy.setupLoginSession("giving-member", "lena.black.editself.notes@example.com", "changeme");
    });

    it("lists family years and prints the household gifts for a year that has them", () => {
        cy.visit("/portal/giving");
        cy.get("#year option").then((options) => {
            const years = [...options].map((option) => Number(option.value));
            expect(years[0]).to.equal(new Date().getFullYear());
            expect(years.at(-1)).to.equal(2014);
            expect(years).to.have.length(years[0] - 2014 + 1);
        });
        cy.get('#year option[value="2018"]').should("have.text", "2018");
        cy.get('#year option[value="2025"]').should("contain.text", "No contributions");

        statementLines(2018).then((lines) => {
            const title = lines.indexOf("Contribution Statement");
            const letterhead = lines.slice(lines.indexOf("Main St. Cathedral"), title);
            expect(letterhead.slice(0, 4), "church details").to.deep.equal([
                "Main St. Cathedral",
                "123 Main St Kansas City, MO 64106 United States",
                "555 123 4234 · demo@churchcrm.io",
                "Tax ID: 47-3829156",
            ]);
            expect(letterhead.slice(4).join(""), "website and social links").to.equal(
                "https://churchcrm.io · X · YouTube · Facebook · Instagram",
            );
            expect(lines[title + 2], "household name").to.equal("Black");
            const gifts = lines.indexOf("AMOUNT") + 1;
            expect(lines.slice(gifts, gifts + 24), "family 20 payments on 2018-03-04").to.deep.equal([
                "March 04 2018", "Pledges", "CHECK #100", "$ 500.00",
                "March 04 2018", "New Building Fund", "CHECK #100", "$ 100.00",
                "March 04 2018", "Music Ministry", "CHECK #100", "$ 100.00",
                "March 04 2018", "Pledges", "CHECK #5532", "$ 25.00",
                "March 04 2018", "New Building Fund", "CHECK #5532", "$ 25.00",
                "March 04 2018", "Music Ministry", "CHECK", "$ 25.00",
            ]);
            const total = lines.indexOf("Total contributions", gifts);
            expect(lines.slice(total, total + 6), "statement totals").to.deep.equal([
                "Total contributions", "$ 775.00",
                "Goods and services", "$ 0.00",
                "Tax-deductible amount", "$ 775.00",
            ]);
        });

        statementLines(2025).then((lines) => {
            const name = lines.indexOf("Black");
            expect(lines[name + 4]).to.equal("No contributions were recorded for this period.");
        });
    });

    it("names the statement file with the year, the church and the family", () => {
        cy.visit("/portal/giving");
        cy.get("#portal-giving form").then((form) =>
            cy.request({ url: form.attr("action"), qs: { year: 2018 }, encoding: "binary" }).then((response) => {
                expect(response.headers["content-disposition"]).to.contain('filename="2018-Main-St-Cathedral-Black.pdf"');
            }),
        );
    });

    describe("with a check number and a non-deductible amount", () => {
        beforeEach(() => {
            cy.dbQuery("UPDATE pledge_plg SET plg_NonDeductible = 10, plg_CheckNo = 1234 WHERE plg_plgID = 10");
        });
        afterEach(() => {
            cy.dbQuery("UPDATE pledge_plg SET plg_NonDeductible = 0, plg_CheckNo = NULL WHERE plg_plgID = 10");
        });

        it("prints them and drops the no-goods disclosure", () => {
            cy.visit("/portal/giving");
            statementLines(2018).then((lines) => {
                expect(lines).to.include("Tax ID: 47-3829156");
                expect(lines).to.include("CHECK #1234");
                const total = lines.indexOf("Total contributions");
                expect(lines.slice(total, total + 6), "totals").to.deep.equal([
                    "Total contributions", "$ 775.00",
                    "Goods and services", "$ 10.00",
                    "Tax-deductible amount", "$ 765.00",
                ]);
                expect(lines.join(" ")).not.to.contain("No goods or services were provided");
            });
        });
    });
});
