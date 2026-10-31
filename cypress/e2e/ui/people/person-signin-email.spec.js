/// <reference types="cypress" />

/**
 * Member sign-in row 10 (#10463): confirming an unconfirmed email on the person
 * page's Portal sign-in card, and the person editor's email field
 * (docs/member-sign-in-design.md §9, §11.3).
 *
 * Seed people: 2 Mathew (no account), 100 Lena (no account), 3 Tony (a staff
 * account). judith.matthews has Edit Records and is not an
 * administrator. An address moved from the family email and one replaced after
 * a sign-in are support fixtures.
 */

import { resetSignIn } from "../../../support/member-sign-in-fixtures";
import { markEmailUnconfirmed, markReplacedAfterSignIn } from "../../../support/sign-in-email-fixtures";

const MATHEW = { id: 2, email: "mathew.campbell@example.com" };
const LENA = { id: 100, email: "lena.walker@example.com" };
const TONY = { id: 3 };
const JUDITH = { username: "judith.matthews@example.com", password: "noMoney$" };

const signInAs = ({ username, password }) => {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(username);
    cy.get("input[name=Password]").type(`${password}{enter}`, { log: false });
    cy.url().should("not.include", "/session/begin");
};

describe("Sign-in email on the person page and the person editor (§9)", () => {
    afterEach(() => {
        resetSignIn([MATHEW.id, LENA.id]);
    });

    it("lets an administrator confirm an email moved from the family email", () => {
        markEmailUnconfirmed(MATHEW.id, "family_email");
        cy.freshAdminFormLogin();
        cy.visit(`/people/view/${MATHEW.id}`);
        cy.get("#portal-signin-unconfirmed").should("contain", MATHEW.email).and("contain", "Moved from the family email");

        cy.get("#portal-signin-confirm-email").click();
        cy.get(".bootbox").should("be.visible").and("contain", "in person or by phone");
        cy.get(".bootbox .btn-primary").click();

        cy.get("#portal-signin-card").should("exist");
        cy.get("#portal-signin-unconfirmed").should("not.exist");
    });

    it("shows staff with Edit Records why they can't confirm a replacement address", () => {
        markReplacedAfterSignIn(LENA.id, "old.address@example.com");
        signInAs(JUDITH);
        cy.visit(`/people/view/${LENA.id}`);
        cy.get("#portal-signin-unconfirmed").should("contain", LENA.email).and("contain", "Changed by staff after the person signed in");
        cy.get("#portal-signin-confirm-email").should("not.exist");
        cy.get("#portal-signin-confirm-refusal").should("contain", "Only an administrator can confirm");
    });

    it("shows the email read-only to staff who may not change it, and warns before changing a signed-in person's email", () => {
        signInAs(JUDITH);
        cy.visit(`/PersonEditor.php?PersonID=${TONY.id}`);
        cy.get("#Email").should("have.attr", "readonly");
        cy.get("#EmailHint").should("contain", "Only an administrator can change the email");

        markReplacedAfterSignIn(LENA.id, "old.address@example.com");
        cy.visit(`/PersonEditor.php?PersonID=${LENA.id}`);
        cy.get("#Email").should("not.have.attr", "readonly");
        cy.get("#EmailHint").should("contain", "signs them out everywhere");

        cy.visit(`/PersonEditor.php?PersonID=${MATHEW.id}`);
        cy.get("#Email").should("not.have.attr", "readonly");
        cy.get("#EmailHint").should("not.exist");
    });
});
