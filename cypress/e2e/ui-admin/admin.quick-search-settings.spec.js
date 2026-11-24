/// <reference types="cypress" />

describe("Administrator configures Quick Search", () => {
    const keys = ["Persons", "FamilyHOH", "FamilyCustomProperties", "Addresses", "Groups", "Deposits", "Payments", "CalendarEvents"].map((source) => `bSearchInclude${source}`);
    const family = "bSearchIncludeFamilies";
    const original = {};
    const modules = ["bEnabledFinance", "bEnabledEvents"];
    const field = (key) => `#quickSearchSettings input[name='${key}']`;

    before(() => {
        [...keys, family, ...modules].forEach((key) => cy.getSystemConfig(key).then((value) => { original[key] = value; }));
        modules.forEach((key) => cy.restoreSystemConfig(key, "1"));
    });

    beforeEach(() => cy.restoreSystemConfig(family, "1"));

    after(() => {
        [...keys, family, ...modules].forEach((key) => cy.restoreSystemConfig(key, original[key]));
    });

    it("saves every search source on Settings and offers no per-source limits", () => {
        const before = {};
        cy.freshAdminFormLogin({ enabled: true });
        cy.intercept("POST", "**/admin/api/system/config/*").as("saveSearch");
        cy.visit("/admin/settings");
        cy.get("#quickSearchSettings .settings-panel-fields").should("not.be.disabled");
        cy.get("#quickSearchSettings [name$='Max']").should("not.exist");
        keys.forEach((key) => {
            cy.get(field(key)).then(($switch) => {
                before[key] = $switch.is(":checked");
                cy.wrap($switch).click({ force: true });
            });
            cy.wait("@saveSearch").its("response.statusCode").should("eq", 200);
        });
        cy.reload();
        keys.forEach((key) => {
            cy.get(field(key)).should(($switch) => expect($switch.is(":checked")).to.eq(!before[key]));
        });
    });

    it("shows family-only options only while family search is on", () => {
        cy.freshAdminFormLogin({ enabled: true });
        cy.visit("/admin/settings");
        cy.get("#quickSearchSettings .settings-panel-fields").should("not.be.disabled");
        cy.get(field("bSearchIncludeFamilyHOH")).should("be.visible");
        cy.get(field(family)).click({ force: true });
        cy.get(field("bSearchIncludeFamilyHOH")).should("not.be.visible");
        cy.get(field("bSearchIncludeFamilyCustomProperties")).should("not.be.visible");
    });

    it("hides sources of modules that are off", () => {
        modules.forEach((key) => cy.restoreSystemConfig(key, "0"));
        cy.freshAdminFormLogin({ enabled: true });
        cy.visit("/admin/settings");
        cy.get("#quickSearchSettings .settings-panel-fields").should("not.be.disabled");
        ["Deposits", "Payments", "CalendarEvents"].forEach((source) => {
            cy.get(field(`bSearchInclude${source}`)).should("not.exist");
        });
    });
});
