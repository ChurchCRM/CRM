/// <reference types="cypress" />

import { PERSONAS, personaNames } from "../../../support/personas-data";

describe("Persona fixtures: every persona can still sign in", () => {
    personaNames.forEach((name) => {
        const { confinedToPortal } = PERSONAS[name];

        it(`${name} lands ${confinedToPortal ? "in the portal" : "in the admin shell"}`, () => {
            cy.setupPersonaSession(name);
            cy.visit(confinedToPortal ? "/" : "/people/dashboard");
            cy.location("pathname").should("match", confinedToPortal ? /\/portal\/$/ : /\/people\/dashboard$/);
        });
    });
});
