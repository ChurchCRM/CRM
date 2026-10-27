/// <reference types="cypress" />

const STYLE_URL = "/api/user/1/setting/ui.style";
const APEX_DEFAULT_TEXT = "rgb(55, 61, 63)";
const AGE_AXIS_LABEL = "#age-stats-bar .apexcharts-xaxis-label";
const FUNDS_AXIS_LABEL = "#fund-bar .apexcharts-yaxis-label";

const bodyColour = (win) => win.getComputedStyle(win.document.body).color;

function expectColour(selector, property, colour) {
    cy.window().then((win) => {
        cy.get(selector)
            .first()
            .should(($el) => {
                expect(win.getComputedStyle($el[0])[property]).to.eq(colour(win));
            });
    });
}

describe("ApexCharts colours follow the theme (#10569)", () => {
    let savedStyle;

    before(() => {
        cy.setupAdminSession();
        cy.request(STYLE_URL).then(({ body }) => {
            savedStyle = body.value;
        });
    });

    beforeEach(() => {
        cy.setupAdminSession();
        cy.request("POST", STYLE_URL, { value: "dark" });
    });

    after(() => {
        cy.setupAdminSession();
        cy.request("POST", STYLE_URL, { value: savedStyle });
    });

    it("draws Age Distribution axis labels in the body colour in dark mode and follows a switch to light", () => {
        cy.visit("/people/dashboard");
        cy.get("html").should("have.attr", "data-bs-theme", "dark");
        expectColour(AGE_AXIS_LABEL, "fill", bodyColour);

        cy.window().its("CRM.theme").invoke("setMode", "default");
        cy.get("html").should("not.have.attr", "data-bs-theme");
        expectColour(AGE_AXIS_LABEL, "fill", () => APEX_DEFAULT_TEXT);
    });

    it("draws the deposit slip Funds chart labels in the body colour in dark mode", () => {
        cy.visit("/finance/deposit/5");
        cy.get("html").should("have.attr", "data-bs-theme", "dark");
        expectColour(FUNDS_AXIS_LABEL, "fill", bodyColour);
    });
});
