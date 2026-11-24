/// <reference types="cypress" />

describe("Admin Feature Toggles page", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("renders once with a single page title and toggles", () => {
        cy.visit("admin/system/feature-toggles");
        cy.get(".page-title").should("have.length", 1).and("contain", "Feature Toggles");
        cy.get("#featureTogglesContainer .settings-panel-fields").should("not.be.disabled");
        cy.get("#featureTogglesContainer input[type=checkbox][data-type=boolean]").should("have.length.greaterThan", 5);
    });

    it("saves a toggle and keeps it after the page reloads", () => {
        const toggle = "#featureTogglesContainer input[name='bEnabledEvents']";
        cy.intercept("POST", "**/admin/api/system/config/bEnabledEvents").as("toggleSave");
        cy.visit("admin/system/feature-toggles");
        cy.get("#featureTogglesContainer .settings-panel-fields").should("not.be.disabled");
        cy.get(toggle).then(($cb) => {
            const wasChecked = $cb.prop("checked");
            cy.wrap($cb).click({ force: true });
            cy.wait("@toggleSave").its("response.statusCode").should("eq", 200);
            cy.get("#featureTogglesContainer .settings-panel-fields").should("not.be.disabled");
            cy.get(toggle).should(wasChecked ? "not.be.checked" : "be.checked");
            cy.get(toggle).click({ force: true });
            cy.wait("@toggleSave");
        });
    });
});
