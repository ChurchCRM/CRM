/// <reference types="cypress" />

// Issue #10683 — user config API must honor the same per-setting permission
// stored on UserConfig rows as SettingsIndividual.php.
// Existing seed: user 3 (tony.wade) has bAddEvent permission FALSE, and an
// editable bEmailMailto=1. All successful test writes preserve seeded values.
describe("User configuration API write authorization", () => {
    const self = "/api/user/3/config";

    it("rejects a non-admin write to a protected setting without modifying it", () => {
        cy.makePrivateUserAPICall("POST", `${self}/bAddEvent`,
            { value: "1" }, 403);
        // Admin can still write the existing blank value, proving this is
        // a per-setting permission check, not a globally disabled endpoint.
        cy.makePrivateAdminAPICall("POST", `${self}/bAddEvent`,
            { value: "" }, 200);
    });

    it("rejects unknown keys with 404 instead of an uncaught exception", () => {
        cy.makePrivateUserAPICall("POST", `${self}/notARealUserConfig`,
            { value: "invented" }, 404);
        cy.makePrivateAdminAPICall("POST", `${self}/notARealUserConfig`,
            { value: "invented" }, 404);
    });

    it("rejects missing or non-string values before attempting a save", () => {
        cy.makePrivateUserAPICall("POST", `${self}/bEmailMailto`, {}, 400);
        cy.makePrivateUserAPICall("POST", `${self}/bEmailMailto`,
            { value: ["1"] }, 400);
    });

    it("preserves writes to editable settings for regular users", () => {
        // Existing seed value is '1'; this call does not alter fixture data.
        cy.makePrivateUserAPICall("POST", `${self}/bEmailMailto`,
            { value: "1" }, 200).then(({ body }) => {
                expect(body).to.have.property("bEmailMailto", "1");
            });
    });
});
