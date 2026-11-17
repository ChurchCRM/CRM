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

describe("Reporting temporary directory localization", () => {
    beforeEach(() => cy.setupLocaleAdminSession("pt_BR"));
    after(() => cy.setupLocaleAdminSession("en_US"));

    it("renders the Portuguese label and tooltip in both themes and responsive widths", () => {
        cy.visit("/admin/");

        cy.get("#reportingSettings").invoke("attr", "data-settings").then((json) => {
            const [setting] = JSON.parse(json);
            expect(setting.name).to.eq(key);
            expect(setting.label).to.eq("Diretório temporário de PDF");
            expect(setting.tooltip).to.include("Deixe em branco para usar o diretório temporário");
        });

        for (const theme of ["light", "dark"]) {
            for (const width of [1440, 820, 390]) {
                cy.viewport(width, 900);
                cy.get("html").invoke("attr", "data-bs-theme", theme);
                cy.get(`#reportingSettings label[for='${key}']`)
                    .should("contain.text", "Diretório temporário de PDF")
                    .and("be.visible");
                cy.get("#reportingSettings small.form-text")
                    .should("contain.text", "Deixe em branco para usar o diretório temporário")
                    .and("be.visible");
            }
        }
    });
});
