/// <reference types="cypress" />

describe("Standard Family Activation", () => {
    beforeEach(() => {
       

        cy.intercept("POST", "/api/families/3/activate/true").as(
            "updateToActive",
        );
        cy.intercept("POST", "/api/families/3/activate/false").as(
            "updateToInActive",
        );

        cy.makePrivateUserAPICall(
            "POST",
            "/api/families/3/activate/true",
            "",
            200,
        );

         cy.setupStandardSession({ forceLogin: true });
    });

    it("Family activation flow", () => {
        cy.visit("v2/family");
        cy.contains("Active Family List");

        cy.visit("v2/family?mode=inactive");
        cy.contains("Inactive Family List").should("be.visible");
        cy.contains("Lewis").should("not.exist");

        cy.visit("v2/family/3");
        cy.contains("This Family is Deactivated").should("not.be.visible");
        cy.get("#activateDeactivate").click();
        cy.get(".bootbox-accept").should("be.visible").click();
        cy.wait("@updateToInActive");

        cy.visit("v2/family?mode=inactive");
        cy.contains("Lewis");

        cy.visit("v2/family/3");
        cy.contains("This Family is Deactivated").should("be.visible");
        cy.get("#activateDeactivate").click();
        cy.get(".bootbox-accept").should("be.visible").click();
        cy.wait("@updateToActive");

        cy.visit("v2/family?mode=inactive");
        cy.contains("Lewis").should("not.exist");
    });
});
