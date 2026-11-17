/// <reference types="cypress" />

// Seed: user 3 (tony.wade) may change bEmailMailto (id 0, value '1'); bAddEvent (id 10, value '') is admin-only.
describe("Staff member changes their own advanced settings", () => {
    const mailtoField = 'select[name="newValue[0]"]';

    beforeEach(() => cy.setupStandardSession());

    after(() => {
        cy.makePrivateUserAPICall("POST", "/api/user/3/config/bEmailMailto", { value: "1" }, 200);
        cy.makePrivateAdminAPICall("POST", "/api/user/3/config/bAddEvent", { value: "" }, 200);
    });

    it("turns off mailto links from My Account and sees the change after saving", () => {
        cy.visit("/account");
        cy.get("#editSettings").click();
        cy.location("pathname").should("match", /\/account\/settings$/);

        cy.get("#userSettingsForm").within(() => {
            cy.contains("tr", "bEmailMailto").should("contain", "User permission to send email via mailto: links");
            cy.contains("bAddEvent").should("not.exist");
            cy.get(mailtoField).should("have.value", "1").select("");
            cy.contains("button", "Save Settings").click();
        });

        cy.location("pathname").should("match", /\/account\/settings$/);
        cy.get(mailtoField).should("have.value", "");

        cy.get(mailtoField).select("1");
        cy.get("#userSettingsForm").contains("button", "Save Settings").click();
        cy.get(mailtoField).should("have.value", "1");
    });

    it("leaves an admin-only setting unchanged when its value is posted anyway", () => {
        cy.visit("/account/settings");
        cy.get('#userSettingsForm input[name="csrf_token"]')
            .invoke("val")
            .then((csrfToken) => {
                cy.request({
                    method: "POST",
                    url: "/account/settings",
                    form: true,
                    body: { csrf_token: csrfToken, "newValue[0]": "1", "newValue[10]": "1" },
                });
            });

        cy.setupAdminSession();
        cy.visit("/admin/system/users/3/edit");
        cy.get('select[name="new_value[10]"]').should("have.value", "");
    });

    it("refuses a save without a CSRF token", () => {
        cy.request({
            method: "POST",
            url: "/account/settings",
            form: true,
            body: { "newValue[0]": "" },
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 403);

        cy.visit("/account/settings");
        cy.get(mailtoField).should("have.value", "1");
    });
});
