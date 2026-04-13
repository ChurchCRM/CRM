/// <reference types="cypress" />

describe("2FA Recovery Code Generation", () => {
    beforeEach(() => cy.setupStandardSession());

    it("generates 12 unique codes in xxxxxxxx-xxxxxxxx lowercase hex format", () => {
        cy.makePrivateUserAPICall(
            "POST",
            "/api/user/current/refresh2farecoverycodes",
            null,
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("TwoFARecoveryCodes");
            const codes = resp.body.TwoFARecoveryCodes;
            expect(codes).to.have.length(12);
            const format = /^[a-f0-9]{8}-[a-f0-9]{8}$/;
            codes.forEach((code) => expect(code).to.match(format));
            expect(new Set(codes).size).to.equal(12);
        });
    });
});

describe("2FA Login Template", () => {
    beforeEach(() => cy.setupStandardSession());

    it("renders TOTP mode by default with toggle to recovery mode", () => {
        cy.visit("session/two-factor");
        cy.get("#TwoFACode")
            .should("have.attr", "maxlength", "6")
            .should("have.attr", "inputmode", "numeric")
            .should("have.attr", "placeholder", "000000");
        cy.contains("a", "Use a recovery code instead")
            .should("have.attr", "href")
            .and("include", "/session/two-factor?recovery");
        cy.contains("a", "Use a different account");
    });

    it("renders recovery mode when ?recovery is present", () => {
        cy.visit("session/two-factor?recovery");
        cy.get("#TwoFACode")
            .should("have.attr", "maxlength", "20")
            .should("have.attr", "placeholder", "xxxxxxxx-xxxxxxxx")
            .should("not.have.attr", "inputmode", "numeric");
        cy.contains("a", "Use authenticator app instead")
            .should("have.attr", "href")
            .and("match", /\/session\/two-factor$/);
        cy.contains("a", "Use a different account");
    });
});
