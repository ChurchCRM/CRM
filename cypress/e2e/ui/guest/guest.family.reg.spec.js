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

    it("Phone No Format Checkbox", () => {
        cy.visit("external/register/");

        // Step 1: Family Info - fill required fields and check family home phone functionality
        cy.get("#familyName").type("TestFamily");
        cy.get("#familyAddress1").clear().type("123 Test St");
        cy.get("#familyCity").clear().type("Testville");
        cy.get("#familyZip").type("12345");
        cy.get("#familyHomePhone").should("have.attr", "data-inputmask");

        // Check that the "No format" checkbox exists and is unchecked by default
        cy.get("#NoFormat_familyHomePhone").should("not.be.checked");

        // Type a formatted phone number
        cy.get("#familyHomePhone").clear().type("(555) 123-4567");
        cy.get("#familyHomePhone").invoke('val').should('match', /^\(555\) 123-4567/);

        // Check the "Allow any format" checkbox (click the label since it's covering the input)
        cy.get('label[for="NoFormat_familyHomePhone"]').click();

        // The mask should be removed, allowing free-form input
        cy.get("#familyHomePhone").clear().type("+44 20 7123 4567");
        cy.get("#familyHomePhone").should("have.value", "+44 20 7123 4567");

        // Uncheck the "Allow any format" checkbox - mask should be reapplied
        cy.get('label[for="NoFormat_familyHomePhone"]').click();

        // Clear and type a properly formatted US phone number
        cy.get("#familyHomePhone").clear().type("(555) 123-4567");
        cy.get("#familyHomePhone").invoke('val').should('match', /^\(555\) 123-4567/);

        // Navigate to members step
        cy.get("#family-info-next").click();
        
        // Wait for stepper transition and ensure add member button is ready
        cy.get("#step-members").should("be.visible");
        cy.get("#add-member-btn").should('be.visible').click();

        // Ensure the member phone field is visible
        cy.get("#member-phone-2").should("be.visible");

        // Check that member phone field starts with mobile type (default mask)
        cy.get("#member-phone-2").should("have.attr", "data-inputmask");

        // Type a mobile number (should use cell format)
        cy.get("#member-phone-2").clear().type("(555) 987-6543");
        cy.get("#member-phone-2").should("have.value", "(555) 987-6543");

        // Change phone type to home
        cy.get("#member-phone-type-2").select("home");

        // Clear and type home number (no extension expected)
        cy.get("#member-phone-2").clear().type("(555) 123-4567");
        cy.get("#member-phone-2").should("have.value", "(555) 123-4567");

        // Change phone type back to mobile
        cy.get("#member-phone-type-2").select("mobile");

        // Clear and type mobile number again
        cy.get("#member-phone-2").clear().type("(555) 987-6543");
        cy.get("#member-phone-2").should("have.value", "(555) 987-6543");

        // Check member phone "Allow any format" checkbox
        cy.get('label[for="member-phone-noformat-2"]').click();

        // Type international phone number (should work without mask)
        cy.get("#member-phone-2").clear().type("+44 20 7123 4567");
        cy.get("#member-phone-2").should("have.value", "+44 20 7123 4567");

        // Change phone type while "Allow any format" is checked (should stay free-form)
        cy.get("#member-phone-type-2").select("home");
        cy.get("#member-phone-2").should("have.value", "+44 20 7123 4567");

        // Uncheck member phone "Allow any format" - mask should be reapplied based on current type
        cy.get('label[for="member-phone-noformat-2"]').click();

        // Type home number (no extension expected)
        cy.get("#member-phone-2").clear().type("(555) 123-4567");
        cy.get("#member-phone-2").invoke('val').should('match', /^\(555\) 123-4567/);

        // Done - masks validated on visible fields above
    });
});
