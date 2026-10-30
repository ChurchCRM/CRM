/// <reference types="cypress" />

/**
 * Member sign-in row 4 (#10463): Admin → Member Portal → Sign-in
 * (docs/member-sign-in-design.md §11.1) and the person page's Portal sign-in
 * card (§11.3). Every x-api-key request replaces the browser session, so the
 * UI steps that follow one log in again.
 */

const SETTINGS_URL = "/admin/api/member-portal/sign-in";
const PERSON_ID = 2;

const settings = () => cy.makePrivateAdminAPICall("GET", SETTINGS_URL, null, 200).its("body");

describe("Admin → Member Portal → Sign-in", () => {
    let savedValues;
    let savedEmailEnabled;

    before(() => {
        settings().then((body) => {
            savedValues = body.values;
        });
        cy.getSystemConfig("bEnabledEmail").then((value) => {
            savedEmailEnabled = value;
        });
    });

    afterEach(() => {
        cy.restoreSystemConfig("bEnabledEmail", savedEmailEnabled);
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, savedValues, 200);
        cy.makePrivateAdminAPICall("POST", `/admin/api/person/${PERSON_ID}/sign-in/enable`, null, [200, 409]);
    });

    it("saves the rule and shows a wrong value next to its field", () => {
        cy.freshAdminFormLogin();
        cy.visit("/admin/member-portal#portal-signin");
        cy.get("#portal-signin").should("have.class", "active");
        cy.get("#memberSignInClassifications [data-cy=classification-count]").first().invoke("text").should("match", /^\d+$/);

        cy.get("#memberSignInClassification-3").check();
        cy.get("#iMemberSignInMinAge").clear().type("500");
        cy.get("#memberSignInSaveButton").click();
        cy.get('[data-error-for="iMemberSignInMinAge"]').should("contain.text", "0 to 120");
        cy.get("#iMemberSignInMinAge").should("have.class", "is-invalid");

        cy.get("#iMemberSignInMinAge").clear().type("16");
        cy.get("#iRememberDeviceDays").clear().type("0");
        cy.get("#bAllowLoginAsUser").check();
        cy.get("#memberSignInSaveButton").click();
        cy.get("#memberSignInStatus").should("contain.text", "Saved.");
        cy.get('[data-error-for="iMemberSignInMinAge"]').should("be.empty");

        cy.reload();
        cy.get("#memberSignInClassification-3").should("be.checked");
        cy.get("#iMemberSignInMinAge").should("have.value", "16");
        cy.get("#iRememberDeviceDays").should("have.value", "0");
        cy.get("#bAllowLoginAsUser").should("be.checked");
    });

    it("adds people to the office list by name", () => {
        cy.freshAdminFormLogin();
        cy.visit("/admin/member-portal#portal-signin");
        cy.get("#sMemberSignInNotificationRecipientIDs").parent().find(".ts-control input").type("Mathew");
        cy.get(".ts-dropdown .option").contains("mathew.campbell@example.com").click();
        cy.get("#memberSignInSaveButton").click();
        cy.get("#memberSignInStatus").should("contain.text", "Saved.");
        settings().its("values.sMemberSignInNotificationRecipientIDs").should("eq", String(PERSON_ID));
    });

    it("can't turn member sign-in on while email is off, and says why", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/bEnabledEmail", { value: "0" }, 200);
        cy.freshAdminFormLogin();
        cy.visit("/admin/member-portal#portal-signin");
        cy.get("#bMemberSignIn").should("be.disabled").and("not.be.checked");
        cy.get("#memberSignInEmailProblem").should("be.visible").find("a").should("have.attr", "href").and("include", "/admin/email");
    });

    it("lets an administrator disable and enable a person's sign-in from the person page", () => {
        cy.freshAdminFormLogin();
        cy.visit(`/people/view/${PERSON_ID}`);
        cy.get("#portal-signin-card").should("be.visible");
        cy.get("#portal-signin-disabled").should("not.exist");

        cy.get("#portal-signin-disable").click();
        cy.focused().should("have.id", "portal-signin-disable-reason");
        cy.get("#portal-signin-disable-confirm").click();
        cy.get("#portal-signin-disable-error").should("not.be.empty");
        cy.get("#portal-signin-disable-reason").type("Asked to close their access");
        cy.get("#portal-signin-disable-confirm").click();

        cy.get("#portal-signin-disabled", { timeout: 10000 }).should("contain.text", "Sign-in is disabled");
        cy.get("#portal-signin-disabled-reason").should("contain.text", "Asked to close their access");
        cy.get("#portal-signin-status").should("have.attr", "data-reason", "sign_in_disabled");

        cy.get("#portal-signin-enable").click();
        cy.get("#portal-signin-disable", { timeout: 10000 }).should("be.visible");
        cy.get("#portal-signin-disabled").should("not.exist");
    });

    it("shows staff with Edit Records the card without its controls", () => {
        cy.makePrivateAdminAPICall("POST", `/admin/api/person/${PERSON_ID}/sign-in/disable`, { reason: "Edit Records view" }, 200);
        cy.setupNoFinanceSession();
        cy.visit(`/people/view/${PERSON_ID}`);
        cy.get("#portal-signin-disabled").should("contain.text", "Sign-in is disabled");
        cy.get("#portal-signin-disabled-reason").should("not.exist");
        cy.get("#portal-signin-disable").should("not.exist");
        cy.get("#portal-signin-enable").should("not.exist");
        cy.get("#portal-signin-sign-out").should("not.exist");
    });
});
