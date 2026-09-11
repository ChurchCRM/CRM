/// <reference types="cypress" />

/**
 * Second family address + "This is the mailing address" flag (#9743).
 *
 * Covers the editor round trip, the family-view card label and the additive API
 * keys, and deletes every family it creates so the row count is unchanged.
 *
 * Ordering note: the API-key calls replace the browser's CRM session server-side,
 * so every cy.request-based assertion lives in the LAST test (and in `after`),
 * after all UI navigation is finished.
 */
describe("Family Second Address", () => {
    beforeEach(() => cy.setupStandardSession());

    const createdFamilyIds = [];

    const startNewFamily = (familyName) => {
        cy.visit("/FamilyEditor.php");
        cy.contains("Family Info");
        cy.get("#FamilyName").type(familyName);
        cy.get('input[name="Address1"]').type("11 Primary Street");
        cy.get('input[name="City"]').clear().type("Springfield");
        cy.get('select[name="State"]').select("IL", { force: true });
    };

    const rememberFamilyIdFromUrl = () =>
        cy.location("pathname").then((pathname) => {
            const id = Number(pathname.split("/").pop());
            createdFamilyIds.push(id);
            return id;
        });

    after(() => {
        // Row-count discipline: remove everything this spec created.
        createdFamilyIds.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/family/${id}`, null, 200);
        });
    });

    it("creates a family with a flagged second address, then unflags and clears it", () => {
        startNewFamily("MailingAddr" + Cypress._.random(0, 1e6));

        // The section starts collapsed for a brand-new family.
        cy.get("#secondAddressSection").should("not.have.class", "show");
        cy.get("#secondAddressToggle").click();
        cy.get("#secondAddressSection").should("have.class", "show");

        // The flag is disabled until a second address line or city exists.
        cy.get("#SecondIsMailing").should("be.disabled");
        // Commas are stripped on save, exactly as on the primary address.
        cy.get("#SecondAddress1").type("PO Box 4242, Suite B");
        cy.get("#SecondCity").type("Othertown");
        cy.get("#SecondIsMailing").should("not.be.disabled").check();

        cy.get('button[name="FamilySubmit"]').click();

        cy.location("pathname").should("include", "/people/family/");
        cy.get("#second-address-card").contains("Mailing Address");
        cy.get("#second-address-card").contains("PO Box 4242 Suite B");
        // The primary card is relabelled once a second address exists.
        cy.contains("Primary Address").should("exist");

        rememberFamilyIdFromUrl().then((familyId) => {
            // Unflag → the card becomes "Second Home".
            cy.visit(`/FamilyEditor.php?FamilyID=${familyId}`);
            cy.get("#secondAddressSection").should("have.class", "show");
            cy.get("#SecondAddress1").should("have.value", "PO Box 4242 Suite B");
            cy.get("#SecondIsMailing").uncheck();
            cy.get('button[name="FamilySubmit"]').click();

            cy.get("#second-address-card").contains("Second Home");
            cy.get("#second-address-card").should("not.contain", "Mailing Address");

            // Clear the second address → the card disappears entirely.
            cy.visit(`/FamilyEditor.php?FamilyID=${familyId}`);
            cy.get("#SecondAddress1").clear();
            cy.get("#SecondCity").clear();
            cy.get('button[name="FamilySubmit"]').click();

            cy.location("pathname").should("include", "/people/family/");
            cy.get("#second-address-card").should("not.exist");
            cy.contains("Primary Address").should("not.exist");
        });
    });

    it("rejects the mailing flag when no second address was entered", () => {
        startNewFamily("MailingErr" + Cypress._.random(0, 1e6));

        cy.get("#secondAddressToggle").click();
        // The client-side guard disables the checkbox (so it is not even submitted).
        // Re-enable it to prove the SERVER rejects the flag on its own.
        cy.get("#SecondIsMailing").invoke("prop", "disabled", false);
        cy.get("#SecondIsMailing").check({ force: true });
        cy.get('button[name="FamilySubmit"]').click();

        // Still on the editor, with the validation message shown and the section open.
        cy.location("pathname").should("include", "FamilyEditor.php");
        cy.get("#SecondAddressError").should(
            "contain",
            "Enter a second address before marking it as the mailing address",
        );
        cy.get("#secondAddressSection").should("have.class", "show");
    });

    // Keep last: the API-key request below invalidates the browser session.
    it("exposes the flagged second address and resolved MailingAddress over the API", () => {
        startNewFamily("MailingApi" + Cypress._.random(0, 1e6));

        cy.get("#secondAddressToggle").click();
        cy.get("#SecondAddress1").type("PO Box 99");
        cy.get("#SecondCity").type("Othertown");
        cy.get("#SecondIsMailing").check();
        cy.get('button[name="FamilySubmit"]').click();

        cy.location("pathname").should("include", "/people/family/");
        rememberFamilyIdFromUrl().then((familyId) => {
            cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}`, null, 200).then((response) => {
                expect(response.body.SecondAddress1).to.equal("PO Box 99");
                expect(response.body.SecondCity).to.equal("Othertown");
                expect(response.body.SecondIsMailing).to.be.true;
                expect(response.body.HasSecondAddress).to.be.true;
                expect(response.body.SecondAddressIsMailing).to.be.true;
                // MailingAddress resolves to the flagged second address …
                expect(response.body.MailingAddress.Address1).to.equal("PO Box 99");
                expect(response.body.MailingAddress.City).to.equal("Othertown");
                // … while Address keeps meaning the primary/physical address.
                expect(response.body.Address).to.contain("11 Primary Street");
                expect(response.body.Address1).to.equal("11 Primary Street");
            });
        });
    });
});
