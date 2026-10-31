/// <reference types="cypress" />

/**
 * Member sign-in row 4 (#10463): who may sign in (docs/member-sign-in-design.md §4),
 * read through GET /api/person/{id}/sign-in, the person card's status.
 * Seed people: 2 Mathew (head, Member, 1970), 5 Albert (child), 43 Natalie (head, Guest),
 * 88 Cecil (child, no birth year), 18 Kathryn (inactive family), 19 Laurie (deceased),
 * 911 (inactive), 104 Mark (awaiting review), 3 and 35 (share tony.wade@example.com),
 * 1 Church Admin (no email).
 */

const SETTINGS_URL = "/admin/api/member-portal/sign-in";
const RULE_KEYS = ["sMemberSignInFamilyRoles", "sMemberSignInClassifications", "iMemberSignInMinAge"];

const status = (personId) =>
    cy.makePrivateAdminAPICall("GET", `/api/person/${personId}/sign-in`, null, 200).its("body");

const expectReason = (personId, reason) =>
    status(personId).then((body) => {
        expect(body.reason, `person ${personId}`).to.eq(reason);
        expect(body.maySignIn).to.eq(reason === null);
    });

const setRule = (settings) => cy.makePrivateAdminAPICall("POST", SETTINGS_URL, settings, 200);

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

describe("Member sign-in rule (person sign-in status)", () => {
    const saved = {};

    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTINGS_URL, null, 200).then(({ body }) => {
            RULE_KEYS.forEach((key) => {
                saved[key] = body.values[key];
            });
        });
    });

    beforeEach(() => {
        setRule({ sMemberSignInFamilyRoles: [1, 2, 3], sMemberSignInClassifications: [1, 2], iMemberSignInMinAge: 13 });
    });

    afterEach(() => {
        db("UPDATE person_per SET per_BirthYear = 1970 WHERE per_ID = 2");
        db("DELETE FROM person_signin_psi WHERE psi_per_ID = 2");
    });

    after(() => {
        setRule(saved);
    });

    it("lets in a head of household whose role, classification and age are ticked", () => {
        status(2).then((body) => {
            expect(body.maySignIn).to.eq(true);
            expect(body.reason).to.be.null;
            expect(body.signInDisabled).to.eq(false);
            expect(body.devices).to.eq(0);
            expect(body.passkeys).to.eq(0);
        });
    });

    it("refuses a family role that is not ticked", () => {
        expectReason(5, null);
        setRule({ sMemberSignInFamilyRoles: [1, 2] });
        expectReason(5, "family_role");
    });

    it("refuses a classification that is not ticked", () => {
        expectReason(43, "classification");
        setRule({ sMemberSignInClassifications: [1, 2, 3] });
        expectReason(43, null);
    });

    it("refuses anyone younger than the minimum age", () => {
        setRule({ iMemberSignInMinAge: 120 });
        expectReason(2, "under_minimum_age");
        setRule({ iMemberSignInMinAge: 0 });
        expectReason(2, null);
    });

    it("with no birth year lets in a head of household but not a child", () => {
        expectReason(88, "age_unknown");
        db("UPDATE person_per SET per_BirthYear = NULL WHERE per_ID = 2");
        expectReason(2, null);
        setRule({ iMemberSignInMinAge: 0 });
        expectReason(88, null);
    });

    it("applies the blocks for everyone before the member rule", () => {
        setRule({ sMemberSignInFamilyRoles: [0, 1, 2, 3, 4, 5], sMemberSignInClassifications: [0, 1, 2, 3, 4, 5] });
        expectReason(19, "deceased");
        expectReason(911, "inactive");
        expectReason(18, "family_inactive");
        expectReason(104, "needs_review");
    });

    it("shows Disable sign-in, and its reason to administrators only", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/person/2/sign-in/disable", { reason: "Rule spec" }, 200);
        status(2).then((body) => {
            expect(body.reason).to.eq("sign_in_disabled");
            expect(body.signInDisabled).to.eq(true);
            expect(body.disabledReason).to.eq("Rule spec");
            expect(body.disabledBy.id).to.eq(1);
        });
        cy.makePrivateEditRecordsAPICall("GET", "/api/person/2/sign-in", null, 200).then(({ body }) => {
            expect(body.signInDisabled).to.eq(true);
            expect(body.disabledReason).to.be.null;
        });
        cy.makePrivateAdminAPICall("POST", "/admin/api/person/2/sign-in/enable", null, 200);
        expectReason(2, null);
    });

    it("refuses an email two people who would qualify share, but not one only one of them qualifies for", () => {
        setRule({ sMemberSignInFamilyRoles: [0], sMemberSignInClassifications: [0, 2], iMemberSignInMinAge: 0 });
        expectReason(3, "shared_email");
        expectReason(35, "shared_email");
        setRule({ sMemberSignInClassifications: [2] });
        expectReason(35, "classification");
        expectReason(3, null);
    });

    it("refuses an unconfirmed address until it is confirmed", () => {
        db(
            "INSERT INTO person_signin_psi (psi_per_ID, psi_UnconfirmedEmail, psi_UnconfirmedSource, psi_UnconfirmedAt) VALUES (2, 'Mathew.Campbell@example.com', 'family_email', NOW()) ON DUPLICATE KEY UPDATE psi_UnconfirmedEmail = VALUES(psi_UnconfirmedEmail)",
        );
        expectReason(2, "email_unconfirmed");
        db("UPDATE person_signin_psi SET psi_UnconfirmedEmail = NULL, psi_UnconfirmedSource = NULL WHERE psi_per_ID = 2");
        expectReason(2, null);
    });

    it("refuses someone without a personal email", () => {
        setRule({ sMemberSignInFamilyRoles: [0], sMemberSignInClassifications: [0], iMemberSignInMinAge: 0 });
        expectReason(1, "no_email");
    });

    it("is open to Edit Records and closed to everyone else", () => {
        cy.makePrivateEditRecordsAPICall("GET", "/api/person/2/sign-in", null, 200);
        cy.makePrivateNoPermAPICall("GET", "/api/person/2/sign-in", null, 403);
        cy.makePrivateAdminAPICall("GET", "/api/person/99999/sign-in", null, 404);
    });
});
