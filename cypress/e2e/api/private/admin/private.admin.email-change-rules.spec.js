/// <reference types="cypress" />

import {
    askForEmail,
    postCode,
    resetEmailSignIn,
    restoreSettings,
    signInEmail,
    useMemberSignIn,
    waitForMail,
} from "../../../../support/member-sign-in";

/**
 * Member sign-in row 10 (#10463): the sign-in email is a credential
 * (docs/member-sign-in-design.md §9). Staff change it in the person editor;
 * whether an address may sign in is tried at /portal/login and read from
 * GET /api/person/{id}/sign-in.
 *
 * Seed people: 2 Mathew (head, Member, no account), 100 Lena (spouse, no
 * account), 3 Tony (a staff account).
 * judith.matthews (95) has Edit Records and is not an administrator.
 *
 * The family email move, devices, passkeys, grants and the audit have no HTTP
 * surface until rows 7, 8 and 9, so they are set and read with cy.dbQuery().
 */

const MATHEW = { id: 2, email: "mathew.campbell@example.com" };
const LENA = { id: 100, email: "lena.walker@example.com" };
const TONY = { id: 3, email: "tony.wade@example.com" };
const JUDITH = { id: 95, username: "judith.matthews@example.com", password: "noMoney$" };

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const lastAuditId = () =>
    db("SELECT COALESCE(MAX(psa_ID), 0) AS id FROM portal_signin_audit_psa").then((rows) => Number(rows[0].id));

const auditSince = (personId, afterId) =>
    db("SELECT * FROM portal_signin_audit_psa WHERE psa_per_ID = ? AND psa_ID > ? ORDER BY psa_ID", [personId, afterId]);

const status = (personId) => cy.makePrivateAdminAPICall("GET", `/api/person/${personId}/sign-in`, null, 200).its("body");

const signIn = ({ username, password }) => {
    cy.clearCookies();
    return cy
        .request({ method: "POST", url: "/admin/", form: true, followRedirect: false, body: { User: username, Password: password } })
        .its("status")
        .should("eq", 302);
};

/** Runs `call` in another cookie jar and puts the browser's own session back afterwards. */
const keepingSession = (call) =>
    cy.getCookies().then((cookies) => {
        call();
        cy.clearCookies();
        cookies.forEach(({ name, value, path, domain, httpOnly, secure, sameSite }) => {
            cy.setCookie(name, value, { path, domain, httpOnly, secure, sameSite });
        });
    });

/**
 * Saves the person editor as `user`, with every field as the form shows it
 * except the email. Answers the response: 302 to the person page when saved.
 */
const saveEmailInPersonEditor = (personId, email, user = JUDITH) => {
    signIn(user);
    return cy.request(`/PersonEditor.php?PersonID=${personId}`).then((page) => {
        const form = Cypress.$(new DOMParser().parseFromString(page.body, "text/html")).find("#personEditor");
        expect(form.length, "person editor form").to.eq(1);
        const body = {};
        form.serializeArray().forEach(({ name, value }) => {
            body[name] = value;
        });
        body.Email = email;
        body.PersonSubmit = "";
        return cy.request({
            method: "POST",
            url: `/PersonEditor.php?PersonID=${personId}`,
            form: true,
            body,
            followRedirect: false,
        });
    });
};

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

const openLink = (path) => {
    cy.clearCookies();
    return cy.request(path).then((page) => {
        const state = page.body.match(/data-state="([a-z]+)"/);
        return { state: state ? state[1] : null, body: page.body };
    });
};

/** Presses the confirm page's button, then presses it again: answers both pages' states. */
const useLinkTwice = (path) =>
    openLink(path).then(({ body }) => {
        const token = body.match(/name="csrf_token"\s+value="([^"]+)"/i);
        expect(token, "csrf token on the confirm page").to.not.be.null;
        const press = () =>
            cy
                .request({ method: "POST", url: path, form: true, body: { csrf_token: token[1] } })
                .then((page) => page.body.match(/data-state="([a-z]+)"/)[1]);
        return press().then((first) => press().then((second) => [first, second]));
    });

