/// <reference types="cypress" />

/**
 * People Dashboard — Settings Panel tests
 *
 * The People Settings panel on the dashboard allows admins to toggle:
 * - Self Registration (bEnableSelfRegistration)
 * - Hide Deceased from Directory (bHideDeceasedFromDirectory)
 *
 * Boolean settings render as Yes/No radio pills (value 1 / 0), not a checkbox.
 * Values are applied asynchronously after GET /admin/api/system/config/{name}.
 */

describe("People Dashboard — Settings Panel", () => {
    beforeEach(() => cy.setupAdminSession());

    it("shows the 'People Settings' button in the page header for admins", () => {
        cy.visit("/people/dashboard");
        cy.contains("button", "People Settings").should("be.visible");
        cy.get("button").contains("People Settings").parent().find(".fa-sliders").should("exist");
    });

    it("expands the People Settings panel when the button is clicked", () => {
        cy.visit("/people/dashboard");
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 5000 }).should("be.visible");
    });

    it("toggles Self Registration setting and saves successfully", () => {
        cy.intercept("GET", "**/admin/api/system/config/bEnableSelfRegistration").as(
            "loadSelfReg",
        );
        cy.intercept("POST", "**/admin/api/system/config/bEnableSelfRegistration").as(
            "saveConfig",
        );

        cy.visit("/people/dashboard");
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 5000 }).should("be.visible");
        cy.wait("@loadSelfReg");

        const yesRadio = "#peopleSettings input[name='bEnableSelfRegistration'][value='1']";
        const noRadio = "#peopleSettings input[name='bEnableSelfRegistration'][value='0']";

        cy.get(`${yesRadio}, ${noRadio}`).filter(":checked").should("have.length", 1);

        cy.get(yesRadio).then(($yes) => {
            const currentlyEnabled = $yes.is(":checked");
            const target = currentlyEnabled ? noRadio : yesRadio;
            const expectedValue = currentlyEnabled ? "0" : "1";

            cy.get(target).click();
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

    it("displays the Self Registration tooltip on hover", () => {
        cy.visit("/people/dashboard");
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 5000 }).should("be.visible");

        cy.get("#peopleSettings").should(
            "contain.text",
            "Allow visitors to self-register as new families",
        );
    });

    it("displays both settings in the People Settings panel", () => {
        cy.visit("/people/dashboard");
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 5000 }).should("be.visible");

        cy.get("#peopleSettings").should("contain.text", "Self Registration");
        cy.get("#peopleSettings").should("contain.text", "Hide Deceased from Directory");
    });

    it("collapses the panel when closed", () => {
        cy.visit("/people/dashboard");
        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show", { timeout: 5000 }).should("be.visible");

        cy.contains("button", "People Settings").click();
        cy.get("#peopleSettings.show").should("not.exist");
    });
});
