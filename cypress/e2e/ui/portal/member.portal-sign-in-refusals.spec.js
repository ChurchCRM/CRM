/// <reference types="cypress" />

import {
    askForEmail,
    auditSince,
    db,
    expectAnswerScreen,
    lastAuditId,
    mailTo,
    resetEmailSignIn,
    restoreSettings,
    setConfig,
    useMemberSignIn,
    waitForMail,
} from "../../../support/member-sign-in";

/**
 * Member sign-in row 5 (#10463): addresses that may not sign in
 * (docs/member-sign-in-design.md §6.1 step 3, A6, D3, §17 "Refusals").
 * Each one gets the same answer screen and no email; the audit gets the reason
 * and the office list one notice an hour. Seed people as in
 * private.admin.member-sign-in-rule.spec.js; the office list is people 15 and 16.
 */

const office = [
    { personId: 15, email: "vivan.stone@example.com" },
    { personId: 16, email: "alicia.wood@example.com" },
];
const mathew = { personId: 2, email: "mathew.campbell@example.com" };

const signInState = (personId, column, value) =>
    db(
        `INSERT INTO person_signin_psi (psi_per_ID, ${column}) VALUES (?, ?) ON DUPLICATE KEY UPDATE ${column} = VALUES(${column})`,
        [personId, value],
    );

const cases = [
    {
        title: "an email two people who may sign in share",
        email: "tony.wade@example.com",
        reason: "shared_email",
        personId: null,
        setup: () => {
            setConfig("sMemberSignInFamilyRoles", "0,1,2");
            setConfig("sMemberSignInClassifications", "0,1,2");
        },
    },
    { title: "a deceased person", email: "laurie.spencer@example.com", reason: "deceased", personId: 19 },
    { title: "an inactive person", email: "inactive.user@example.com", reason: "inactive", personId: 911 },
    { title: "a person whose family is deactivated", email: "kathryn.ray@example.com", reason: "family_inactive", personId: 18 },
    { title: "a person awaiting review", email: "mark.smith@example.com", reason: "needs_review", personId: 104 },
    {
        title: "a person with Disable sign-in on",
        email: mathew.email,
        reason: "sign_in_disabled",
        personId: mathew.personId,
        setup: () =>
            cy.makePrivateAdminAPICall("POST", `/admin/api/person/${mathew.personId}/sign-in/disable`, { reason: "Refusals spec" }, 200),
    },
    { title: "a classification that is not ticked", email: "natalie.diaz@example.com", reason: "classification", personId: 43 },
    {
        title: "a person under the minimum age",
        email: mathew.email,
        reason: "under_minimum_age",
        personId: mathew.personId,
        setup: () => setConfig("iMemberSignInMinAge", "120"),
    },
    {
        title: "an unconfirmed address moved from the family email",
        email: mathew.email,
        reason: "email_unconfirmed",
        personId: mathew.personId,
        setup: () => {
            signInState(mathew.personId, "psi_UnconfirmedEmail", mathew.email);
            signInState(mathew.personId, "psi_UnconfirmedSource", "family_email");
        },
    },
    { title: "an address nobody has", email: "nobody.10463@example.com", reason: "no_match", personId: null },
];

const officeNotices = (count = 1) => waitForMail(office[0].email, count);

const noticeText = (notice) => cy.task("mail:get", { id: notice.ID }).its("body.Text");

describe("Member Portal sign-in refusals", () => {
    const saved = {};

    before(() => {
        cy.task("mail:available").its("available").should("eq", true);
        useMemberSignIn(saved, {
            sMemberSignInNotificationRecipientIDs: office.map((person) => person.personId).join(","),
        });
    });

    beforeEach(() => {
        cy.clearCookies();
        resetEmailSignIn([mathew.personId]);
        setConfig("sMemberSignInFamilyRoles", "1,2");
        setConfig("sMemberSignInClassifications", "1,2");
        setConfig("iMemberSignInMinAge", "13");
    });

    afterEach(() => {
        db(
            "UPDATE person_signin_psi SET psi_SignInDisabled = 0, psi_DisabledBy_usr_ID = NULL, psi_DisabledAt = NULL, psi_DisabledReason = NULL, psi_UnconfirmedEmail = NULL, psi_UnconfirmedSource = NULL WHERE psi_per_ID = ?",
            [mathew.personId],
        );
    });

    after(() => {
        restoreSettings(saved);
    });

    cases.forEach(({ title, email, reason, personId, setup }) => {
        it(`refuses ${title} without telling them, and tells the office once`, () => {
            setup?.();
            lastAuditId().then((startId) => {
                askForEmail(email).then(expectAnswerScreen);

                db("SELECT COUNT(*) AS n FROM person_signin_request_psr WHERE psr_Email = ?", [email])
                    .its("0.n")
                    .should("eq", 0);
                auditSince(startId).then((rows) => {
                    expect(rows.map((row) => row.psa_Event)).to.deep.equal(["refused"]);
                    expect(rows[0].psa_Reason).to.eq(reason);
                    expect(rows[0].psa_Email).to.eq(email);
                    expect(rows[0].psa_per_ID).to.eq(personId);
                });

                officeNotices().then((notices) => {
                    expect(notices).to.have.length(1);
                    expect(notices[0].Subject).to.include("Someone could not sign in to the Member Portal");
                    expect(notices[0].To.map((to) => to.Address).sort()).to.deep.equal(office.map((p) => p.email).sort());
                    noticeText(notices[0]).should("include", email);
                });
                mailTo(email).should("have.length", 0);
            });
        });
    });

    it("sends no second office notice for the same address within the hour", () => {
        lastAuditId().then((startId) => {
            askForEmail("natalie.diaz@example.com").then(expectAnswerScreen);
            askForEmail("Natalie.Diaz@example.com ").then(expectAnswerScreen);
            auditSince(startId).then((rows) => {
                expect(rows.map((row) => `${row.psa_Event}:${row.psa_Reason}`)).to.deep.equal([
                    "refused:classification",
                    "refused:classification",
                ]);
            });

            askForEmail("nobody.10463@example.com").then(expectAnswerScreen);
            officeNotices(2).then((notices) => {
                expect(notices).to.have.length(2);
                noticeText(notices[1]).should("include", "Email address typed: natalie.diaz@example.com");
                noticeText(notices[0]).should("include", "Email address typed: nobody.10463@example.com");
            });
        });
    });

    it("words the office notice with the typed address, the reason and the person", () => {
        askForEmail("natalie.diaz@example.com");
        officeNotices().then((notices) => {
            cy.task("mail:get", { id: notices[0].ID }).then(({ body }) => {
                expect(body.Text).to.include("Email address typed: natalie.diaz@example.com");
                expect(body.Text).to.include("Reason: Their classification may not sign in");
                expect(body.Text).to.include("People with this email: Natalie Diaz");
                expect(body.HTML).to.include("/people/view/43");
            });
        });
    });

    it("sends no notice when the office list is empty", () => {
        setConfig("sMemberSignInNotificationRecipientIDs", "");
        askForEmail("natalie.diaz@example.com").then(expectAnswerScreen);
        mailTo(office[0].email).should("have.length", 0);
        setConfig("sMemberSignInNotificationRecipientIDs", office.map((person) => person.personId).join(","));
    });
});
