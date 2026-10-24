/// <reference types="cypress" />

/**
 * Member sign-in row 10 (#10463): confirming an unconfirmed email on the person
 * page's Portal sign-in card, and the person editor's email field
 * (docs/member-sign-in-design.md §9, §11.3).
 *
 * Seed people: 2 Mathew (no account), 100 Lena (self-service account), 3 Tony
 * (a staff account). judith.matthews has Edit Records and is not an
 * administrator. The family email move and "has signed in" have no HTTP
 * surface until rows 5 and 9, so they are set with cy.dbQuery().
 */

const MATHEW = { id: 2, email: "mathew.campbell@example.com" };
const LENA = { id: 100, email: "lena.walker@example.com" };
const TONY = { id: 3 };
const JUDITH = { username: "judith.matthews@example.com", password: "noMoney$" };

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const unconfirmed = (personId, email, source) =>
    db(
        "INSERT INTO person_signin_psi (psi_per_ID, psi_ProvenEmail, psi_ProvenAt, psi_UnconfirmedEmail, psi_UnconfirmedSource, psi_UnconfirmedAt) VALUES (?, ?, ?, ?, ?, NOW()) " +
            "ON DUPLICATE KEY UPDATE psi_UnconfirmedEmail = VALUES(psi_UnconfirmedEmail), psi_UnconfirmedSource = VALUES(psi_UnconfirmedSource)",
        [personId, source === "family_email" ? null : "old.address@example.com", source === "family_email" ? null : "2026-01-01 00:00:00", email, source],
    );

const signInAs = ({ username, password }) => {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(username);
    cy.get("input[name=Password]").type(`${password}{enter}`, { log: false });
    cy.url().should("not.include", "/session/begin");
};

describe("Sign-in email on the person page and the person editor (§9)", () => {
    afterEach(() => {
        db("DELETE FROM person_signin_psi WHERE psi_per_ID IN (?, ?)", [MATHEW.id, LENA.id]);
    });

    it("lets an administrator confirm an email moved from the family email", () => {
        unconfirmed(MATHEW.id, MATHEW.email, "family_email");
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
        unconfirmed(LENA.id, LENA.email, "staff");
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

        unconfirmed(LENA.id, LENA.email, "staff");
        cy.visit(`/PersonEditor.php?PersonID=${LENA.id}`);
        cy.get("#Email").should("not.have.attr", "readonly");
        cy.get("#EmailHint").should("contain", "signs them out everywhere");

        cy.visit(`/PersonEditor.php?PersonID=${MATHEW.id}`);
        cy.get("#Email").should("not.have.attr", "readonly");
        cy.get("#EmailHint").should("not.exist");
    });
});
