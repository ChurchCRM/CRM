/// <reference types="cypress" />

import { createPersonInEditor } from "../../../../support/person-editor";

const LAST_NAME = "Famconv";

const ADDRESS = {
    Address1: "12 Orchard Lane",
    Address2: "Unit 4",
    City: "Springfield",
    Zip: "62704",
    Country: "United States",
    HomePhone: "(555) 010-2030",
};

const createLoner = (firstName) =>
    createPersonInEditor({ FirstName: firstName, LastName: LAST_NAME, Gender: 1, Classification: 1, Family: 0, ...ADDRESS });

const readPerson = (id) => cy.makePrivateAdminAPICall("GET", `/api/person/${id}`, null, 200).its("body");
const readFamily = (id) => cy.makePrivateAdminAPICall("GET", `/api/family/${id}`, null, 200).its("body");

describe("API — Convert a person without a family into a family", () => {
    const created = { people: [], families: [] };

    after(() => {
        created.families.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/family/${id}?deleteMembers=true`, null, [200, 404]);
        });
        created.people.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/person/${id}`, null, [200, 404]);
        });
    });

    it("moves the person into a new family with their name, address and home phone", () => {
        createLoner("Avery").then((personId) => {
            created.people.push(personId);

            cy.makePrivateAdminAPICall("POST", `/admin/api/person/${personId}/convert-to-family`, {}, 200).then(({ body }) => {
                expect(body.success).to.equal(true);
                expect(body.family.name).to.equal(LAST_NAME);
                expect(body.family.url).to.match(new RegExp(`/people/family/${body.family.id}$`));
                created.families.push(body.family.id);

                readFamily(body.family.id).then((family) => {
                    expect(family.Name).to.equal(LAST_NAME);
                    expect(family.Address1).to.equal(ADDRESS.Address1);
                    expect(family.Address2).to.equal(ADDRESS.Address2);
                    expect(family.City).to.equal(ADDRESS.City);
                    expect(family.Zip).to.equal(ADDRESS.Zip);
                    expect(family.Country).to.equal(ADDRESS.Country);
                    expect(family.HomePhone).to.contain("555");
                });
                readPerson(personId).then((person) => {
                    expect(Number(person.FamId)).to.equal(body.family.id);
                    ["Address1", "Address2", "City", "State", "Zip", "Country", "HomePhone"].forEach((field) => {
                        expect(person[field] ?? null, field).to.equal(null);
                    });
                });

                cy.makePrivateAdminAPICall("POST", `/admin/api/person/${personId}/convert-to-family`, {}, 400)
                    .its("body.message")
                    .should("equal", "Already in a family");
            });
        });
    });

    it("refuses the conversion to users who are not administrators", () => {
        createLoner("Cameron").then((personId) => {
            created.people.push(personId);

            ["makePrivateNoPermAPICall", "makePrivateUserAPICall"].forEach((command) => {
                cy[command]("POST", `/admin/api/person/${personId}/convert-to-family`, {}, 403);
            });

            readPerson(personId).then((person) => {
                expect(Number(person.FamId)).to.equal(0);
                expect(person.Address1).to.equal(ADDRESS.Address1);
                expect(person.HomePhone).to.contain("555");
            });
        });
    });

    it("answers 404 for a person who does not exist", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/person/999999/convert-to-family", {}, 404);
    });
});
