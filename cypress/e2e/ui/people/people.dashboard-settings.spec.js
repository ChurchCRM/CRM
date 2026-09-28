/// <reference types="cypress" />

/**
 * People Dashboard — Settings Panel tests (#9994).
 *
 * Boolean settings render as Yes/No radio pills (value 1 / 0), not a checkbox.
 * Values are applied after GET /admin/api/system/config/{name}.
 * Toggle must restore bEnableSelfRegistration so later specs keep the seed default.
 */

function setSelfReg(value) {
    cy.makePrivateAdminAPICall(
        "POST",
        "admin/api/system/config/bEnableSelfRegistration",
        { value },
    );
}

describe("People Dashboard — Settings Panel", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("/people/dashboard");
        cy.window().its("CRM.localesLoaded").should("eq", true);
        cy.contains("button", "People Settings", { timeout: 10000 }).should("be.visible");
    });

    after(() => {
        setSelfReg("0");
    });

    it("shows the People Settings button in the page header for admins", () => {
        cy.contains("button", "People Settings").should("be.visible");
        cy.get(".fa-sliders").should("exist");
    });

    it("expands the People Settings panel when the button is clicked", () => {
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 10000 }).should("be.visible");
    });

    it("toggles Self Registration setting and saves successfully", () => {
        cy.intercept("GET", "**/admin/api/system/config/bEnableSelfRegistration").as(
            "loadSelfReg",
        );
        cy.intercept("POST", "**/admin/api/system/config/bEnableSelfRegistration").as(
            "saveConfig",
        );

        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 10000 }).should("be.visible");
        cy.wait("@loadSelfReg");

        const yesRadio = "#peopleSettings input[name='bEnableSelfRegistration'][value='1']";
        const noRadio = "#peopleSettings input[name='bEnableSelfRegistration'][value='0']";

        cy.get(`${yesRadio}, ${noRadio}`).filter(":checked").should("have.length", 1);

        cy.get(yesRadio).then(($yes) => {
            const currentlyEnabled = $yes.is(":checked");
            const target = currentlyEnabled ? noRadio : yesRadio;
            const expectedValue = currentlyEnabled ? "0" : "1";

            cy.get(target).click({ force: true });
            cy.get(target).should("be.checked");

            cy.get("#peopleSettings #settingsPanelSaveBtn").should("not.be.disabled").click();

            cy.wait("@saveConfig").then((interception) => {
                expect(interception.response.statusCode).to.eq(200);
                const body = interception.request.body;
                const sent =
                    body && typeof body === "object" && "value" in body
                        ? String(body.value)
                        : String(body);
                expect(sent).to.contain(expectedValue);
            });
        });
    });

    it("shows the Self Registration help tooltip on the setting", () => {
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 10000 }).should("be.visible");

        cy.get("#peopleSettings [title*='self-register'], #peopleSettings [data-bs-original-title*='self-register']").should(
            "exist",
        );
    });

    it("displays both settings in the People Settings panel", () => {
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 10000 }).should("be.visible");

        cy.get("#peopleSettings").should("contain.text", "Self Registration");
        cy.get("#peopleSettings").should("contain.text", "Hide Deceased from Directory");
    });

    it("collapses the panel when closed", () => {
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 10000 }).should("be.visible");

        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show").should("not.exist");
    });
});
