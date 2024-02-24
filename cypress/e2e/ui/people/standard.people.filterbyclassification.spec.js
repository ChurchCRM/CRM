describe('template spec', () => {
  it('filter-by-classification', () => {
      cy.loginAdmin("OptionManager.php?mode=classes");
      cy.get("#inactive4").uncheck();
      cy.get("#inactive5").uncheck();
      cy.reload();
      cy.get("tr:nth-child(5) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(1) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(2) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(3) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(4) .TextColumn~ .TextColumn+ td input").should('not.be.checked');

      cy.visit("v2/people?familyActiveStatus=inactive");
      cy.get("#members_filter input").type("edwin.adams@example.com");
      cy.contains("No matching records found");

      cy.visit("v2/people?familyActiveStatus=all");
      cy.get("#members_filter input").type("edwin.adams@example.com");
      cy.contains("(564)-714-4633");

      cy.visit("v2/people");
      cy.get("#members_filter input").type("edwin.adams@example.com");
      cy.contains("(564)-714-4633");


      cy.visit("OptionManager.php?mode=classes");
      cy.get("#inactive4").check();
      cy.reload();
      cy.get("tr:nth-child(5) .TextColumn~ .TextColumn+ td input").should('be.checked');
      cy.get("tr:nth-child(1) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(2) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(3) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(4) .TextColumn~ .TextColumn+ td input").should('not.be.checked');

      cy.visit("v2/people?familyActiveStatus=inactive");
      cy.get("#members_filter input").type("edwin.adams@example.com");
      cy.contains("No matching records found");

      cy.visit("v2/people?familyActiveStatus=all");
      cy.get("#members_filter input").type("edwin.adams@example.com");
      cy.contains("(564)-714-4633");

      cy.visit("v2/people");
      cy.get("#members_filter input").type("edwin.adams@example.com");
      cy.contains("(564)-714-4633");

      cy.visit("OptionManager.php?mode=classes");
      cy.get("#inactive5").check();
      cy.reload();
      cy.get("tr:nth-child(4) .TextColumn~ .TextColumn+ td input").should('be.checked');
      cy.get("tr:nth-child(5) .TextColumn~ .TextColumn+ td input").should('be.checked');
      cy.get("tr:nth-child(1) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(2) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(3) .TextColumn~ .TextColumn+ td input").should('not.be.checked');

      cy.visit("v2/people?familyActiveStatus=inactive");
      cy.get("#members_filter input").type("edwin.adams@example.com");
      cy.contains("(564)-714-4633");

      cy.visit("v2/people?familyActiveStatus=all");
      cy.get("#members_filter input").type("edwin.adams@example.com");
      cy.contains("(564)-714-4633");

      cy.visit("v2/people");
      cy.get("#members_filter input").type("edwin.adams@example.com");
      cy.contains("No matching records found");

      cy.visit("OptionManager.php?mode=classes");
      cy.get("#inactive4").uncheck();
      cy.get("#inactive5").uncheck();
      cy.reload();
      cy.get("tr:nth-child(5) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(1) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(2) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(3) .TextColumn~ .TextColumn+ td input").should('not.be.checked');
      cy.get("tr:nth-child(4) .TextColumn~ .TextColumn+ td input").should('not.be.checked');

  })

})


