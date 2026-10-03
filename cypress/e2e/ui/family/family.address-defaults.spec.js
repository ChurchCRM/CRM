/// <reference types="cypress" />

describe("Address defaults fall back to the church address", () => {
    const original = {};
    const keys = ["sDefaultCity", "sChurchCity"];

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

    it("pre-fills a new person with the church city when no default city is set", () => {
        setConfig("sDefaultCity", "");
        setConfig("sChurchCity", "Churchville");
        cy.visit("/PersonEditor.php");
        cy.get("#City").should("have.value", "Churchville");
    });
});
