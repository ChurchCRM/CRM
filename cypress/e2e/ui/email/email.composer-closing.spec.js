/// <reference types="cypress" />

describe("Composer closing", () => {
    let saved;

    before(() => cy.getSystemConfig("sConfirmSincerely").then((value) => (saved = value)));
    after(() => cy.restoreSystemConfig("sConfirmSincerely", saved));

    const signatureWith = (value) => {
        cy.makePrivateAdminAPICall("POST", "admin/api/system/config/sConfirmSincerely", { value }, 200);
        cy.setupAdminSession();
        cy.visit("/v2/dashboard");
        return cy.window().its("CRM.comm.emailSignature");
    };

    it("pre-fills the closing word only when the setting has one", () => {
        cy.getSystemConfig("sChurchName").then((church) => {
            signatureWith("Blessings").should("eq", `Blessings,\nChurch Admin\n${church}`);
            signatureWith("").should("eq", `Church Admin\n${church}`);
        });
    });
});
