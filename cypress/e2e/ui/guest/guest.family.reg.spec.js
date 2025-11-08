/// <reference types="cypress" />

describe("Family Reg", () => {
    it("Adam Family Registration", () => {
        cy.visit("external/register/");
        cy.contains("Main St. Cathedral");

        // Step 1: Family Info
        cy.get("#familyName").type("Adam");
        cy.get("#familyAddress1").clear().type("742 Evergreen Terrace");
        cy.get("#familyCity").clear().type("Springfield");
        cy.get("#familyZip").type("99777");
        cy.get("#familyHomePhone").type("(555) 123-4567");
        cy.get("#family-info-next").click();

        // Step 2: Members - First member card is auto-created (Homer)
        cy.get("#member-first-name-1").type("Homer");
        cy.get("#member-last-name-1").clear().type("Adam");
        cy.get("#member-email-1").type("homer.adam@example.com");
        // Set birthday for Homer (05/12/1956)
        cy.setDatePickerValue("#member-birthday-1", "05/12/1956");

        // Add second member (Marge)
        cy.get("#add-member-btn").click();
        cy.get("#member-first-name-2").type("Marge");
        cy.get("#member-last-name-2").type("Adam");
        cy.get("#member-email-2").type("marge.adam@example.com");
        // Set birthday for Marge (10/01/1957)
        cy.setDatePickerValue("#member-birthday-2", "10/01/1957");

        // Add third member (Bart)
        cy.get("#add-member-btn").click();
        cy.get("#member-first-name-3").type("Bart");
        cy.get("#member-last-name-3").type("Adam");
        cy.get("#member-email-3").type("bart.adam@example.com");
        // Set birthday for Bart (04/01/1980)
        cy.setDatePickerValue("#member-birthday-3", "04/01/1980");

        // Add fourth member (Lisa)
        cy.get("#add-member-btn").click();
        cy.get("#member-first-name-4").type("Lisa");
        cy.get("#member-last-name-4").type("Adam");
        cy.get("#member-email-4").type("lisa.adam@example.com");
        // Set birthday for Lisa (05/09/1981)
        cy.setDatePickerValue("#member-birthday-4", "05/09/1981");

        // Add fifth member (Maggie)
        cy.get("#add-member-btn").click();
        cy.get("#member-first-name-5").type("Maggie");
        cy.get("#member-last-name-5").type("Adam");
        cy.get("#member-email-5").type("maggie.adam@example.com");
        // Set birthday for Maggie (01/14/1984)
        cy.setDatePickerValue("#member-birthday-5", "01/14/1984");

        // Proceed to review
        cy.get("#members-next").click();

        // Step 3: Review and Submit
        cy.get("#submit-registration").click();

        // Verify success and redirect
        cy.get(".bootbox-body").should("contain", "Thank you for registering your family");
        cy.get(".btn-default").click();
        cy.url().should("contain", "external/register/");
    });
});
