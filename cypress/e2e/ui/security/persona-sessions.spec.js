/// <reference types="cypress" />

import { PERSONAS, personaNames } from "../../../support/personas-data";

describe("Persona fixtures: every persona can still sign in", () => {
    personaNames.forEach((name) => {
        const { confinedToPortal } = PERSONAS[name];

        it(`${name} lands ${confinedToPortal ? "in the portal" : "in the admin shell"}`, () => {
            cy.setupPersonaSession(name);
            cy.visit(confinedToPortal ? "/" : "/v2/dashboard");
            cy.location("pathname").should("eq", confinedToPortal ? "/portal/" : "/v2/dashboard");
        });
    });
});
