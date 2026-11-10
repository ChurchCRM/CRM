/// <reference types="cypress" />

describe("An administrator configures Sunday School", () => {
    let originalSundaySchool;

    before(() => {
        cy.getSystemConfig("bEnabledSundaySchool").then((value) => { originalSundaySchool = value; });
    });

    after(() => cy.restoreSystemConfig("bEnabledSundaySchool", originalSundaySchool));

    beforeEach(() => cy.setupAdminSession());

    it("auto-saves the module toggle and keeps it after reload", () => {
        const next = originalSundaySchool === "1" ? "0" : "1";
        cy.intercept("POST", "**/admin/api/system/config/bEnabledSundaySchool").as("saveSundaySchool");
        cy.visit("/groups/dashboard");
        cy.contains("Group Settings").click();
        cy.get("#groupSettings .settings-panel-fields").should("not.be.disabled");
        cy.get("#groupSettings .settings-panel-save").should("not.exist");
        cy.get(`#groupSettings input[name='bEnabledSundaySchool'][value='${next}']`).parent("label").click();
        cy.wait("@saveSundaySchool").then(({ request, response }) => {
            expect(request.body.value).to.eq(next);
            expect(response.statusCode).to.eq(200);
        });
        cy.request("/admin/api/system/config/bEnabledSundaySchool").its("body.value").should("eq", next);
        cy.reload();
        cy.contains("Group Settings").click();
        cy.get("#groupSettings .settings-panel-fields").should("not.be.disabled");
        cy.get(`#groupSettings input[name='bEnabledSundaySchool'][value='${next}']`).should("be.checked");
    });
});
