context("CSVImport", () => {
  it("Verify CSV Import", () => {
    cy.loginAdmin("CSVImport.php");
    cy.get("input[type='file']").selectFile('cypress/data/test_import.csv')
    cy.get("input[type='submit']").click();
    cy.contains('Total number of rows in the CSV file:3');
    cy.get('#SelField0').select("Last Name",{force:true});
    cy.get('#SelField1').select("First Name",{force:true});
    cy.get('#SelField2').select("Address 1",{force:true});
    cy.get('#SelField3').select("City",{force:true});
    cy.get('#SelField4').select("State",{force:true});
    cy.get('#SelField5').select("Zip",{force:true});
    cy.get('#SelField6').select("Email",{force:true});
    cy.get('#SelField7').select("Birth Date",{force:true});
    cy.get('#SelField8').select("Home Phone",{force:true});
    // Now that we have mapped the right fields, do the import
    cy.get("input[type='submit']").click();
    cy.contains('Data import successful.');
  });
});

