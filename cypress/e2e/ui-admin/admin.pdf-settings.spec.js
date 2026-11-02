/// <reference types="cypress" />

const key = "sPDFTempDir";
const endpoint = `/admin/api/system/config/${key}`;
let original;

describe("Reporting temporary directory", () => {
    before(() => cy.getSystemConfig(key).then((value) => { original = value; }));
    after(() => cy.restoreSystemConfig(key, original));
    beforeEach(() => cy.setupAdminSession());

    it("saves a shared directory and restores the system default when cleared", () => {
        cy.intercept("POST", `**${endpoint}`).as("savePdfDirectory");
        cy.visit("/admin/");
        cy.get(`#reportingSettings input[name='${key}']`).should("not.be.disabled").clear().type("/tmp").blur();
        cy.wait("@savePdfDirectory").its("response.statusCode").should("eq", 200);
        cy.reload();
        cy.get(`#reportingSettings input[name='${key}']`).should("have.value", "/tmp").clear().blur();
        cy.wait("@savePdfDirectory").its("response.statusCode").should("eq", 200);
        cy.getSystemConfig(key).should("eq", "");
    });

    it("rejects relative and missing directories without changing the saved value", () => {
        cy.getSystemConfig(key).then((before) => {
            cy.makePrivateAdminAPICall("POST", endpoint, { value: "relative/path" }, 400);
            cy.makePrivateAdminAPICall("POST", endpoint, { value: "/churchcrm-missing-pdf-directory/invalid" }, 400);
            cy.getSystemConfig(key).should("eq", before);
        });
    });
});
