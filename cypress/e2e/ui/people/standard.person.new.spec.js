const personEditorPath = "PersonEditor.php";
const personViewPath = "PersonView.php";

describe("Standard Person", () => {
    const uniqueSeed = Date.now().toString();
    
    beforeEach(() => cy.setupStandardSession());

    it("Add Full Person", () => {
        const name = "Bobby " + uniqueSeed;

        cy.visit(personEditorPath);
        cy.get("#Gender").select("1");
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type("Hall");
        cy.get("#BirthMonth").select("12");
        cy.get("#BirthDay").select("21");
        cy.get("#BirthYear").clear().type("1950");
        cy.get("#Email").type("boby@example.com");
        cy.get("#Classification").select("1");
        // Click FAB save button
        cy.get(".fab-save").click();

        cy.url().should("contain", personViewPath);
        cy.contains(name);

        // make sure edit works - click FAB edit button
        cy.get('.fab-edit').click();

        cy.url().should("contain", personEditorPath);

        cy.get("#BirthYear").clear().type("1980");
        cy.get("#Email").clear().type(`bobby${uniqueSeed}@example.com`);
        // Click FAB save button
        cy.get(".fab-save").click();

        cy.url().should("contain", personViewPath);
        cy.contains(name);

    });

    it("Add Person only first and last name", () => {
        const name = "Robby " + uniqueSeed;

        cy.visit(personEditorPath);
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type("Hall");
        // Click FAB save button
        cy.get(".fab-save").click();

        cy.url().should("contain", personViewPath);
        cy.contains(name);

        // make sure edit works - click FAB edit button
        cy.get('.fab-edit').click();

        cy.url().should("contain", personEditorPath);

        cy.get("#Email").clear().type(`robby${uniqueSeed}@example.com`);
        // Click FAB save button
        cy.get(".fab-save").click();

        cy.url().should("contain", personViewPath);
        cy.contains(name);
    });

    it("Add Person with Create New Family option", () => {
        // Tests fix for issue #7895 - setWorkPhone error when creating new family
        const firstName = "NewFam " + uniqueSeed;
        const lastName = "TestFamily" + uniqueSeed;

        cy.visit(personEditorPath);
        cy.get("#FirstName").type(firstName);
        cy.get("#LastName").type(lastName);
        
        // Select "Create a new family (using last name)" option (-1)
        // Use force:true because Select2 covers the native select element
        cy.get("#familyId").select("-1", { force: true });
        
        // Select Head of Household role
        cy.get("#FamilyRole").select("1", { force: true });
        
        // Click FAB save button
        cy.get(".fab-save").click();

        // Should redirect to PersonView without error
        cy.url().should("contain", personViewPath);
        cy.contains(firstName);
    });
});
