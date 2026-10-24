/// <reference types="cypress" />

import { askForEmail, postCode, resetEmailSignIn, restoreSettings, signInEmail, useMemberSignIn } from "../../../../support/member-sign-in";

/**
 * Member sign-in row 10 (#10463): a member changes their own email in the
 * portal (docs/member-sign-in-design.md §9). The new address gets a link and
 * the change happens when it is used; the old address gets a notice.
 *
 * Persona: Lena Black (person 100, family 20, spouse), signed in by email at
 * /portal/login. Amanda Black (99) is in her family.
 * Given grants, an expired link, the proven email and the audit have no HTTP
 * surface until rows 5 and 9, so they are set and read with cy.dbQuery().
 */

const LENA = { id: 100, email: "lena.walker@example.com" };
const AMANDA = { id: 99, email: "amanda.black@example.com" };

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const lastAuditId = () =>
    db("SELECT COALESCE(MAX(psa_ID), 0) AS id FROM portal_signin_audit_psa").then((rows) => Number(rows[0].id));

const signInToPortal = () => {
    cy.clearCookies();
    resetEmailSignIn([LENA.id]);
    askForEmail(LENA.email);
    signInEmail(LENA.email).then(({ code }) => postCode(code));
    cy.request("/api/portal/me").its("body.profile.id").should("eq", LENA.id);
    cy.task("mail:clear");
};

const csrfToken = () =>
    cy.request("/portal/profile/edit").then((response) => response.body.match(/name="csrf_token"\s+value="([^"]+)"/i)[1]);

const saveProfile = (body) =>
    csrfToken().then((token) =>
        cy.request({
            method: "POST",
            url: "/api/portal/me",
            body,
            failOnStatusCode: false,
            headers: { "content-type": "application/json", "X-CSRF-Token": token },
        }),
    );

const profile = () => cy.request("/api/portal/me").its("body.profile");

const mailTo = (address) =>
    cy.task("mail:list", { limit: 100 }).then((result) =>
        (result.body?.messages || []).filter((message) =>
            (message.To || []).some((to) => (to.Address || "").toLowerCase() === address.toLowerCase()),
        ),
    );

const confirmLinkIn = (message) =>
    cy.task("mail:get", { id: message.ID }).then((result) => {
        const match = (result.body?.HTML || "").match(/\/portal\/email\/confirm\/[0-9a-f]{64}/);
        expect(match, "confirm link in the email").to.not.be.null;
        return match[0];
    });

/** Opens a link in a fresh browser, as a mail client would, keeping the member's session. */
const inFreshBrowser = (call) =>
    cy.getCookies().then((cookies) => {
        cy.clearCookies();
        call();
        cy.clearCookies();
        cookies.forEach(({ name, value, path, domain, httpOnly, secure, sameSite }) => {
            cy.setCookie(name, value, { path, domain, httpOnly, secure, sameSite });
        });
    });

const stateOf = (body) => body.match(/data-state="([a-z]+)"/)[1];

const openLink = (path) => cy.request(path).then((page) => ({ state: stateOf(page.body), body: page.body }));

const pressConfirm = (path, body) => {
    const token = body.match(/name="csrf_token"\s+value="([^"]+)"/i)[1];
    return cy.request({ method: "POST", url: path, form: true, body: { csrf_token: token } }).then((page) => stateOf(page.body));
};

