/// <reference types="cypress" />

describe("Family Verification Page", () => {
    const familyId = 1;

    beforeEach(() => {
        cy.setupAdminSession();
        // Get a fresh verification URL for each test (token is consumed on use)
        cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}/verify/url`, null, 200).then((response) => {
            cy.wrap(response.body.url).as("verifyUrl");
        });
    });

    it("Should display family header and members", function() {
        cy.visit(this.verifyUrl);
        cy.get(".container-fluid").should("be.visible");
        cy.contains("Family Members").should("be.visible");
        cy.get(".col-lg-4").should("exist");
    });

    it("Should show confirmation modal with radio options", function() {
        cy.visit(this.verifyUrl);
        cy.get("#confirmVerifyBtn").click();
        cy.get("#confirm-Verify").should("be.visible");
        cy.get("#NoChanges").should("exist");
        cy.get("#UpdateNeeded").should("exist");
    });

    it("Should allow filling update information", function() {
        cy.visit(this.verifyUrl);
        cy.get("#confirmVerifyBtn").click();
        cy.get("#UpdateNeeded").click();
        // Type short text and verify textarea is functional
        cy.get("#confirm-info-data").should("be.visible").type("Update needed");
        cy.get("#confirm-info-data").invoke("val").should("include", "Update");
    });

    it("Should display modal footer buttons", function() {
        cy.visit(this.verifyUrl);
        cy.get("#confirmVerifyBtn").click();
        cy.get("#onlineVerifyCancelBtn").should("be.visible");
        cy.get("#onlineVerifyBtn").should("be.visible");
        cy.get("#onlineVerifySiteBtn").should("be.visible");
    });
});
