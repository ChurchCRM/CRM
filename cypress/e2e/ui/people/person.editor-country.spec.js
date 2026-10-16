/// <reference types="cypress" />

describe("Person editor country (#10418)", () => {
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

    const createUnassignedPersonStoredAs = (country) => {
        cy.visit("/PersonEditor.php");
        return cy.get("#personEditor").then(($form) => {
            const body = new URLSearchParams(new FormData($form[0]));
            Object.entries({
                FirstName: `CountryName${Cypress._.random(0, 1e6)}`,
                LastName: "Person10418",
                Gender: "1",
                Family: "0",
                Address1: "10418 Country Test Way",
                City: "Kansas City",
                State: "MO",
                Zip: "64111",
                Country: country,
                PersonSubmit: "",
            }).forEach(([key, value]) => body.set(key, value));
            return cy
                .request({
                    method: "POST",
                    url: $form.attr("action"),
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
                cy.visit(`/PersonEditor.php?PersonID=${personId}`);
                cy.get("#Country", { timeout: 10000 }).should("have.value", "US");
                cy.get("#State", { timeout: 10000 }).should("have.value", "MO");
                cy.get('button[name="PersonSubmit"]').click();

                cy.location("pathname").should("match", new RegExp(`/people/view/${personId}$`));
                cy.request(`/api/person/${personId}`).its("body.Country").should("equal", storedCountry);
            });
        });
    });
});
