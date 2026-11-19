/// <reference types="cypress" />

const columnTexts = (title) =>
    cy.window().then((win) => {
        const table = win.$("#members").DataTable();
        const index = table
            .columns()
            .header()
            .toArray()
            .findIndex((th) => th.textContent.trim() === title);
        expect(index, `${title} column`).to.be.greaterThan(-1);
        return table
            .column(index, { search: "applied" })
            .data()
            .toArray()
            .map((cell) => win.$("<div>").html(cell).text().trim());
    });

const visitPeopleList = () => {
    cy.visit("/people/list");
    cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
    cy.get(".filter-DeceasedStatus").siblings(".ts-wrapper").find(".ts-control .item").should("contain", "Living");
};

describe("People list exclude filters", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("hides the selected classification in exclude mode and keeps everyone else", () => {
        visitPeopleList();
        cy.get("#filter-exclude-Classification").should("not.be.checked");

        columnTexts("Classification").then((everyone) => {
            const members = everyone.filter((name) => name === "Member").length;
            expect(members).to.be.greaterThan(0);
            expect(everyone.length).to.be.greaterThan(members);

            cy.tomSelectByText(".filter-Classification", "Member");
            columnTexts("Classification").should((names) => {
                expect(names).to.have.length(members);
                expect(names.every((name) => name === "Member")).to.eq(true);
            });

            cy.get("#filter-exclude-Classification").check();
            columnTexts("Classification").should((names) => {
                expect(names).to.have.length(everyone.length - members);
                expect(names).not.to.include("Member");
            });

            cy.get("#filter-exclude-Classification").uncheck();
            columnTexts("Classification").should((names) => {
                expect(names).to.have.length(members);
                expect(names.every((name) => name === "Member")).to.eq(true);
            });
        });
    });

    it("excludes on a multi-value filter column", () => {
        visitPeopleList();
        columnTexts("Name").then((everyone) => {
            cy.tomSelectByText(".filter-Custom", "My Custom Drop Down List:My Custom Item 1");
            columnTexts("Name").should((names) => {
                expect(names).to.have.length(1);
                expect(names[0]).to.contain("Mark").and.contain("Smith");
            });

            cy.get("#filter-exclude-Custom").check();
            columnTexts("Name").should((names) => {
                expect(names).to.have.length(everyone.length - 1);
                expect(names.some((name) => name.includes("Mark") && name.includes("Smith"))).to.eq(false);
            });
        });
    });

    it("Clear Filter turns exclude mode off", () => {
        visitPeopleList();
        cy.tomSelectByText(".filter-Classification", "Member");
        cy.get("#filter-exclude-Classification").check();
        columnTexts("Classification").should("not.include", "Member");

        cy.get("#ClearFilter").click();
        cy.get("#filter-exclude-Classification").should("not.be.checked");
        columnTexts("Classification").should("include", "Member");
    });
});
