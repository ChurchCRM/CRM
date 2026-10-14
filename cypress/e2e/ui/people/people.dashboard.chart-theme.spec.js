/// <reference types="cypress" />

const STYLE_URL = "/api/user/1/setting/ui.style";
const APEX_DEFAULT_LABEL = "rgb(55, 61, 63)";
const AXIS_LABEL = "#age-stats-bar .apexcharts-xaxis-label";

describe("People Dashboard — Age Distribution chart theme (#10569)", () => {
    let savedStyle;

    before(() => {
        cy.setupAdminSession();
        cy.request(STYLE_URL).then(({ body }) => {
            savedStyle = body.value;
        });
    });

    beforeEach(() => cy.setupAdminSession());

    after(() => {
        cy.setupAdminSession();
        cy.request("POST", STYLE_URL, { value: savedStyle });
    });

    it("draws axis labels in the body colour in dark mode and follows a switch to light", () => {
        cy.request("POST", STYLE_URL, { value: "dark" });
        cy.visit("/people/dashboard");
        cy.get("html").should("have.attr", "data-bs-theme", "dark");

        cy.window().then((win) => {
            const bodyColor = win.getComputedStyle(win.document.body).color;
            expect(bodyColor).not.to.eq(APEX_DEFAULT_LABEL);
            cy.get(AXIS_LABEL)
                .first()
                .should(($label) => {
                    expect(win.getComputedStyle($label[0]).fill).to.eq(bodyColor);
                });
        });

        cy.window().its("CRM.theme").invoke("setMode", "default");
        cy.get("html").should("not.have.attr", "data-bs-theme");
        cy.window().then((win) => {
            cy.get(AXIS_LABEL)
                .first()
                .should(($label) => {
                    expect(win.getComputedStyle($label[0]).fill).to.eq(APEX_DEFAULT_LABEL);
                });
        });
    });
});
