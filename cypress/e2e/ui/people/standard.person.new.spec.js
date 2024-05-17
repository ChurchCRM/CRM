const personEditorPath = "PersonEditor.php";
const personViewPath = "PersonView.php";

context("Standard Person", () => {
    const uniqueSeed = Date.now().toString();

    it("Add Full Person", () => {
        const name = "Bobby " + uniqueSeed;

        cy.loginStandard(personEditorPath);
        cy.get("#Gender").select("1");
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type("Hall");
        cy.get("#BirthMonth").select("12");
        cy.get("#BirthDay").select("21");
        cy.get("#BirthYear").clear().type("1950");
        cy.get("#Email").type("boby@example.com");
        cy.get("#Classification").select("1");
        cy.get("#PersonSaveButton").click();

        cy.url().should("contains", personViewPath);
        cy.contains(name);

        // make sure edit works
        cy.get('#EditPerson').click();

        cy.url().should("contains", personEditorPath);

        cy.get("#BirthYear").clear().type("1980");
        cy.get("#Email").clear().type(`bobby${uniqueSeed}@example.com`);
        cy.get("#PersonSaveButton").click();

        cy.url().should("contains", personViewPath);
        cy.contains(name);

    });

    it("Add Person only first and last name", () => {
        const name = "Robby " + uniqueSeed;

        cy.loginStandard(personEditorPath);
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type("Hall");
        cy.get("#PersonSaveButton").click();

        cy.url().should("contains", personViewPath);
        cy.contains(name);

        // make sure edit works
        cy.get('#EditPerson').click();

        cy.url().should("contains", personEditorPath);

        cy.get("#Email").clear().type(`robby${uniqueSeed}@example.com`);
        cy.get("#PersonSaveButton").click();

        cy.url().should("contains", personViewPath);
        cy.contains(name);
    });
});
