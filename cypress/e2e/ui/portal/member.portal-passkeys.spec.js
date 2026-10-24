/// <reference types="cypress" />

import {
    askForEmail,
    auditSince,
    db,
    lastAuditId,
    mailTo,
    resetEmailSignIn,
    restoreSettings,
    setConfig,
    signInEmail,
    useMemberSignIn,
} from "../../../support/member-sign-in";
import {
    addVirtualAuthenticator,
    authenticatorCredentials,
    removeVirtualAuthenticator,
    setUserVerified,
} from "../../../support/passkeys";

/**
 * Member sign-in row 8 (#10463): passkeys (docs/member-sign-in-design.md §6.3,
 * §5, §9, §11.1, §14, §17 "Passkeys"), with Chrome's virtual authenticator.
 * Seed person 78, Norman Cooper: head of household, Member, born 1967, no
 * staff account. judith.matthews has Edit Records and is not an administrator.
 */

const member = { personId: 78, firstName: "Norman", email: "norman.cooper@example.com" };
const JUDITH = { username: "judith.matthews@example.com", password: "noMoney$" };
const PASSKEY_FAILURES = "portal-passkey.ip";
const isSessionCookie = (cookie) => /^CRM-[0-9a-f]{32}$/.test(cookie.name);

const base64ToBase64Url = (value) => value.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
const textToBase64Url = (text) => base64ToBase64Url(btoa(text));
const events = (rows) => rows.map((row) => `${row.psa_Event}:${row.psa_Method ?? ""}:${row.psa_Reason ?? ""}`);
const passkeysOf = (personId) => db("SELECT * FROM person_passkey_ppk WHERE ppk_per_ID = ? ORDER BY ppk_ID", [personId]);

const signInWithCode = ({ remember }) => {
    cy.task("mail:clear");
    askForEmail(member.email);
    signInEmail(member.email).then(({ code }) => {
        cy.visit("/portal/login/code");
        if (remember) {
            cy.get("#portalRememberDevice").check();
        }
        cy.get("#portalSignInCode").type(code);
        cy.get("#portalSignInCodeSubmit").click();
        cy.location("pathname").should("match", /\/portal\/$/);
    });
};

const addPasskeyOnDevicesPage = () => {
    cy.visit("/portal/devices");
    cy.get("#portal-passkey-warning").should("contain.text", "never on a shared or public one");
    cy.get("#portal-passkey-add").click();
    cy.get(".portal-flash").should("contain.text", "Your passkey was added.");
    cy.get("#portal-passkeys-table [data-passkey-row]").should("have.length", 1);
};

const signInWithPasskey = () => {
    cy.clearCookies();
    cy.visit("/portal/login");
    cy.get("#portalPasskeySignIn").should("be.visible");
    cy.get("#portalPasskeySignInButton").click();
};

const expectPasskeyRefused = () => {
    cy.get("#portalSignInFailed").should("be.visible");
    cy.get("#portalSignInFailedPasskey").should("contain.text", "sign in with your email address");
    cy.get("#portalStaffSignIn").should("not.exist");
    cy.request({ url: "/portal/", followRedirect: false }).its("status").should("eq", 302);
};

const csrfOf = (html) => html.match(/name="csrf_token" value="([^"]+)"/)[1];

const postPasskeyOptions = (url, csrf, status) =>
    cy.request({ method: "POST", url, headers: { "X-CSRF-Token": csrf }, body: {}, failOnStatusCode: false }).then((response) => {
        expect(response.status, url).to.eq(status);
        return response.body;
    });

/** Saves the person editor as Judith with only the email changed (design §9, staff with Edit Records). */
const staffChangesEmail = (email) => {
    cy.clearCookies();
    cy.request({ method: "POST", url: "/admin/", form: true, followRedirect: false, body: { User: JUDITH.username, Password: JUDITH.password } })
        .its("status")
        .should("eq", 302);
    cy.request(`/PersonEditor.php?PersonID=${member.personId}`).then((page) => {
        const form = Cypress.$(new DOMParser().parseFromString(page.body, "text/html")).find("#personEditor");
        const body = {};
        form.serializeArray().forEach(({ name, value }) => {
            body[name] = value;
        });
        body.Email = email;
        body.PersonSubmit = "";
        cy.request({ method: "POST", url: `/PersonEditor.php?PersonID=${member.personId}`, form: true, body, followRedirect: false })
            .its("status")
            .should("eq", 302);
    });
    cy.clearCookies();
};

