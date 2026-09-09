const personEditorPath = "PersonEditor.php";
const personViewPath = "people/view/";

describe("Standard Person", () => {
    const uniqueSeed = Date.now().toString();
    
    beforeEach(() => cy.setupStandardSession());

    it("Add Full Person", () => {
        const name = "Bobby " + uniqueSeed;

        cy.visit(personEditorPath);
        cy.get("#Gender").select("1");
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type("Hall");
        // Use zero-padded single-digit month/day values ("09") to guard against
        // regression of issue #9623 where filterInt() collapsed "09" to 0 via
        // FILTER_VALIDATE_INT treating the leading zero as an octal prefix.
        cy.get("#BirthMonth").select("09");
        cy.get("#BirthDay").select("09");
        cy.get("#BirthYear").clear().type("1950");
        cy.get("#Email").type("boby@example.com");
        cy.get("#Classification").select("1");
        // Click FAB save button
        cy.get('button[name="PersonSubmit"]').click();

        cy.url().should("contain", personViewPath);
        cy.contains(name);

        // Re-open editor and verify the zero-padded month/day were saved correctly.
        // With the bug the saved month and day are 0 and the selects show "-" (value "0").
        cy.contains('a.btn', 'Edit').first().click();
        cy.url().should("contain", personEditorPath);
        cy.get("#BirthMonth").should("have.value", "09");
        cy.get("#BirthDay").should("have.value", "09");

        cy.get("#BirthYear").clear().type("1980");
        cy.get("#Email").clear().type(`bobby${uniqueSeed}@example.com`);
        // Click FAB save button
        cy.get('button[name="PersonSubmit"]').click();

        cy.url().should("contain", personViewPath);
        cy.contains(name);

    });

    it("Add Person only first and last name", () => {
        const name = "Robby " + uniqueSeed;

        cy.visit(personEditorPath);
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type("Hall");
        // Click FAB save button
        cy.get('button[name="PersonSubmit"]').click();

        cy.url().should("contain", personViewPath);
        cy.contains(name);

        // make sure edit works - click Edit button in toolbar
        cy.contains('a.btn', 'Edit').first().click();

        cy.url().should("contain", personEditorPath);

        cy.get("#Email").clear().type(`robby${uniqueSeed}@example.com`);
        // Click FAB save button
        cy.get('button[name="PersonSubmit"]').click();

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
        // Use force:true because TomSelect covers the native select element
        cy.get("#familyId").select("-1", { force: true });
        
        // Select Head of Household role
        cy.get("#FamilyRole").select("1", { force: true });
        
        // Click FAB save button
        cy.get('button[name="PersonSubmit"]').click();

        // Should redirect to PersonView without error
        cy.url().should("contain", personViewPath);
        cy.contains(firstName);
    });
});
