/// <reference types="cypress" />

describe("Standard Family", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Delete Person", () => {
        // Create a temporary person with minimum required fields
        cy.visit("PersonEditor.php");
        cy.get("#FirstName").type("TempDel");
        cy.get("#LastName").type("TestPerson");
        cy.get("#Gender").select("1");
        cy.get("#Classification").select("1");
        cy.get(".fab-save").click();

        cy.url().should("contain", "PersonView.php").then((url) => {
            const personId = new URL(url).searchParams.get("PersonID");

            cy.get("#deletePersonBtn").first().click();
            cy.get(".bootbox-accept").should("be.visible").click();
            cy.url().should("contain", "v2/dashboard");

            cy.visit(`PersonView.php?PersonID=${personId}`);
            cy.contains("Person not found");
        });
    });

    it("Delete Family", () => {
        // Create a temporary family with minimum required fields
        cy.visit("FamilyEditor.php");
        cy.get("#FamilyName").type("TempDelFamily");
        cy.get('input[name="FirstName1"]').type("TempDel");
        cy.get('select[name="Classification1"]').select("1", { force: true });
        cy.get(".fab-save").click();

        cy.location("pathname").should("include", "/v2/family/").then((pathname) => {
            const familyId = pathname.split("/").pop();

            cy.get("#deleteFamilyBtn").click();
            cy.url().should("contain", "SelectDelete.php");
            cy.get("#deleteFamilyAndMembersBtn").click();
            cy.url().should("contain", "v2/family");

            cy.visit(`v2/family/${familyId}`);
            cy.contains("Family not found");
        });
    });
});
