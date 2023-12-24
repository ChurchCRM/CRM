context("Standard Person", () => {
    it("Add Full Person", () => {
        const uniqueSeed = Date.now().toString();
        const name = "Bobby " + uniqueSeed;
        cy.loginStandard("PersonEditor.php");
        cy.get("#Gender").select("1");
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type("Hall");
        cy.get("#BirthMonth").select("12");
        cy.get("#BirthDay").select("21");
        cy.get("#BirthYear").clear().type("1950");
        cy.get("#Email").type("boby@example.com");
        cy.get("#Classification").select("1");
        cy.get("#PersonSaveButton").click();

        cy.url().should("contains", "PersonView.php");
        cy.contains(name);
    });

    it("Add Person only first and last name", () => {
        const uniqueSeed = Date.now().toString();
        const name = "Robby " + uniqueSeed;
        cy.loginStandard("PersonEditor.php");
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type("Hall");
        cy.get("#PersonSaveButton").click();

        cy.url().should("contains", "PersonView.php");
        cy.contains(name);
    });
});
