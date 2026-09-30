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
        // Row-count discipline: remove everything this spec created, members included.
        createdFamilyIds.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/family/${id}?deleteMembers=true`, null, 200);
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
        rememberFamilyIdFromUrl().then((familyId) => {
            cy.get("#second-address-card").contains("Mailing Address");
            cy.get("#second-address-card").contains("PO Box 4242 Suite B");
            // The primary card is relabelled once a second address exists.
            cy.contains("Primary Address").should("exist");

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

    it("shows the mailing address on a member's page when the family has no primary address", () => {
        const familyName = "MailingOnly" + Cypress._.random(0, 1e6);
        cy.visit("/FamilyEditor.php");
        cy.contains("Family Info");
        cy.get("#FamilyName").type(familyName);
        cy.get('input[name="FirstName1"]').type("Mia");
        cy.get("#secondAddressToggle").click();
        cy.get("#SecondAddress1").type("PO Box 5150");
        cy.get("#SecondCity").type("Othertown");
        cy.get("#SecondIsMailing").check();

        // Imported and older families can have no primary address at all, country
        // included. The editor's country list has no blank entry, so the form is
        // posted with the primary address emptied.
        cy.get("#familyEditor")
            .then(($form) => {
                const body = new URLSearchParams(new FormData($form[0]));
                ["Address1", "Address2", "City", "State", "StateTextbox", "Zip", "Country"].forEach((key) =>
                    body.set(key, "")
                );
                body.set("FamilySubmit", "");
                return cy.request({
                    method: "POST",
                    url: $form.attr("action"),
                    headers: { "content-type": "application/x-www-form-urlencoded" },
                    body: body.toString(),
                });
            })
            .then((response) => {
                createdFamilyIds.push(Number(response.redirects.pop().split("/").pop()));
            });

        cy.request(`/api/persons/search/${familyName}`).then(({ body }) => {
            cy.visit(`/people/view/${body[0].objid}`);
        });
        cy.get("#person-family-mailing-address").should("contain", "PO Box 5150");
    });

    it("keeps the second street line on the card when the first one is empty", () => {
        startNewFamily("MailingLine2" + Cypress._.random(0, 1e6));

        cy.get("#secondAddressToggle").click();
        cy.get("#SecondAddress2").type("Apt 7");
        cy.get("#SecondCity").type("Othertown");
        cy.get('button[name="FamilySubmit"]').click();

        cy.location("pathname").should("include", "/people/family/");
        rememberFamilyIdFromUrl();
        cy.get("#second-address-card").should("contain", "Apt 7 Othertown,");
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