/** Signs in at /portal/login with the emailed code, in the current cookie jar. */
const signInByEmail = (address) => {
    cy.clearCookies();
    resetEmailSignIn();
    askForEmail(address);
    return signInEmail(address).then(({ code }) => postCode(code));
};

/** Whether /portal/login emails a sign-in code to the address. The email follows the answer, once the request is audited. */
const signInCodeSentTo = (address) => {
    cy.clearCookies();
    resetEmailSignIn();
    return lastAuditId().then((startId) => {
        askForEmail(address);
        return db("SELECT COUNT(*) AS n FROM portal_signin_audit_psa WHERE psa_ID > ? AND psa_Event = 'email_requested' AND psa_Email = ?", [
            startId,
            address,
        ]).then((rows) =>
            Number(rows[0].n) === 0
                ? false
                : waitForMail(address).then((messages) => messages.some((m) => (m.Subject || "").includes("Your Member Portal sign-in code"))),
        );
    });
};

const markFamilyEmailMoved = (person, familyId) => {
    db(
        "INSERT INTO person_signin_psi (psi_per_ID, psi_UnconfirmedEmail, psi_UnconfirmedSource, psi_UnconfirmedAt) VALUES (?, ?, 'family_email', NOW()) " +
            "ON DUPLICATE KEY UPDATE psi_UnconfirmedEmail = VALUES(psi_UnconfirmedEmail), psi_UnconfirmedSource = VALUES(psi_UnconfirmedSource)",
        [person.id, person.email],
    );
    db(
        "INSERT INTO family_email_move_fem (fem_fam_ID, fem_Email, fem_Outcome, fem_per_ID, fem_NeedsConfirm, fem_DateMoved) VALUES (?, ?, 'head', ?, 1, NOW())",
        [familyId, person.email, person.id],
    );
};

const giveGrant = (personId) =>
    db(
        "INSERT INTO portal_grant_pgr (pgr_per_ID, pgr_Capability, pgr_Reason, pgr_GrantedBy_usr_ID, pgr_GrantedAt) VALUES (?, 'volunteer.team.manage', 'Email change spec', 1, NOW())",
        [personId],
    );

