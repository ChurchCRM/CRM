describe("template spec", () => {
    it("filter-by-classification", () => {
        cy.loginAdmin("OptionManager.php?mode=classes");
        cy.get("#inactive4").uncheck();
        cy.get("#inactive5").uncheck();

        cy.reload();

        cy.get("#inactive1").should("not.be.checked");
        cy.get("#inactive2").should("not.be.checked");
        cy.get("#inactive3").should("not.be.checked");
        cy.get("#inactive4").should("not.be.checked");
        cy.get("#inactive5").should("not.be.checked");

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

        cy.get("#inactive1").should("not.be.checked");
        cy.get("#inactive2").should("not.be.checked");
        cy.get("#inactive3").should("not.be.checked");
        cy.get("#inactive4").should("be.checked");
        cy.get("#inactive5").should("not.be.checked");

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

        cy.get("#inactive1").should("not.be.checked");
        cy.get("#inactive2").should("not.be.checked");
        cy.get("#inactive3").should("not.be.checked");
        cy.get("#inactive4").should("be.checked");
        cy.get("#inactive5").should("be.checked");

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

        cy.get("#inactive1").should("not.be.checked");
        cy.get("#inactive2").should("not.be.checked");
        cy.get("#inactive3").should("not.be.checked");
        cy.get("#inactive4").should("not.be.checked");
        cy.get("#inactive5").should("not.be.checked");
    });
});