describe("Member Portal passkeys", () => {
    const saved = {};
    let authenticatorId;

    before(() => {
        cy.task("mail:available").its("available").should("eq", true);
        db("SELECT cfg_value FROM config_cfg WHERE cfg_name = 'bPortalPasskeys'").then((rows) => {
            saved.bPortalPasskeys = rows.length > 0 ? rows[0].cfg_value : null;
        });
        useMemberSignIn(saved, { iRememberDeviceDays: "30" });
    });

    beforeEach(() => {
        cy.clearCookies();
        resetEmailSignIn([member.personId]);
        cy.expireSignInThrottle(PASSKEY_FAILURES);
        db("DELETE FROM person_passkey_ppk WHERE ppk_per_ID = ?", [member.personId]);
        db("DELETE FROM person_device_pdv WHERE pdv_per_ID = ?", [member.personId]);
        addVirtualAuthenticator().then((id) => {
            authenticatorId = id;
        });
    });

    afterEach(() => {
        removeVirtualAuthenticator(authenticatorId);
        setConfig("bPortalPasskeys", "1");
        setConfig("sMemberSignInClassifications", "1,2");
        db("UPDATE person_per SET per_Email = ? WHERE per_ID = ?", [member.email, member.personId]);
        db(
            "UPDATE person_signin_psi SET psi_SignInDisabled = 0, psi_UnconfirmedEmail = NULL, psi_UnconfirmedSource = NULL, psi_UnconfirmedAt = NULL WHERE psi_per_ID = ?",
            [member.personId],
        );
    });

    after(() => {
        if (saved.bPortalPasskeys === null) {
            db("DELETE FROM config_cfg WHERE cfg_name = 'bPortalPasskeys'");
        } else {
            setConfig("bPortalPasskeys", saved.bPortalPasskeys);
        }
        restoreSettings(saved);
    });

    it("offers a passkey once after an email sign-in, only when the member ticked This is my own device", () => {
        signInWithCode({ remember: false });
        cy.get("#portal-main").should("contain.text", `Welcome, ${member.firstName}`);
        cy.get("#portal-passkey-offer").should("not.exist");

        cy.clearCookies();
        resetEmailSignIn([member.personId]);
        signInWithCode({ remember: true });
        cy.get("#portal-passkey-offer")
            .should("be.visible")
            .and("contain.text", "Sign in next time on this device with Face ID, a fingerprint or your device PIN.");
        cy.get("#portal-passkey-offer-no").click();
        cy.get("#portal-passkey-offer").should("not.be.visible");

        cy.reload();
        cy.get("#portal-main").should("contain.text", `Welcome, ${member.firstName}`);
        cy.get("#portal-passkey-offer").should("not.exist");
        passkeysOf(member.personId).should("have.length", 0);
    });

    it("adds a passkey from the offer: verified, kept by the hash of its id, audited and announced by email", () => {
        lastAuditId().then((startId) => {
            signInWithCode({ remember: true });
            cy.task("mail:clear");
            cy.get("#portal-passkey-offer-yes").click();
            cy.get(".portal-flash").should("contain.text", "Your passkey was added.");
            cy.get("#portal-passkey-offer").should("not.exist");

            authenticatorCredentials(authenticatorId).then((credentials) => {
                expect(credentials).to.have.length(1);
                expect(credentials[0].isResidentCredential, "a discoverable credential").to.eq(true);
                expect(credentials[0].rpId).to.eq("localhost");
                const credentialId = credentials[0].credentialId;

                passkeysOf(member.personId).then((rows) => {
                    expect(rows).to.have.length(1);
                    expect(rows[0].ppk_CredentialId).to.eq(base64ToBase64Url(credentialId));
                    expect(rows[0].ppk_UserHandle).to.eq(base64ToBase64Url(credentials[0].userHandle));
                    expect(rows[0].ppk_PublicKey).to.match(/^-----BEGIN PUBLIC KEY-----/);
                    expect(rows[0].ppk_Transports).to.eq("internal");
                    expect(rows[0].ppk_Label).to.match(/^\w[\w ]* · \w+$/);
                    expect(rows[0].ppk_LastUsedAt).to.be.null;
                });
                db("SELECT COUNT(*) AS n FROM person_passkey_ppk WHERE ppk_CredentialHash = SHA2(FROM_BASE64(?), 256)", [credentialId])
                    .its("0.n")
                    .should("eq", 1);

                auditSince(startId).then((rows) => {
                    const added = rows.find((row) => row.psa_Event === "passkey_added");
                    expect(added.psa_per_ID).to.eq(member.personId);
                    expect(added.psa_Method).to.eq("email_code");
                    expect(added.psa_Detail).to.match(/^\w[\w ]* · \w+$/);
                    expect(JSON.stringify(rows)).to.not.include(credentialId).and.not.include(base64ToBase64Url(credentialId));
                });
            });

            mailTo(member.email).then((messages) => {
                expect(messages).to.have.length(1);
                expect(messages[0].Subject).to.include("A passkey was added to your Member Portal sign-in");
            });

            cy.visit("/portal/devices");
            cy.get("[data-passkey-label]").invoke("text").should("match", /^\w[\w ]* · \w+$/);
            cy.get("[data-passkey-last-used]").should("contain.text", "Not yet");
        });
    });

    it("adds a passkey on Sign-in and devices after an email sign-in without the box", () => {
        signInWithCode({ remember: false });
        addPasskeyOnDevicesPage();
        authenticatorCredentials(authenticatorId).should("have.length", 1);
        cy.get("#portal-devices-everywhere-text").should("contain.text", "Your passkeys stay");
    });

    it("refuses to add a passkey in a session resumed from a remembered device", () => {
        signInWithCode({ remember: true });
        cy.getCookies().then((cookies) => cookies.filter(isSessionCookie).forEach((cookie) => cy.clearCookie(cookie.name)));

        cy.visit("/portal/devices");
        cy.get("#portal-account-toggle").should("contain.text", `Hello ${member.firstName}`);
        cy.get("#portal-passkey-add").should("not.exist");
        cy.get("#portal-passkey-add-unavailable").should("contain.text", "only within 15 minutes of signing in with your email address");
        cy.request("/portal/devices").then(({ body }) => {
            postPasskeyOptions("/portal/devices/passkeys/options", csrfOf(body), 403)
                .its("message")
                .should("include", "within 15 minutes");
        });
        passkeysOf(member.personId).should("have.length", 0);
    });

    it("refuses to add a passkey in a portal session opened from a staff sign-in", () => {
        cy.setupAdminSession();
        cy.visit("/portal/devices");
        cy.get("#portal-passkey-add").should("not.exist");
        cy.get("#portal-passkey-add-unavailable").should("contain.text", "only within 15 minutes of signing in with your email address");
    });

    it("signs in with a passkey through the shared step, and records its use", () => {
        signInWithCode({ remember: false });
        addPasskeyOnDevicesPage();
        passkeysOf(member.personId).then(([before]) => {
            lastAuditId().then((startId) => {
                signInWithPasskey();
                cy.location("pathname").should("match", /\/portal\/$/);
                cy.get("#portal-account-toggle").should("contain.text", `Hello ${member.firstName}`);
                cy.get("#portal-passkey-offer").should("not.exist");

                auditSince(startId).then((rows) => {
                    expect(events(rows)).to.deep.equal(["signed_in:passkey:"]);
                });
                db("SELECT psi_LastMethod FROM person_signin_psi WHERE psi_per_ID = ?", [member.personId])
                    .its("0.psi_LastMethod")
                    .should("eq", "passkey");
                passkeysOf(member.personId).then(([after]) => {
                    expect(after.ppk_LastUsedAt).to.not.be.null;
                    expect(Number(after.ppk_SignCount)).to.be.greaterThan(Number(before.ppk_SignCount));
                });

                cy.visit("/portal/devices");
                cy.get("[data-passkey-last-used]").should("not.contain.text", "Not yet");
                cy.get("#portal-passkey-add-unavailable").should("contain.text", "only within 15 minutes");
                cy.get("#portal-devices-confirm-email").should("not.exist");
            });
        });
    });

    it("refuses an answer from another origin or with a bad signature, naming the passkey's person", () => {
        const origin = Cypress.config("baseUrl").replace(/\/$/, "");
        signInWithCode({ remember: false });
        addPasskeyOnDevicesPage();
        authenticatorCredentials(authenticatorId).then(([credential]) => {
            const answerFrom = (csrf, clientOrigin) =>
                postPasskeyOptions("/portal/login/passkey/options", csrf, 200).then(({ publicKey }) =>
                    cy.request({
                        method: "POST",
                        url: "/portal/login/passkey",
                        form: true,
                        body: {
                            csrf_token: csrf,
                            credentialId: base64ToBase64Url(credential.credentialId),
                            clientDataJSON: textToBase64Url(
                                JSON.stringify({ type: "webauthn.get", challenge: publicKey.challenge, origin: clientOrigin }),
                            ),
                            authenticatorData: "AAAA",
                            signature: "AAAA",
                            userHandle: base64ToBase64Url(credential.userHandle),
                        },
                    }),
                );

            cy.clearCookies();
            cy.request("/portal/login").then(({ body }) => {
                const csrf = csrfOf(body);
                lastAuditId().then((startId) => {
                    answerFrom(csrf, "http://evil-localhost").its("body").should("include", "portalSignInFailed");
                    answerFrom(csrf, origin).its("body").should("include", "portalSignInFailed");
                    auditSince(startId).then((rows) => {
                        expect(events(rows)).to.deep.equal(["refused:passkey:passkey_invalid", "refused:passkey:passkey_invalid"]);
                        rows.forEach((row) => expect(row.psa_per_ID).to.eq(member.personId));
                    });
                });
            });
            cy.request({ url: "/portal/", followRedirect: false }).its("status").should("eq", 302);
        });
    });

    it("does not sign in when the device cannot verify the member", () => {
        signInWithCode({ remember: false });
        addPasskeyOnDevicesPage();
        setUserVerified(authenticatorId, false);
        signInWithPasskey();
        cy.get("#portalPasskeySignInError").should("contain.text", "No passkey was used.");
        cy.location("pathname").should("match", /\/portal\/login$/);
    });

    it("uses a passkey's answer once", () => {
        signInWithCode({ remember: false });
        addPasskeyOnDevicesPage();
        let answer;
        cy.intercept("POST", "**/portal/login/passkey", (request) => {
            answer = request.body;
        });
        signInWithPasskey();
        cy.location("pathname").should("match", /\/portal\/$/);
        lastAuditId().then((startId) => {
            cy.then(() =>
                cy.request({
                    method: "POST",
                    url: "/portal/login/passkey",
                    body: answer,
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    followRedirect: false,
                }),
            ).then((response) => {
                expect(response.status).to.eq(303);
                expect(response.headers.location).to.match(/\/portal\/login$/);
            });
            auditSince(startId).should("have.length", 0);
        });
    });

    it("removes a passkey on Sign-in and devices, and a removed passkey no longer signs in", () => {
        signInWithCode({ remember: false });
        addPasskeyOnDevicesPage();
        lastAuditId().then((startId) => {
            cy.get("[data-passkey-remove]").click();
            cy.get("[data-passkey-remove-submit]").click();
            cy.get(".portal-flash").should("contain.text", "That passkey is removed.");
            cy.get("#portal-passkeys-empty").should("be.visible");
            passkeysOf(member.personId).should("have.length", 0);
            auditSince(startId).then((rows) => {
                expect(events(rows)).to.deep.equal(["passkey_removed::"]);
                expect(rows[0].psa_Actor_usr_ID).to.be.null;
            });

            authenticatorCredentials(authenticatorId).should("have.length", 1);
            lastAuditId().then((refusedFrom) => {
                signInWithPasskey();
                expectPasskeyRefused();
                auditSince(refusedFrom).then((rows) => {
                    expect(events(rows)).to.deep.equal(["refused:passkey:passkey_unknown"]);
                    expect(rows[0].psa_per_ID).to.be.null;
                });
            });
        });
    });

    it("Disable sign-in removes every passkey", () => {
        signInWithCode({ remember: false });
        addPasskeyOnDevicesPage();
        cy.makePrivateAdminAPICall("POST", `/admin/api/person/${member.personId}/sign-in/disable`, { reason: "Passkeys spec" }, 200).then(
            ({ body }) => {
                expect(body.passkeysRemoved).to.eq(1);
            },
        );
        passkeysOf(member.personId).should("have.length", 0);
        cy.makePrivateAdminAPICall("POST", `/admin/api/person/${member.personId}/sign-in/enable`, {}, 200);

        signInWithPasskey();
        expectPasskeyRefused();
    });

    it("fails for a person who may no longer sign in, keeps the passkey, and works again once they may", () => {
        signInWithCode({ remember: false });
        addPasskeyOnDevicesPage();
        lastAuditId().then((startId) => {
            setConfig("sMemberSignInClassifications", "2");
            signInWithPasskey();
            expectPasskeyRefused();
            auditSince(startId).then((rows) => {
                expect(events(rows)).to.deep.equal(["refused:passkey:classification"]);
            });
            passkeysOf(member.personId).should("have.length", 1);

            setConfig("sMemberSignInClassifications", "1,2");
            signInWithPasskey();
            cy.location("pathname").should("match", /\/portal\/$/);
        });
    });

    it("keeps passkeys when staff change the email, and a passkey sign-in confirms the new address", () => {
        const newEmail = "norman.cooper.new@example.com";
        signInWithCode({ remember: false });
        addPasskeyOnDevicesPage();

        staffChangesEmail(newEmail);
        passkeysOf(member.personId).should("have.length", 1);
        db("SELECT psi_UnconfirmedEmail FROM person_signin_psi WHERE psi_per_ID = ?", [member.personId])
            .its("0.psi_UnconfirmedEmail")
            .should("eq", newEmail);

        cy.task("mail:clear");
        signInWithPasskey();
        cy.location("pathname").should("match", /\/portal\/$/);
        cy.visit("/portal/devices");
        cy.get("#portal-devices-confirm-email").should("contain.text", newEmail);
        cy.get("#portal-devices-confirm-email-submit").click();
        cy.get(".portal-flash").should("contain.text", `We emailed a link to ${newEmail}.`);

        mailTo(newEmail).then((messages) => {
            expect(messages, "confirmation email to the new address").to.have.length(1);
            cy.task("mail:get", { id: messages[0].ID }).then(({ body }) => {
                const link = (body.HTML || "").match(/\/portal\/email\/confirm\/[0-9a-f]{64}/);
                expect(link, "confirm link").to.not.be.null;
                cy.visit(link[0]);
                cy.get("#portal-email-confirm-button").click();
                cy.get("#portal-email-confirm").should("have.attr", "data-state", "done");
            });
        });
        db("SELECT psi_UnconfirmedEmail, psi_ProvenEmail FROM person_signin_psi WHERE psi_per_ID = ?", [member.personId]).then(([row]) => {
            expect(row.psi_UnconfirmedEmail).to.be.null;
            expect(row.psi_ProvenEmail).to.eq(newEmail);
        });
    });

    it("hides passkeys everywhere while the church has them turned off", () => {
        setConfig("bPortalPasskeys", "0");
        cy.visit("/portal/login");
        cy.get("#portalSignInEmail").should("have.attr", "autocomplete", "email");
        cy.get("#portalPasskeySignIn").should("not.exist");
        cy.request("/portal/login").then(({ body }) => {
            postPasskeyOptions("/portal/login/passkey/options", csrfOf(body), 404);
        });

        signInWithCode({ remember: true });
        cy.get("#portal-passkey-offer").should("not.exist");
        cy.visit("/portal/devices");
        cy.get("#portal-devices-remembered").should("be.visible");
        cy.get("#portal-devices-passkeys").should("not.exist");
    });

    it("stops checking passkey answers from an IP address after 20 failures in an hour", () => {
        const origin = Cypress.config("baseUrl").replace(/\/$/, "");
        const failOnce = (csrf, index) =>
            postPasskeyOptions("/portal/login/passkey/options", csrf, 200).then(({ publicKey }) =>
                cy
                    .request({
                        method: "POST",
                        url: "/portal/login/passkey",
                        form: true,
                        body: {
                            csrf_token: csrf,
                            credentialId: textToBase64Url(`unknown-credential-${index}`),
                            clientDataJSON: textToBase64Url(JSON.stringify({ type: "webauthn.get", challenge: publicKey.challenge, origin })),
                            authenticatorData: "AAAA",
                            signature: "AAAA",
                            userHandle: "",
                        },
                    })
                    .its("body")
                    .should("include", "portalSignInFailed"),
            );

        cy.visit("/portal/login");
        cy.get("#portalPasskeySignIn").should("be.visible");
        cy.get("#portalSignInEmail").should("have.attr", "autocomplete", "username webauthn");
        cy.request("/portal/login").then(({ body }) => {
            const csrf = csrfOf(body);
            lastAuditId().then((startId) => {
                Cypress._.times(21, (index) => failOnce(csrf, index));
                auditSince(startId).then((rows) => {
                    const seen = events(rows);
                    expect(seen.filter((event) => event === "refused:passkey:passkey_unknown")).to.have.length(20);
                    expect(seen[seen.length - 1]).to.eq("throttled:passkey:throttled_ip");
                });
            });
        });
    });

    it("tells administrators on the Sign-in tab that this site address can use passkeys", () => {
        cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/sign-in", null, 200).its("body.passkeyProblem").should("be.null");
    });
});
