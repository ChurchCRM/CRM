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
        cy.get('button[name="PersonSubmit"]').click();

        cy.url().should("contain", "PersonView.php").then((url) => {
            const personId = new URL(url).searchParams.get("PersonID");

            // Open Actions dropdown, then click Delete (use first() to avoid duplicate IDs)
            cy.get("#person-actions-dropdown").first().click();
            cy.get("#deletePersonBtn").first().click();
            // Modal may be covered in headless runs; force the click to ensure deletion
            cy.get(".bootbox-accept").first().click({ force: true });
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
        cy.get('button[name="FamilySubmit"]').click();

        cy.location("pathname").should("include", "/v2/family/").then((pathname) => {
            const familyId = pathname.split("/").pop();

            // Open Actions dropdown, then click Delete
            cy.get("#family-actions-dropdown").first().click();
            cy.get("#deleteFamilyBtn").first().click();
            cy.url().should("contain", "SelectDelete.php");

            // Intercept the API delete call so we can wait for it
            cy.intercept("DELETE", `**/api/family/${familyId}*`).as("deleteFamily");
            cy.get("#deleteFamilyAndMembersBtn").first().click();
            cy.wait("@deleteFamily").its("response.statusCode").should("eq", 200);
            cy.url({ timeout: 10000 }).should("contain", "v2/family");

            cy.visit(`v2/family/${familyId}`);
            cy.contains("Family not found");
        });
    });
});
