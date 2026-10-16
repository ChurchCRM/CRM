/// <reference types="cypress" />

describe("Family editor country (#10418)", () => {
    const LATITUDE = 39.0418;
    const LONGITUDE = -94.5418;
    const STREET = "10418 Country Test Way";
    const createdFamilyIds = [];

    beforeEach(() => cy.setupStandardSession());

    after(() => cy.cleanupFamilies(createdFamilyIds));

    const postEditorForm = (fields, options = {}) =>
        cy.get("#familyEditor").then(($form) => {
            const body = new URLSearchParams(new FormData($form[0]));
            Object.entries({ ...fields, FamilySubmit: "" }).forEach(([key, value]) => body.set(key, value));
            return cy.request({
                method: "POST",
                url: $form.attr("action"),
                headers: { "content-type": "application/x-www-form-urlencoded" },
                body: body.toString(),
                ...options,
            });
        });

    // Imported and older families hold the country as a name or an alias, which the
    // editor's select (valued by code) cannot post, so the form is submitted directly.
    const createFamilyStoredAs = (country) => {
        cy.visit("/FamilyEditor.php");
        cy.get("#FamilyName").type(`CountryName${Cypress._.random(0, 1e6)}`);
        return postEditorForm({
            Address1: STREET,
            City: "Kansas City",
            stateType: "dropDown",
            State: "MO",
            Zip: "64111",
            Country: country,
            SecondAddress1: "PO Box 10418",
            SecondCity: "Kansas City",
            secondStateType: "dropDown",
            SecondState: "MO",
            SecondZip: "64111",
            SecondCountry: country,
            Latitude: String(LATITUDE),
            Longitude: String(LONGITUDE),
        }).then((response) => {
            const familyId = Number(response.redirects.pop().split("/").pop());
            createdFamilyIds.push(familyId);
            return familyId;
        });
    };

    const openEditor = (familyId) => {
        cy.visit(`/FamilyEditor.php?FamilyID=${familyId}`);
        cy.get("#Country", { timeout: 10000 }).should("have.value", "US");
        cy.get("#State", { timeout: 10000 }).should("have.value", "MO");
        cy.get("#SecondCountry", { timeout: 10000 }).should("have.value", "US");
        cy.get("#SecondState", { timeout: 10000 }).should("have.value", "MO");
    };

    const readFamily = (familyId) => cy.request(`/api/family/${familyId}`).its("body");

    ["United States", "USA"].forEach((storedCountry) => {
        it(`keeps "${storedCountry}", the coordinates and the address line on an unchanged save`, () => {
            createFamilyStoredAs(storedCountry).then((familyId) => {
                openEditor(familyId);
                cy.get('button[name="FamilySubmit"]').click();

                cy.location("pathname").should("match", new RegExp(`/people/family/${familyId}$`));
                readFamily(familyId).then((family) => {
                    expect(family.Country).to.equal(storedCountry);
                    expect(family.SecondCountry).to.equal(storedCountry);
                    expect(family.Latitude).to.equal(LATITUDE);
                    expect(family.Longitude).to.equal(LONGITUDE);
                });
                cy.contains(`${STREET} Kansas City, MO 64111 United States`);
            });
        });
    });

    it("keeps a stored country the list does not know on an unchanged save", () => {
        createFamilyStoredAs("Atlantis").then((familyId) => {
            cy.visit(`/FamilyEditor.php?FamilyID=${familyId}`);
            cy.get("#Country", { timeout: 10000 }).should("have.value", "Atlantis");
            cy.get("#SecondCountry", { timeout: 10000 }).should("have.value", "Atlantis");
            cy.get("#StateTextbox").should("be.visible").and("have.value", "MO");
            cy.get('button[name="FamilySubmit"]').click();

            cy.location("pathname").should("match", new RegExp(`/people/family/${familyId}$`));
            readFamily(familyId).then((family) => {
                expect(family.Country).to.equal("Atlantis");
                expect(family.SecondCountry).to.equal("Atlantis");
                expect(family.State).to.equal("MO");
                expect(family.Latitude).to.equal(LATITUDE);
                expect(family.Longitude).to.equal(LONGITUDE);
            });
        });
    });

    it("treats a real country change as an address change and shows the country by name", () => {
        createFamilyStoredAs("United States").then((familyId) => {
            openEditor(familyId);
            postEditorForm({ Country: "CA" }, { timeout: 60000 });

            readFamily(familyId).then((family) => {
                expect(family.Country).to.equal("CA");
                expect(family.Latitude).to.not.equal(LATITUDE);
                expect(family.Longitude).to.not.equal(LONGITUDE);
            });
            cy.visit(`/people/family/${familyId}`);
            cy.contains(`${STREET} Kansas City, MO 64111 Canada`);
        });
    });
});
