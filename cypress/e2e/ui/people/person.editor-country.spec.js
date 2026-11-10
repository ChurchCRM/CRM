/// <reference types="cypress" />

describe("Person editor country and state (#10418, #10566)", () => {
    const createdPersonIds = [];
    let originalHideAddress;

    before(() => {
        cy.setupAdminSession();
        cy.getSystemConfig("bHidePersonAddress").then((value) => {
            originalHideAddress = value;
        });
        cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bHidePersonAddress", { value: "0" }, 200);
    });

    beforeEach(() => cy.setupStandardSession());

    after(() => {
        cy.cleanupPeople(createdPersonIds);
        cy.setupAdminSession();
        cy.restoreSystemConfig("bHidePersonAddress", originalHideAddress);
    });

    const createUnassignedPersonStoredAs = (country, state = "MO") => {
        cy.visit("/people/person/editor");
        return cy.get("#personEditor").then(($form) => {
            const body = new URLSearchParams(new FormData($form[0]));
            Object.entries({
                firstName: `CountryName${Cypress._.random(0, 1e6)}`,
                lastName: "Person10418",
                gender: "1",
                family: "0",
                address1: "10418 Country Test Way",
                city: "Kansas City",
                state: state,
                zip: "64111",
                country: country,
                personSubmit: "",
            }).forEach(([key, value]) => body.set(key, value));
            return cy
                .request({
                    method: "POST",
                    url: $form[0].action,
                    headers: { "content-type": "application/x-www-form-urlencoded" },
                    body: body.toString(),
                })
                .then((response) => {
                    const personId = Number(response.redirects.pop().split("/").pop());
                    createdPersonIds.push(personId);
                    return personId;
                });
        });
    };

    ["United States", "USA"].forEach((storedCountry) => {
        it(`keeps "${storedCountry}" on an unchanged save`, () => {
            createUnassignedPersonStoredAs(storedCountry).then((personId) => {
                cy.visit(`/people/person/editor?personId=${personId}`);
                cy.get("#Country", { timeout: 10000 }).should("have.value", "US");
                cy.get("#State", { timeout: 10000 }).should("have.value", "MO");
                cy.get('button[name="personSubmit"]').click();

                cy.location("pathname").should("match", new RegExp(`/people/view/${personId}$`));
                cy.request(`/api/person/${personId}`).its("body.Country").should("equal", storedCountry);
            });
        });
    });

    it("keeps a country stored by its English name and a stored state name on an unchanged save", () => {
        createUnassignedPersonStoredAs("Germany", "Bayern").then((personId) => {
            cy.visit(`/people/person/editor?personId=${personId}`);
            cy.get("#Country", { timeout: 10000 }).should("have.value", "DE");
            cy.get("#State", { timeout: 10000 }).should("have.value", "BY");
            cy.get('button[name="personSubmit"]').click();

            cy.location("pathname").should("match", new RegExp(`/people/view/${personId}$`));
            cy.request(`/api/person/${personId}`)
                .its("body")
                .then((person) => {
                    expect(person.Country).to.equal("Germany");
                    expect(person.State).to.equal("Bayern");
                });
        });
    });
});
