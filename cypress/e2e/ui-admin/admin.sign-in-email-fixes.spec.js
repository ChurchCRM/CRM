/// <reference types="cypress" />

import { markEmailUnconfirmed, restorePeople, setPersonEmails } from "../../support/sign-in-email-fixtures";

/**
 * Member sign-in row 9b (#10463): Fix shared emails and Confirm selected on
 * "Email needs attention", on Admin → Member Portal and, for staff with Edit
 * Records, on /people/sign-in-email (docs/member-sign-in-design.md §9, §11.2).
 * Every x-api-key request replaces the browser session, so each UI step that
 * follows one signs in again. The standard user (tony.wade) has Edit Records
 * and is not an administrator; Marion Hart (7) has a staff account.
 */

const SETTINGS_URL = "/admin/api/member-portal/sign-in";
const RULE_KEYS = ["sMemberSignInFamilyRoles", "sMemberSignInClassifications", "iMemberSignInMinAge"];
const SEED = {
    6: "constance.hart@example.com",
    7: "marion.sutton@example.com",
    26: "paul.robertson@example.com",
    28: "rafael.dixon@example.com",
    48: "kurt.hernandez@example.com",
    49: "lorraine.craig@example.com",
    50: "sherri.gordon@example.com",
    59: "franklin.beck@example.com",
    76: "leroy.larson@example.com",
    77: "natalie.lynch@example.com",
    79: "bradley.spencer@example.com",
    80: "judy.douglas@example.com",
};

const fixRow = (address) => cy.get(`#sharedEmailFixModal [data-cy=shared-fix-row][data-address="${address}"]`);

// Bootstrap ignores a hide while the modal is still opening; it focuses the modal once it is open.
const fixModalOpen = () => cy.get("#sharedEmailFixModal").should("be.visible").and("have.focus");

const noHorizontalScroll = () =>
    cy.document().then((doc) => {
        expect(doc.documentElement.scrollWidth, "page width").to.be.at.most(doc.documentElement.clientWidth + 1);
    });

