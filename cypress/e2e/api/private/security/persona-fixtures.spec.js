/// <reference types="cypress" />

import { PERSONAS, personaNames } from "../../../../support/personas-data";

describe("Persona fixtures: every staff persona's API key still authenticates", () => {
    personaNames
        .filter((name) => PERSONAS[name].apiKey)
        .forEach((name) => {
            it(`${name} reads people (200)`, () => {
                cy.personaAPICall(name, "GET", "/api/person/1", null, 200);
            });
        });
});
