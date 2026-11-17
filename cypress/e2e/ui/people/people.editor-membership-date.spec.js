/// <reference types="cypress" />

describe("Person editor saves dates in the configured date-picker format (#5966)", () => {
    const personId = 2;
    let originalFormat;

    before(() => {
        cy.setupAdminSession();
        cy.getSystemConfig("sDatePickerFormat").then((value) => {
            originalFormat = value;
        });
    });

    after(() => {
        cy.setupAdminSession();
        cy.restoreSystemConfig("sDatePickerFormat", originalFormat);
    });

    [
        { format: "m/d/Y", typed: "03/22/2014" },
        { format: "d/m/Y", typed: "22/03/2014" },
        { format: "Y-m-d", typed: "2014-03-22" },
    ].forEach(({ format, typed }) => {
        it(`keeps ${typed} as 2014-03-22 with format ${format}`, () => {
            cy.setupAdminSession();
            cy.setSystemConfig("sDatePickerFormat", format);
            cy.visit(`/PersonEditor.php?PersonID=${personId}`);
            cy.get("#MembershipDate").invoke("val", typed);
            cy.get('button[name="PersonSubmit"]').click();
            cy.url().should("include", `/people/view/${personId}`);

            cy.visit(`/PersonEditor.php?PersonID=${personId}`);
            cy.get("#MembershipDate").should("have.value", typed);
        });
    });
});
