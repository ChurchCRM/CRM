/// <reference types="cypress" />

describe("New payment: family lookup by envelope number (issue #11190)", () => {
    const FAMILY_ID = 5;
    const ENVELOPE = 9190;
    let originalSetting;

    const assignEnvelope = (envelope) => {
        cy.clearCookies();
        cy.readEnv("admin.username").then((username) =>
            cy.readEnv("admin.password").then((password) =>
                cy.request({ method: "POST", url: "/session/begin", form: true, body: { User: username, Password: password } }),
            ),
        );
        cy.request({
            method: "POST",
            url: "/ManageEnvelopes.php",
            form: true,
            body: { Classification: "0", Confirm: "Confirm", [`EnvelopeID_${FAMILY_ID}`]: String(envelope) },
        });
        cy.clearCookies();
    };

    before(() => {
        cy.getSystemConfig("bUseDonationEnvelopes").then((v) => {
            originalSetting = v;
        });
        cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bUseDonationEnvelopes", { value: "1" }, 200);
        assignEnvelope(ENVELOPE);
    });

    after(() => {
        assignEnvelope(0);
        cy.restoreSystemConfig("bUseDonationEnvelopes", originalSetting);
    });

    // The option label shows "#9190"; a zero-padded entry never appears in it,
    // so the browser must not re-filter what the server already matched.
    ["9190", "009190"].forEach((typed) => {
        it(`lists the family after typing envelope ${typed}`, () => {
            cy.setupAdminSession();
            cy.visit("/finance/pledge/new?type=Payment");
            cy.get("#FamilyName-ts-control").type(typed);
            cy.get(".ts-dropdown .option").should("contain.text", `#${ENVELOPE}`);
            cy.get(".ts-dropdown .no-results").should("not.exist");
        });
    });
});