describe("Email needs attention: Fix shared emails and Confirm selected", () => {
    const saved = {};

    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTINGS_URL, null, 200).then(({ body }) => {
            RULE_KEYS.forEach((key) => {
                saved[key] = body.values[key];
            });
            cy.makePrivateAdminAPICall(
                "POST",
                SETTINGS_URL,
                {
                    sMemberSignInFamilyRoles: [0, ...body.familyRoles.map((role) => role.id)],
                    sMemberSignInClassifications: [0, ...body.classifications.map((classification) => classification.id)],
                    iMemberSignInMinAge: 0,
                },
                200,
            );
        });
    });

    beforeEach(() => restorePeople(SEED));

    after(() => {
        restorePeople(SEED);
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, saved, 200);
    });

    it("fixes shared emails from the Sign-in tab's count, after a preview and a confirm", () => {
        setPersonEmails({ 76: "larson.home@example.com", 77: "larson.home@example.com" });
        setPersonEmails({ 79: "cooper.home@example.com", 80: "cooper.home@example.com" });
        setPersonEmails({ 48: "diaz.children@example.com", 49: "diaz.children@example.com" });

        cy.freshAdminFormLogin();
        cy.visit("/admin/member-portal#portal-signin");
        cy.get("#memberSignInSharedEmails").should("be.visible");
        cy.get("#memberSignInSharedEmails [data-shared-address-count]").invoke("text").then(Number).should("be.at.least", 3);
        cy.get("#memberSignInSharedEmails [data-open-shared-email-fix]").click();

        cy.get("#portal-members").should("have.class", "active");
        fixModalOpen();
        fixRow("larson.home@example.com").find("[data-cy=shared-fix-keeper]").should("contain.text", "Leroy Larson");
        fixRow("larson.home@example.com").find("[data-cy=shared-fix-losers]").should("contain.text", "Natalie Larson");
        fixRow("cooper.home@example.com").find("[data-cy=shared-fix-keeper]").should("contain.text", "Bradley Cooper");
        cy.get('#sharedEmailFixModal [data-cy=shared-by-hand-row][data-address="diaz.children@example.com"]')
            .should("contain.text", "No head of household or spouse shares it")
            .find("input")
            .should("not.exist");

        fixRow("cooper.home@example.com").find("input.shared-fix-row").uncheck();
        cy.get("#sharedEmailFixModal [data-cy=shared-fix-count]").should("have.text", "1");
        cy.get("#sharedEmailFixModal [data-cy=shared-fix-apply]").click();
        cy.get("#sharedEmailFixModal [data-cy=shared-fix-confirm]")
            .should("contain.text", "People who lose an address: 1")
            .and("contain.text", "has no email of their own");
        cy.get("#sharedEmailFixModal [data-action=back]").click();
        fixRow("cooper.home@example.com").find("input.shared-fix-row").should("not.be.checked");
        cy.get("#sharedEmailFixModal [data-cy=shared-fix-apply]").click();
        cy.get("#sharedEmailFixModal [data-cy=shared-fix-confirm-apply]").click();
        cy.get("#sharedEmailFixModal [data-cy=shared-fix-result]")
            .should("contain.text", "Shared addresses fixed: 1")
            .and("contain.text", "larson.home@example.com")
            .and("contain.text", "Natalie Larson");
        cy.get("#sharedEmailFixModal .modal-footer .btn-primary").click();
        cy.get("#sharedEmailFixModal").should("not.be.visible");

        cy.get('#memberListProblems [data-problem="shared_email"]').should("have.class", "active");
        cy.get("#memberListTable").should("contain.text", "Bradley Cooper").and("not.contain.text", "Leroy Larson");
        cy.get('#memberListProblems [data-problem="no_email"]').click();
        cy.get("#memberListTable").should("contain.text", "Natalie Larson");
    });

    it("lets Edit Records staff fix and confirm only what §9 allows them to", () => {
        setPersonEmails({ 6: "hart.home@example.com", 7: "hart.home@example.com" });
        setPersonEmails({ 76: "larson.home@example.com", 77: "larson.home@example.com" });
        [50, 59, 28].forEach((personId) => markEmailUnconfirmed(personId));
        markEmailUnconfirmed(26, "staff");
        cy.makePrivateAdminAPICall("POST", "/admin/api/person/28/sign-in/grants", { capability: "portal.calendar.view", reason: "Row 9b UI spec" }, 201);

        cy.setupStandardSession({ forceLogin: true });
        cy.visit("/people/dashboard");
        cy.get("#signInEmailAttentionLink").click();
        cy.location("pathname").should("eq", "/people/sign-in-email");

        cy.get("#sharedEmailFixButton").should("be.visible").click();
        fixModalOpen();
        fixRow("hart.home@example.com").find("input").should("be.disabled");
        fixRow("hart.home@example.com").find("[data-cy=shared-fix-refusal]").should("contain.text", "Only an administrator");
        fixRow("larson.home@example.com").find("input.shared-fix-row").should("be.checked");
        cy.get("#sharedEmailFixModal [data-cy=shared-fix-count]").should("have.text", "1");
        cy.get("#sharedEmailFixModal [data-cy=shared-fix-apply]").click();
        cy.get("#sharedEmailFixModal [data-cy=shared-fix-confirm-apply]").click();
        cy.get("#sharedEmailFixModal [data-cy=shared-fix-result]").should("contain.text", "Shared addresses fixed: 1");
        cy.get("#sharedEmailFixModal .modal-footer .btn-primary").click();
        cy.get("#sharedEmailFixModal").should("not.be.visible");

        cy.get('#memberListProblems [data-problem="email_unconfirmed"]').click();
        cy.get("#unconfirmedKindBar").should("be.visible");
        cy.get('#unconfirmedKinds [data-kind="family_email"]').click();
        cy.get("#memberListTable").should("contain.text", "Sherri Gordon").and("not.contain.text", "Paul Smith");
        cy.contains("#memberListTable tbody tr", "Rafael Dixon")
            .find("[data-cy=attention-select-locked]")
            .should("have.attr", "title")
            .and("contain", "Only an administrator");
        cy.get("#bulkConfirmSelectAll").click();
        cy.contains("#memberListTable tbody tr", "Sherri Gordon").find("[data-cy=attention-select]").should("be.checked");
        cy.contains("#memberListTable tbody tr", "Franklin Beck").find("[data-cy=attention-select]").should("be.checked");
        cy.contains("#memberListTable tbody tr", "Rafael Dixon").find("input").should("not.be.checked");
        cy.get("#bulkConfirmCount").invoke("text").then(Number).should("be.at.least", 2);

        cy.get("#bulkConfirmSelectAll").click();
        cy.get("#bulkConfirmCount").should("have.text", "0");
        cy.contains("#memberListTable tbody tr", "Sherri Gordon").find("[data-cy=attention-select]").check();
        cy.contains("#memberListTable tbody tr", "Franklin Beck").find("[data-cy=attention-select]").check();
        cy.get("#bulkConfirmButton").click();
        cy.get(".bootbox")
            .should("contain.text", "Email addresses to confirm: 2")
            .and("contain.text", "Anyone who reads an address can sign in as its person.");
        cy.get(".bootbox .bootbox-accept").should("have.focus").click();
        cy.get("#emailAttentionResult").should("contain.text", "Emails confirmed: 2");
        cy.get("#memberListTable").should("not.contain.text", "Sherri Gordon").and("not.contain.text", "Franklin Beck");
        cy.get("#memberListTable").should("contain.text", "Rafael Dixon");

        cy.get('#unconfirmedKinds [data-kind="replacement"]').click();
        cy.contains("#memberListTable tbody tr", "Paul Smith").find("[data-cy=attention-select-locked]").should("exist");
    });

    it("fits a phone screen", () => {
        setPersonEmails({ 76: "larson.home@example.com", 77: "larson.home@example.com" });
        markEmailUnconfirmed(59);
        cy.viewport(375, 812);
        cy.setupStandardSession({ forceLogin: true });
        cy.visit("/people/sign-in-email");
        cy.get("#memberListTable tbody tr").should("have.length.at.least", 1);
        noHorizontalScroll();
        cy.get('#memberListProblems [data-problem="email_unconfirmed"]').click();
        cy.contains("#memberListTable tbody tr", "Franklin Beck").find("[data-cy=attention-select]").check();
        cy.get("#memberListTable tr.child").should("not.exist");
        cy.get("#bulkConfirmCount").should("have.text", "1");
        cy.get("#sharedEmailFixButton").click();
        fixModalOpen();
        fixRow("larson.home@example.com").should("be.visible");
        noHorizontalScroll();
    });
});
