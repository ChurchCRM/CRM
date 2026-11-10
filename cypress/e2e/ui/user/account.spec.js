/// <reference types="cypress" />

import { STAFF } from "../../../support/gates/personas";

// Each row is checked against an endpoint behind the same gate.
const PROBES = {
    Notes: "/api/person/2/notes",
    "Manage groups and roles": "/kiosk/api/devices",
    "Manage donations and finance": "/api/donation-funds",
    "Manage fundraisers": "/api/fundraisers",
    "Email people and groups": "/api/cart/emails",
};

describe("My Account (/account)", () => {
    it("shows the signed-in user's own account, with no id in the URL", () => {
        cy.setupStandardSession();
        cy.visit("/account");
        cy.location("pathname").should("match", /\/account\/?$/);
        cy.readEnv("standard.username").then((username) => {
            cy.get("#tab-account").should("contain", username);
        });
        cy.get('#tab-account a[href$="/account/password"]').should("exist");
        cy.get('#tab-account a[href$="/account/2fa"]').should("exist");
    });

    it("serves the password and two-factor pages", () => {
        cy.setupStandardSession();
        cy.visit("/account/password");
        cy.get("#passwordChangeForm").should("have.attr", "action").and("match", /\/account\/password$/);
        cy.visit("/account/2fa");
        cy.get("#two-factor-enrollment-app").should("exist");
    });

    it("no longer serves /v2/user", () => {
        cy.setupStandardSession();
        ["/v2/user/3", "/v2/user/current/changepassword", "/v2/user/current/manage2fa", "/v2/user/current/enroll2fa"].forEach((url) => {
            cy.request({ url, failOnStatusCode: false }).its("status").should("eq", 404);
        });
    });

    STAFF.forEach((persona) => {
        it(`${persona}: every probed permission row matches its gate, and denied rows say why`, () => {
            cy.setupPersonaSession(persona);
            cy.visit("/account");
            cy.get('#settingsNav a[href="#tab-permissions"]').click();
            cy.get('[data-cy="permission-row"]').should("have.length.greaterThan", 10);
            cy.get('[data-cy="permission-row"][data-granted="false"]').each((row) => {
                cy.wrap(row).find('[data-cy="permission-reason"]').invoke("text").should("not.be.empty");
            });
            Object.entries(PROBES).forEach(([label, url]) => {
                cy.contains('[data-cy="permission-label"]', new RegExp(`^${label}$`))
                    .closest('[data-cy="permission-row"]')
                    .invoke("attr", "data-granted")
                    .then((granted) => {
                        cy.personaAPICall(persona, "GET", url, null, granted === "true" ? 200 : 403);
                    });
            });
        });
    });
});
