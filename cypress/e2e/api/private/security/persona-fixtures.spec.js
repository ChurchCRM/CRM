/// <reference types="cypress" />

import { PERSONAS, personaNames } from "../../../../support/personas-data";

describe("Persona fixtures: every persona's API key still authenticates", () => {
    personaNames.forEach((name) => {
        const { confinedToPortal } = PERSONAS[name];

        it(`${name} ${confinedToPortal ? "is confined to the portal (403)" : "reads people (200)"}`, () => {
            cy.personaAPICall(name, "GET", "/api/person/1", null, confinedToPortal ? 403 : 200);
        });
    });
});