describe("Sign-in email changes by staff, imports and registration (§9)", () => {
    const saved = {};

    let lastEmailLogId;

    before(() => {
        useMemberSignIn(saved);
        db("SELECT COALESCE(MAX(eml_ID), 0) AS id FROM email_log_eml").then((rows) => {
            lastEmailLogId = Number(rows[0].id);
        });
    });

    const resetPeople = () => {
        cy.clearCookies();
        [MATHEW, LENA, TONY].forEach(({ id, email }) => {
            db("UPDATE person_per SET per_Email = ? WHERE per_ID = ?", [email, id]);
        });
        const ids = [MATHEW.id, LENA.id, TONY.id];
        db("DELETE FROM person_signin_psi WHERE psi_per_ID IN (?, ?, ?)", ids);
        db("DELETE FROM person_email_change_pec WHERE pec_per_ID IN (?, ?, ?)", ids);
        db("DELETE FROM person_device_pdv WHERE pdv_per_ID IN (?, ?, ?)", ids);
        db("DELETE FROM person_passkey_ppk WHERE ppk_per_ID IN (?, ?, ?)", ids);
        db("DELETE FROM portal_grant_pgr WHERE pgr_Reason = 'Email change spec'");
        db("DELETE FROM family_email_move_fem WHERE fem_per_ID IN (?, ?, ?)", ids);
        db("DELETE FROM person_signin_request_psr WHERE psr_per_ID IN (?, ?, ?)", ids);
    };

    beforeEach(() => {
        resetPeople();
        cy.task("mail:clear");
    });

    afterEach(resetPeople);

    after(() => {
        restoreSettings(saved);
        // Other specs read the seeded email history of these people.
        db("DELETE FROM email_log_eml WHERE eml_ID > ?", [lastEmailLogId]);
    });

    it("before the first sign-in, a staff change is the address on record: it signs in at once and is audited", () => {
        const newEmail = `mathew.${Date.now()}@example.com`;
        lastAuditId().then((startId) => {
            saveEmailInPersonEditor(MATHEW.id, newEmail).its("status").should("eq", 302);

            status(MATHEW.id).then((body) => {
                expect(body.maySignIn).to.eq(true);
                expect(body.unconfirmedEmail).to.be.null;
            });
            mailTo(MATHEW.email).should("have.length", 0);
            signInCodeSentTo(newEmail).should("eq", true);
            auditSince(MATHEW.id, startId).then((rows) => {
                expect(rows[0].psa_Event).to.eq("email_changed");
                expect(rows[0].psa_Actor_usr_ID).to.eq(JUDITH.id);
                expect(rows[0].psa_Email).to.eq(newEmail);
                expect(rows[0].psa_Detail).to.eq(`from=${MATHEW.email}; via=staff`);
            });
        });
    });

    describe("after the first sign-in", () => {
        const newEmail = `lena.replaced.${Date.now()}@example.com`;
        const randomHash = () => Array.from({ length: 64 }, () => Math.floor(Math.random() * 16).toString(16)).join("");

        const replaceSignedInEmail = () => {
            signInByEmail(LENA.email);
            cy.task("mail:clear");
            saveEmailInPersonEditor(LENA.id, newEmail).its("status").should("eq", 302);
            cy.clearCookies();
        };

        it("ends every session and remembered device, keeps passkeys, tells the old address and leaves the new one unconfirmed", () => {
            lastAuditId().then((startId) => {
                signInByEmail(LENA.email);
                cy.request("/api/portal/me").its("body.profile.id").should("eq", LENA.id);
                db("SELECT psi_ProvenEmail FROM person_signin_psi WHERE psi_per_ID = ?", [LENA.id]).its("0.psi_ProvenEmail").should("eq", LENA.email);
                db(
                    "INSERT INTO person_device_pdv (pdv_per_ID, pdv_TokenHash, pdv_Kind, pdv_CreatedAt, pdv_ExpiresAt) VALUES (?, ?, 'browser', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))",
                    [LENA.id, randomHash()],
                );
                db(
                    "INSERT INTO person_passkey_ppk (ppk_per_ID, ppk_CredentialId, ppk_CredentialHash, ppk_PublicKey, ppk_CreatedAt) VALUES (?, 'cypress-credential', ?, 'cypress-key', NOW())",
                    [LENA.id, randomHash()],
                );
                cy.task("mail:clear");

                keepingSession(() => {
                    saveEmailInPersonEditor(LENA.id, newEmail).its("status").should("eq", 302);
                });

                cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
                db("SELECT pdv_RevokedAt FROM person_device_pdv WHERE pdv_per_ID = ?", [LENA.id]).its("0.pdv_RevokedAt").should("not.be.null");
                db("SELECT COUNT(*) AS n FROM person_passkey_ppk WHERE ppk_per_ID = ?", [LENA.id]).its("0.n").should("eq", 1);
                db("SELECT psi_UnconfirmedEmail, psi_UnconfirmedSource FROM person_signin_psi WHERE psi_per_ID = ?", [LENA.id]).then((rows) => {
                    expect(rows[0].psi_UnconfirmedEmail).to.eq(newEmail);
                    expect(rows[0].psi_UnconfirmedSource).to.eq("staff");
                });
                status(LENA.id).then((body) => {
                    expect(body.reason).to.eq("email_unconfirmed");
                    expect(body.unconfirmedEmail).to.eq(newEmail);
                    expect(body.unconfirmedSource).to.eq("staff");
                });

                mailTo(LENA.email).then((messages) => {
                    expect(messages).to.have.length(1);
                    expect(messages[0].Subject).to.contain("Your email address was changed");
                    cy.task("mail:get", { id: messages[0].ID }).its("body.Text").should("contain", newEmail);
                });
                mailTo(newEmail).should("have.length", 0);
                signInCodeSentTo(newEmail).should("eq", false);

                auditSince(LENA.id, startId).then((rows) => {
                    const changed = rows.find((row) => row.psa_Event === "email_changed");
                    expect(changed.psa_Actor_usr_ID).to.eq(JUDITH.id);
                    expect(changed.psa_Email).to.eq(newEmail);
                    expect(changed.psa_Detail).to.eq(`from=${LENA.email}; via=staff; unconfirmed=yes; devices=1`);
                    const ended = rows.filter((row) => row.psa_Event === "session_ended");
                    expect(ended.map((row) => row.psa_Reason)).to.include("session_version");
                });
            });
        });

        it("the link in the notice to the proven address confirms the new address", () => {
            replaceSignedInEmail();
            lastAuditId().then((startId) => {
                mailTo(LENA.email).then((messages) => confirmLinkIn(messages[0])).then((path) => {
                    openLink(path).then(({ state, body }) => {
                        expect(state).to.eq("confirm");
                        expect(body).to.contain(newEmail);
                    });
                    status(LENA.id).its("reason").should("eq", "email_unconfirmed");

                    useLinkTwice(path).should("deep.eq", ["done", "invalid"]);
                    signInCodeSentTo(newEmail).should("eq", true);
                    status(LENA.id).then((body) => {
                        expect(body.maySignIn).to.eq(true);
                        expect(body.unconfirmedEmail).to.be.null;
                    });
                    openLink(path).its("state").should("eq", "invalid");
                });
                auditSince(LENA.id, startId).then((all) => {
                    const rows = all.filter((row) => row.psa_Event.startsWith("email_c"));
                    expect(rows).to.have.length(1);
                    expect(rows[0].psa_Event).to.eq("email_confirmed");
                    expect(rows[0].psa_Actor_usr_ID).to.be.null;
                    expect(rows[0].psa_Email).to.eq(newEmail);
                    expect(rows[0].psa_Detail).to.eq("via=link_to_previous_address; source=staff");
                });
            });
        });

        it("staff with Edit Records can't confirm a replacement address; an administrator can", () => {
            replaceSignedInEmail();
            cy.makePrivateEditRecordsAPICall("GET", `/api/person/${LENA.id}/sign-in`, null, 200).then(({ body }) => {
                expect(body.canConfirmEmail).to.eq(false);
                expect(body.confirmEmailRefusal).to.contain("Only an administrator");
            });
            cy.makePrivateEditRecordsAPICall("POST", `/api/person/${LENA.id}/sign-in/confirm-email`, { email: newEmail }, 403);
            cy.makePrivateAdminAPICall("POST", `/api/person/${LENA.id}/sign-in/confirm-email`, { email: "someone.else@example.com" }, 409);
            status(LENA.id).its("reason").should("eq", "email_unconfirmed");

            signInCodeSentTo(newEmail).should("eq", false);
            lastAuditId().then((startId) => {
                cy.makePrivateAdminAPICall("POST", `/api/person/${LENA.id}/sign-in/confirm-email`, { email: newEmail }, 200);
                status(LENA.id).its("maySignIn").should("eq", true);
                signInCodeSentTo(newEmail).should("eq", true);
                auditSince(LENA.id, startId).then((rows) => {
                    expect(rows.filter((row) => row.psa_Event === "email_confirmed").map((row) => `${row.psa_Actor_usr_ID}:${row.psa_Detail}`)).to.deep.eq([
                        "1:via=person_page; source=staff",
                    ]);
                });
            });
            cy.makePrivateAdminAPICall("POST", `/api/person/${LENA.id}/sign-in/confirm-email`, { email: newEmail }, 409);
        });
    });

    describe("an address moved from the family email", () => {
        it("doesn't sign in, saving it again doesn't confirm it, and staff with Edit Records may confirm it", () => {
            markFamilyEmailMoved(MATHEW, 1);
            status(MATHEW.id).its("reason").should("eq", "email_unconfirmed");

            lastAuditId().then((startId) => {
                saveEmailInPersonEditor(MATHEW.id, MATHEW.email.toUpperCase()).its("status").should("eq", 302);
                status(MATHEW.id).then((body) => {
                    expect(body.reason).to.eq("email_unconfirmed");
                    expect(body.unconfirmedSource).to.eq("family_email");
                });
                auditSince(MATHEW.id, startId).should("have.length", 0);

                cy.makePrivateEditRecordsAPICall("GET", `/api/person/${MATHEW.id}/sign-in`, null, 200)
                    .its("body.canConfirmEmail")
                    .should("eq", true);
                signInCodeSentTo(MATHEW.email).should("eq", false);
                cy.makePrivateEditRecordsAPICall("POST", `/api/person/${MATHEW.id}/sign-in/confirm-email`, { email: MATHEW.email }, 200);
                status(MATHEW.id).its("maySignIn").should("eq", true);
                signInCodeSentTo(MATHEW.email).should("eq", true);
                db("SELECT fem_NeedsConfirm FROM family_email_move_fem WHERE fem_per_ID = ?", [MATHEW.id]).its("0.fem_NeedsConfirm").should("eq", 0);
                auditSince(MATHEW.id, startId).then((rows) => {
                    expect(rows.filter((row) => row.psa_Event.startsWith("email_c")).map((row) => `${row.psa_Event}:${row.psa_Actor_usr_ID}:${row.psa_Detail}`)).to.deep.eq([
                        `email_confirmed:${JUDITH.id}:via=person_page; source=family_email`,
                    ]);
                });
            });
        });
    });

    describe("a person with a staff account or given portal grants", () => {
        it("refuses a change by staff who are not administrators, and lets an administrator make it", () => {
            saveEmailInPersonEditor(TONY.id, "tony.elsewhere@example.com").then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.contain("Only an administrator can change the email");
            });
            cy.makePrivateAdminAPICall("GET", `/api/person/${TONY.id}`, null, 200).its("body.Email").should("eq", TONY.email);

            cy.readEnv("admin.username").then((username) =>
                cy.readEnv("admin.password").then((password) => {
                    saveEmailInPersonEditor(TONY.id, "tony.elsewhere@example.com", { username, password })
                        .its("status")
                        .should("eq", 302);
                }),
            );
            cy.makePrivateAdminAPICall("GET", `/api/person/${TONY.id}`, null, 200)
                .its("body.Email")
                .should("eq", "tony.elsewhere@example.com");
        });

        it("lets only an administrator change or confirm the email of a person with a given grant", () => {
            giveGrant(MATHEW.id);
            markFamilyEmailMoved(MATHEW, 1);

            saveEmailInPersonEditor(MATHEW.id, "mathew.elsewhere@example.com").then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.contain("Only an administrator can change the email");
            });
            cy.makePrivateEditRecordsAPICall("GET", `/api/person/${MATHEW.id}/sign-in`, null, 200).then(({ body }) => {
                expect(body.canConfirmEmail).to.eq(false);
                expect(body.confirmEmailRefusal).to.contain("staff account or portal permissions");
            });
            cy.makePrivateEditRecordsAPICall("POST", `/api/person/${MATHEW.id}/sign-in/confirm-email`, { email: MATHEW.email }, 403);
            cy.makePrivateAdminAPICall("POST", `/api/person/${MATHEW.id}/sign-in/confirm-email`, { email: MATHEW.email }, 200);
            status(MATHEW.id).its("maySignIn").should("eq", true);
        });
    });

    it("refuses the confirm endpoint without Edit Records or the CSRF token", () => {
        markFamilyEmailMoved(MATHEW, 1);
        cy.makePrivateNoPermAPICall("POST", `/api/person/${MATHEW.id}/sign-in/confirm-email`, { email: MATHEW.email }, 403);
        signIn(JUDITH);
        cy.request({
            method: "POST",
            url: `/api/person/${MATHEW.id}/sign-in/confirm-email`,
            body: { email: MATHEW.email },
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 403);
        status(MATHEW.id).its("reason").should("eq", "email_unconfirmed");
    });

    it("a registration form's address is audited as given by registration", () => {
        const email = `registered.${Date.now()}@example.com`;
        lastAuditId().then((startId) => {
            cy.request({
                method: "POST",
                url: "/api/public/register/person",
                body: { firstName: "Reggie", lastName: "Emailchange", gender: "1", email },
            }).then(({ body }) => {
                const personId = Number(body.Id);
                auditSince(personId, startId).then((rows) => {
                    expect(rows).to.have.length(1);
                    expect(rows[0].psa_Event).to.eq("email_changed");
                    expect(rows[0].psa_Actor_usr_ID).to.be.null;
                    expect(rows[0].psa_Email).to.eq(email);
                    expect(rows[0].psa_Detail).to.eq("from=; via=registration");
                });
                cy.cleanupPeople([personId]);
            });
        });
    });
});