describe("A member changes their own email in the portal (§9)", () => {
    const saved = {};

    let lastEmailLogId;

    before(() => {
        useMemberSignIn(saved);
        db("SELECT COALESCE(MAX(eml_ID), 0) AS id FROM email_log_eml").then((rows) => {
            lastEmailLogId = Number(rows[0].id);
        });
    });

    after(() => {
        restoreSettings(saved);
        // Other specs read the seeded email history of these people.
        db("DELETE FROM email_log_eml WHERE eml_ID > ?", [lastEmailLogId]);
    });

    beforeEach(() => {
        cy.task("mail:clear");
        cy.expireSignInThrottle("email-change.person");
    });

    afterEach(() => {
        cy.clearCookies();
        db("UPDATE person_per SET per_Email = ? WHERE per_ID = ?", [LENA.email, LENA.id]);
        db("UPDATE person_per SET per_Email = ? WHERE per_ID = ?", [AMANDA.email, AMANDA.id]);
        db("DELETE FROM person_signin_psi WHERE psi_per_ID = ?", [LENA.id]);
        db("DELETE FROM person_email_change_pec WHERE pec_per_ID = ?", [LENA.id]);
        db("DELETE FROM portal_grant_pgr WHERE pgr_Reason = 'Email change spec'");
        db("DELETE FROM person_signin_request_psr WHERE psr_per_ID = ?", [LENA.id]);
        cy.expireSignInThrottle("email-change.person");
    });

    it("sends a link to the new address and a notice to the old one, and changes the email only when the link is used", () => {
        const newEmail = `lena.own.${Date.now()}@example.com`;
        signInToPortal();
        lastAuditId().then((startId) => {
            saveProfile({ email: newEmail }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.pendingEmail).to.eq(newEmail);
                expect(response.body.updated).to.deep.eq([]);
                expect(response.body.profile.email).to.eq(LENA.email);
                expect(response.body.profile.pendingEmail).to.eq(newEmail);
            });

            mailTo(LENA.email).then((messages) => {
                expect(messages).to.have.length(1);
                expect(messages[0].Subject).to.contain("A change of your email address was requested");
                cy.task("mail:get", { id: messages[0].ID }).its("body.Text").should("contain", newEmail);
            });
            mailTo(newEmail).then((messages) => {
                expect(messages).to.have.length(1);
                expect(messages[0].Subject).to.contain("Confirm your email address");
                confirmLinkIn(messages[0]).then((path) => {
                    inFreshBrowser(() => {
                        openLink(path).then(({ state, body }) => {
                            expect(state).to.eq("confirm");
                            expect(body).to.contain(newEmail);
                            db("SELECT per_Email FROM person_per WHERE per_ID = ?", [LENA.id]).its("0.per_Email").should("eq", LENA.email);
                            pressConfirm(path, body).should("eq", "done");
                            pressConfirm(path, body).should("eq", "invalid");
                        });
                    });
                });
            });

            profile().then((body) => {
                expect(body.email).to.eq(newEmail);
                expect(body.pendingEmail).to.eq("");
            });
            db("SELECT psi_ProvenEmail FROM person_signin_psi WHERE psi_per_ID = ?", [LENA.id]).its("0.psi_ProvenEmail").should("eq", newEmail);
            db("SELECT * FROM portal_signin_audit_psa WHERE psa_per_ID = ? AND psa_ID > ? AND psa_Event LIKE 'email_%'", [LENA.id, startId]).then((rows) => {
                expect(rows).to.have.length(1);
                expect(rows[0].psa_Event).to.eq("email_changed");
                expect(rows[0].psa_Email).to.eq(newEmail);
                expect(rows[0].psa_Detail).to.eq(`from=${LENA.email}; via=portal`);
            });
        });
    });

    it("a new request cancels the open one, and an expired link can't be used", () => {
        const first = `lena.first.${Date.now()}@example.com`;
        const second = `lena.second.${Date.now()}@example.com`;
        signInToPortal();
        saveProfile({ email: first }).its("status").should("eq", 200);
        saveProfile({ email: second }).its("body.pendingEmail").should("eq", second);

        mailTo(first).then((messages) => confirmLinkIn(messages[0])).then((path) => {
            inFreshBrowser(() => openLink(path).its("state").should("eq", "invalid"));
        });
        mailTo(second).then((messages) => confirmLinkIn(messages[0])).then((path) => {
            db("UPDATE person_email_change_pec SET pec_ExpiresAt = '2000-01-01 00:00:00' WHERE pec_per_ID = ? AND pec_UsedAt IS NULL AND pec_CancelledAt IS NULL", [LENA.id]);
            inFreshBrowser(() => openLink(path).its("state").should("eq", "invalid"));
        });
        profile().then((body) => {
            expect(body.email).to.eq(LENA.email);
            expect(body.pendingEmail).to.eq("");
        });
    });

    it("saving the address on record again sends nothing and changes nothing", () => {
        signInToPortal();
        saveProfile({ email: ` ${LENA.email.toUpperCase()} ` }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.pendingEmail).to.eq("");
            expect(response.body.profile.email).to.eq(LENA.email);
        });
        cy.task("mail:list", { limit: 10 }).its("body.messages").should("have.length", 0);
    });

    it("refuses an empty or invalid address, and saves the other fields only when the email is accepted", () => {
        signInToPortal();
        saveProfile({ email: "" }).its("status").should("eq", 400);
        saveProfile({ email: "not-an-address", middleName: "Refused" }).then((response) => {
            expect(response.status).to.eq(400);
            expect(response.body.failures[0]).to.match(/^email: /);
        });
        profile().its("middleName").should("not.eq", "Refused");
    });

    it("a person with a given portal grant can't change their own email while the grant lasts", () => {
        db(
            "INSERT INTO portal_grant_pgr (pgr_per_ID, pgr_Capability, pgr_Reason, pgr_GrantedBy_usr_ID, pgr_GrantedAt) VALUES (?, 'volunteer.team.manage', 'Email change spec', 1, NOW())",
            [LENA.id],
        );
        signInToPortal();
        profile().its("emailLocked").should("eq", true);
        cy.request("/portal/profile/edit").its("body").should("match", /id="portal-email"[^>]*readonly/);
        saveProfile({ email: `lena.granted.${Date.now()}@example.com` }).its("status").should("eq", 403);
        cy.task("mail:list", { limit: 10 }).its("body.messages").should("have.length", 0);

        db("UPDATE portal_grant_pgr SET pgr_EndsAt = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE pgr_Reason = 'Email change spec'");
        profile().its("emailLocked").should("eq", false);
    });

    it("a family adult can't change another member's email", () => {
        signInToPortal();
        saveProfile({ personId: AMANDA.id, id: AMANDA.id, email: "household.change@example.com" }).its("status").should("eq", 200);
        csrfToken().then((token) =>
            cy.request({
                method: "POST",
                url: "/api/portal/family",
                body: { email: "household.change@example.com" },
                headers: { "content-type": "application/json", "X-CSRF-Token": token },
            }),
        );
        profile().its("pendingEmail").should("eq", "household.change@example.com");
        mailTo(AMANDA.email).should("have.length", 0);
        cy.makePrivateAdminAPICall("GET", `/api/person/${AMANDA.id}`, null, 200).its("body.Email").should("eq", AMANDA.email);
    });

    it("limits how often a member may ask in an hour", () => {
        signInToPortal();
        Cypress._.times(5, (n) => {
            saveProfile({ email: `lena.many.${n}.${Date.now()}@example.com` }).its("status").should("eq", 200);
        });
        saveProfile({ email: `lena.many.last.${Date.now()}@example.com` }).its("status").should("eq", 429);
    });
});
