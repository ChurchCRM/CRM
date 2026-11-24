/// <reference types="cypress" />

describe("Administrator configures Quick Search", () => {
    const sources = ["Persons", "Addresses", "Families", "FamilyHOH", "Groups", "Deposits", "Payments", "CalendarEvents"];
    const keys = [...sources.flatMap((source) => [`bSearchInclude${source}`, `bSearchInclude${source}Max`]), "bSearchIncludeFamilyCustomProperties"];
    const original = {};
    const modules = ["bEnabledFinance", "bEnabledEvents"];

    before(() => {
        [...keys, ...modules].forEach((key) => cy.getSystemConfig(key).then((value) => { original[key] = value; }));
        modules.forEach((key) => cy.restoreSystemConfig(key, "1"));
    });

    after(() => {
        [...keys, ...modules].forEach((key) => cy.restoreSystemConfig(key, original[key]));
    });

    it("saves every source and its configurable limit together on the dashboard", () => {
        cy.freshAdminFormLogin({ enabled: true });
        cy.intercept("POST", "**/admin/api/system/config/*").as("saveSearch");
        cy.visit("/admin/");
        cy.get("#quickSearchSettings .settings-panel-fields").should("not.be.disabled");
        keys.forEach((key) => {
            if (key.endsWith("Max")) {
                cy.get(`#quickSearchSettings input[name='${key}']`).clear().type("7").blur();
            } else {
                cy.get(`#quickSearchSettings input[name='${key}']:not(:checked)`).click({ force: true });
            }
            cy.wait("@saveSearch").its("response.statusCode").should("eq", 200);
        });
        cy.reload();
        keys.forEach((key) => {
            cy.get(`#quickSearchSettings input[name='${key}']${key.endsWith("Max") ? "" : ":checked"}`)
                .should("have.value", key.endsWith("Max") ? "7" : original[key] === "1" ? "0" : "1");
        });
        cy.visit("/admin/system/feature-toggles");
        cy.get("#featureTogglesContainer").should("not.contain", "Search Settings");
        cy.get("[data-setting^='bSearchInclude']").should("not.exist");
    });

    it("hides module-specific controls without discarding their saved settings", () => {
        const limits = {};
        ["Deposits", "Payments", "CalendarEvents"].forEach((source) => {
            cy.getSystemConfig(`bSearchInclude${source}Max`).then((value) => { limits[source] = value; });
        });
        modules.forEach((key) => cy.restoreSystemConfig(key, "0"));
        cy.freshAdminFormLogin({ enabled: true });
        cy.visit("/admin/");
        cy.get("#quickSearchSettings .settings-panel-fields").should("not.be.disabled");
        ["Deposits", "Payments", "CalendarEvents"].forEach((source) => {
            cy.get(`#quickSearchSettings [name^='bSearchInclude${source}']`).should("not.exist");
            cy.getSystemConfig(`bSearchInclude${source}Max`).then((value) => expect(value).to.eq(limits[source]));
        });
    });
});
