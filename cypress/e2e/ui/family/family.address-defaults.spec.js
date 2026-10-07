/// <reference types="cypress" />

describe("Address defaults fall back to the church address", () => {
    const original = {};
    const keys = ["sDefaultCity", "sChurchCity", "sDefaultCountry", "sChurchCountry"];

    const setConfig = (name, value) =>
        cy.makePrivateAdminAPICall("POST", `admin/api/system/config/${name}`, { value }, 200);

    before(() => {
        cy.setupAdminSession();
        keys.forEach((key) => cy.getSystemConfig(key).then((value) => (original[key] = value)));
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    after(() => {
        cy.setupAdminSession();
        keys.forEach((key) => cy.restoreSystemConfig(key, original[key]));
    });

    it("pre-fills a new family with the church city when no default city is set", () => {
        setConfig("sDefaultCity", "");
        setConfig("sChurchCity", "Churchville");
        cy.visit("/FamilyEditor.php");
        cy.get("#City").should("have.value", "Churchville");
    });

    it("lets the configured default city override the church city", () => {
        setConfig("sDefaultCity", "Defaultown");
        setConfig("sChurchCity", "Churchville");
        cy.visit("/FamilyEditor.php");
        cy.get("#City").should("have.value", "Defaultown");
    });

    it("does not post the church address as hidden fields for a new person added to a family", () => {
        setConfig("sDefaultCity", "");
        setConfig("sChurchCity", "Churchville");
        cy.visit("/PersonEditor.php?FamilyID=1");
        cy.get("input[type=hidden][name=City]").should("have.value", "");
    });
    it("leaves the country blank when no country default is configured", () => {
        setConfig("sDefaultCountry", "");
        setConfig("sChurchCountry", "");
        cy.visit("/FamilyEditor.php");
        cy.get("#Country", { timeout: 10000 })
            .should("have.class", "tomselected")
            .find("option")
            .first()
            .should("have.value", "");
        cy.get("#Country").should("have.value", "");
        cy.get("#stateInputDiv").should("not.have.class", "d-none");
        cy.get("#stateOptionDiv").should("have.class", "d-none");
        cy.contains("Unable to load state list").should("not.exist");
    });
});
