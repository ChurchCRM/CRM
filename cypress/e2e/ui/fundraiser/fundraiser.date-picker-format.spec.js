/// <reference types="cypress" />

/**
 * The fundraiser editor, list filter and view read and show dates in the configured
 * date-picker format (#5966). tony.wade (standard session) has ManageFundraisers.
 */
describe("Fundraiser dates follow the date-picker format", () => {
    let originalFormat;
    const title = "Date format fundraiser";

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
        { format: "d/m/Y", start: "22/03/2031", end: "25/03/2031", filter: "01/01/2031" },
        { format: "m/d/Y", start: "03/22/2031", end: "03/25/2031", filter: "01/01/2031" },
    ].forEach(({ format, start, end, filter }) => {
        it(`saves, reloads and filters ${start} under ${format}`, () => {
            cy.setupAdminSession();
            cy.setSystemConfig("sDatePickerFormat", format);
            cy.setupStandardSession();

            cy.visit("/fundraiser/editor");
            cy.get("#Title").type(`${title} ${format}`);
            cy.get("#Date").invoke("val", start);
            cy.get("#EndDate").invoke("val", end);
            cy.get('input[name="FundRaiserSubmit"]').click();
            cy.url().should("include", "/fundraiser/editor/");
            cy.get("#Date").should("have.value", start);
            cy.get("#EndDate").should("have.value", end);

            cy.visit(`/fundraiser/?dateStart=${encodeURIComponent(filter)}`);
            cy.contains(`${title} ${format}`);
        });
    });
});
