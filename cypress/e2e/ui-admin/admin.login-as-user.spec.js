/**
 * Issue #9843 — admin-only "Login as User" (masquerade) with an exit banner,
 * behind the Allow Login as User setting, with a per-session and per-action
 * history and a two-factor rule.
 *
 * Fixtures used (cypress/data/seed.sql):
 *   - admin            : usr_ID 1  (Church Admin), no 2FA
 *   - tony.wade@example.com / basicjoe : usr_ID 3 (Tony Campbell), non-admin
 *   - twofa_user       : usr_ID 27, non-admin, 2FA enrolled
 *   - locale-admin@churchcrm.test : usr_ID 906, a second admin; the two-factor
 *     tests enrol it with TWOFA_ADMIN_SECRET and remove it again
 */

const TARGET_USER_ID = 3; // tony.wade@example.com
const TARGET_USER_NAME = "Tony Campbell";
const ADMIN_USER_ID = 1;

// usr_ID 99 — non-admin with usr_EditSelf=1, i.e. EditSelf-exclusive. Such a
// user is confined to the Member Portal (#9863), which renders neither admin
// header layout: the banner reaches it through the portal's own Twig layout
// (#9869).
const SELF_SERVICE_USER_ID = 99;
const PEER_ADMIN_USER_ID = 906; // locale-admin@churchcrm.test, usr_Admin = 1
const SELF_SERVICE_USER_NAME = "Amanda Black";

// usr_ID 8 — mustchange.user (Herminia Hart), seeded with usr_NeedPasswordChange = 1
// and Add/Edit Records, so a masquerade lands on the dashboard, not limited-access.
const MUST_CHANGE_USER_ID = 8;
const MUST_CHANGE_USER_NAME = "Herminia Hart";
const MUST_CHANGE_USER_LOGIN = "mustchange.user";

const TWOFA_TARGET_USER_ID = 27;
const TWOFA_ADMIN_LOGIN = "locale-admin@churchcrm.test";
const TWOFA_ADMIN_PASSWORD = "changeme";
// The seeded twofa_user secret is not a valid Google Authenticator key, so it can
// never produce a code; this one is JBSWY3DPEHPK3PXP encrypted with the seeded sTwoFASecretKey.
const TWOFA_ADMIN_SECRET = "JBSWY3DPEHPK3PXP";
const TWOFA_ADMIN_SECRET_ENCRYPTED =
    "def50200cca1b3bcb6e443a1769db195752f572fdefd6afac1b4921b7a160d4f7bff9c69652af3a1f0e5fc933b6112c8e28135da8159b3d0a2d8dd0c6a9f8762ff8358f391b9261800e3d2c4665a3224b51da5969a38fb9e745c300ffcbae7163f2c119d";
const TARGET_HAS_2FA_ADMIN_HAS_NONE =
    "This user signs in with two-factor authentication. Turn on two-factor authentication for your own account and sign in with it to log in as them.";
const TARGET_HAS_2FA_SESSION_WITHOUT =
    "This user signs in with two-factor authentication. Sign out, then sign in again with your own two-factor code to log in as them.";

let savedAllowLoginAsUser;

function base32Decode(secret) {
    const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
    const bits = [...secret.replace(/=+$/, "")].map((c) => alphabet.indexOf(c).toString(2).padStart(5, "0")).join("");
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) {
        bytes.push(Number.parseInt(bits.slice(i, i + 8), 2));
    }
    return new Uint8Array(bytes);
}

/** The current RFC 6238 code for a base32 secret (30-second step, 6 digits, SHA-1). */
async function totp(secret) {
    const counter = Math.floor(Date.now() / 30000);
    const message = new DataView(new ArrayBuffer(8));
    message.setUint32(0, Math.floor(counter / 2 ** 32));
    message.setUint32(4, counter >>> 0);
    const key = await crypto.subtle.importKey("raw", base32Decode(secret), { name: "HMAC", hash: "SHA-1" }, false, [
        "sign",
    ]);
    const hash = new Uint8Array(await crypto.subtle.sign("HMAC", key, message.buffer));
    const offset = hash[hash.length - 1] & 0xf;
    const binary =
        ((hash[offset] & 0x7f) << 24) | (hash[offset + 1] << 16) | (hash[offset + 2] << 8) | hash[offset + 3];
    return String(binary % 1000000).padStart(6, "0");
}

function formLogin(userName, password) {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(userName);
    cy.get("input[name=Password]").type(`${password}{enter}`, { log: false });
}

function setTwoFactorAdminSecret(encryptedSecret) {
    cy.dbQuery(
        "UPDATE user_usr SET usr_TwoFactorAuthSecret = ?, usr_TwoFactorAuthLastKeyTimestamp = NULL, usr_FailedLogins = 0 WHERE usr_UserName = ?",
        [encryptedSecret, TWOFA_ADMIN_LOGIN],
    ).then((r) => expect(r.error).to.eq(null));
}

function setAllowLoginAsUser(value) {
    cy.clearCookies();
    cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bAllowLoginAsUser", { value }, 200);
}

/** The newest Login as User session row. */
function latestMasqueradeSession() {
    return cy
        .dbQuery("SELECT * FROM user_masquerade_session_ums ORDER BY ums_ID DESC LIMIT 1")
        .then((r) => r.rows[0]);
}

/** Today's date in the rotating log filename format ({Y-m-d}-auth.log). */
function authLogFileName() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, "0");
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}-auth.log`;
}

/** Read the current CSRF token out of a rendered page's impersonation exit form. */
function csrfTokenFromPage() {
    return cy
        .visit(`/v2/user/${TARGET_USER_ID}`)
        .get('#impersonateForm input[name="csrf_token"]')
        .invoke("val");
}

before(() => {
    cy.rememberTestEnv(["admin.api.key", "standard.username", "standard.password"]);
    cy.useChurchTimeZone();
    cy.clearCookies();
    cy.getSystemConfig("bAllowLoginAsUser").then((value) => {
        savedAllowLoginAsUser = value;
    });
    setAllowLoginAsUser("1");
});

after(() => {
    cy.useHostTimeZone();
    cy.clearCookies();
    cy.restoreSystemConfig("bAllowLoginAsUser", savedAllowLoginAsUser);
});

describe("Admin Login as User (masquerade)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    // The "Advanced Settings" button lives at the bottom of the Account pane,
    // which is the tab that is active on load — no tab click needed.
    it("shows the Login as User button beside Advanced Settings", () => {
        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get("#loginAsUser")
            .should("be.visible")
            .and("have.class", "btn-outline-warning");
        cy.get("#loginAsUser i").should("have.class", "fa-user-secret");
        cy.get("#loginAsUser").should("contain.text", "Login as User");
    });

    it("does not show the button on the admin's own record", () => {
        cy.visit(`/v2/user/${ADMIN_USER_ID}`);
        cy.get("#loginAsUser").should("not.exist");
    });

    it("does not show the button on another administrator's record", () => {
        cy.visit(`/v2/user/${PEER_ADMIN_USER_ID}`);
        cy.get("#loginAsUser").should("not.exist");
    });

    it("confirms, masquerades, shows the banner, and exits back to the user record", () => {
        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get("#loginAsUser").click();

        // bootbox confirm
        cy.get(".bootbox.modal").should("be.visible");
        cy.get(".bootbox.modal").should(
            "contain.text",
            `Log in as ${TARGET_USER_NAME}?`,
        );
        cy.get(".bootbox.modal .btn-warning").click();

        // Lands on the dashboard as Tony
        cy.url().should("include", "/v2/dashboard");
        cy.get("#impersonationBanner")
            .should("be.visible")
            .and(
                "contain.text",
                `You are logged in as ${TARGET_USER_NAME}. Actions are recorded as them.`,
            );
        cy.get("#impersonationExit").should("be.visible");
        cy.get("#impersonationExit").should(
            "have.attr",
            "aria-label",
            "Exit and return to your own account",
        );

        // The topbar user menu now shows Tony's identity, not the admin's
        cy.get(".navbar .nav-item.dropdown").contains(TARGET_USER_NAME).should("exist");
        cy.get(".navbar").should("not.contain.text", "Church Admin");

        // An admin-only page is refused while masquerading
        cy.visit({ url: "/admin/system/users", failOnStatusCode: false });
        cy.url().should("include", "access-denied");

        // The Login as User button is absent on every user page while masquerading
        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get("#loginAsUser").should("not.exist");

        // Exit returns the admin to the user's record with no banner
        cy.get("#impersonationExit").click();
        cy.url().should("include", `/v2/user/${TARGET_USER_ID}`);
        cy.get("#impersonationBanner").should("not.exist");
        cy.get(".navbar").should("contain.text", "Church Admin");
    });

    it("Sign out during a masquerade returns the admin instead of ending the session", () => {
        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get("#loginAsUser").click();
        cy.get(".bootbox.modal .btn-warning").click();
        cy.url().should("include", "/v2/dashboard");

        // The user menu's sign-out item is relabelled and exits the masquerade
        cy.get('.navbar [aria-label="Open user menu"]').click();
        cy.get("#userMenuSignOut")
            .should("be.visible")
            .and("contain.text", "Exit to your account");
        cy.get("#userMenuSignOut").click();

        cy.url().should("include", `/v2/user/${TARGET_USER_ID}`);
        cy.get("#impersonationBanner").should("not.exist");
        cy.get(".navbar").should("contain.text", "Church Admin");
        cy.url().should("not.include", "/session/begin");
    });

    // Regression: an EditSelf-exclusive target lands in the Member Portal, which
    // renders neither Header.php nor HeaderNotLoggedIn.php. The banner must follow
    // the session onto the portal's own layout too (MP8, #9869), otherwise the
    // administrator has no visible way back — and the portal's "you are viewing
    // this as yourself" staff bar must not claim otherwise.
    it("shows the banner in the Member Portal for a self-service-only user", () => {
        cy.visit(`/v2/user/${SELF_SERVICE_USER_ID}`);
        cy.get("#loginAsUser").click();
        cy.get(".bootbox.modal").should(
            "contain.text",
            `Log in as ${SELF_SERVICE_USER_NAME}?`,
        );
        cy.get(".bootbox.modal .btn-warning").click();

        cy.url().should("include", "/portal");
        // The portal layout marks a masqueraded session with its own body class
        // (`portal-body-with-bar`, which makes room for the banner), not the admin
        // shell's `impersonating`.
        cy.get("body").should("have.class", "portal-body-with-bar");
        cy.get(".portal-home", { timeout: 10000 }).should("exist");
        // The staff bar would say "You are viewing the Member Portal as yourself",
        // which during a masquerade is exactly the wrong sentence.
        cy.get(".portal-staff-bar").should("not.exist");
        cy.get("#impersonationBanner")
            .should("be.visible")
            .and(
                "contain.text",
                `You are logged in as ${SELF_SERVICE_USER_NAME}. Actions are recorded as them.`,
            );

        // The banner's icon is the only exit on this layout — the portal has no
        // admin user menu.
        cy.get("#impersonationExit").should("be.visible").click();
        cy.url().should("include", `/v2/user/${SELF_SERVICE_USER_ID}`);
        cy.get("#impersonationBanner").should("not.exist");
        cy.get(".navbar").should("contain.text", "Church Admin");
    });

    it("is not trapped by the account's forced password change, and Exit still works (2026-09-18)", () => {
        // A user created by an administrator must change their password on
        // first login. That obligation is the account owner's, not the
        // masquerading administrator's: it used to bounce every request —
        // including the banner's Exit — to the change-password page.
        cy.dbQuery("UPDATE user_usr SET usr_NeedPasswordChange = 1 WHERE usr_per_ID = ?", [SELF_SERVICE_USER_ID]).then((r) => {
            expect(r.error).to.eq(null);
        });

        cy.visit(`/v2/user/${SELF_SERVICE_USER_ID}`);
        cy.get("#loginAsUser").click();
        cy.get(".bootbox.modal .btn-warning").click();

        cy.url().should("include", "/portal").and("not.include", "password");
        cy.get(".portal-home", { timeout: 10000 }).should("exist");
        cy.get("#impersonationBanner").should("be.visible");

        // Another page of theirs, and still no password screen.
        cy.visit("/portal/profile");
        cy.url().should("not.include", "password");

        cy.get("#impersonationExit").should("be.visible").click();
        cy.url().should("include", `/v2/user/${SELF_SERVICE_USER_ID}`).and("not.include", "password");
        cy.get("#impersonationBanner").should("not.exist");
        cy.get(".navbar").should("contain.text", "Church Admin");

        // The flag itself is untouched: the owner still has to change it.
        cy.dbQuery("SELECT usr_NeedPasswordChange AS f FROM user_usr WHERE usr_per_ID = ?", [SELF_SERVICE_USER_ID]).then((r) => {
            expect(Number(r.rows[0].f)).to.eq(1);
        });
        cy.dbQuery("UPDATE user_usr SET usr_NeedPasswordChange = 0 WHERE usr_per_ID = ?", [SELF_SERVICE_USER_ID]);
    });

    [
        [910, "deceased"],
        [911, "inactive"],
    ].forEach(([userId, status]) => {
        it(`masquerades as a ${status} account and exits back to the admin`, () => {
            cy.visit(`/v2/user/${userId}`);
            cy.get("#loginAsUser").click();
            cy.get(".bootbox.modal .btn-warning").click();
            cy.get("#impersonationBanner").should("be.visible");

            cy.visit("/v2/dashboard");
            cy.url().should("not.include", "/session/begin");
            cy.get("#impersonationExit").click();
            cy.url().should("include", `/v2/user/${userId}`);
            cy.get("#impersonationBanner").should("not.exist");
            cy.get(".navbar").should("contain.text", "Church Admin");
        });
    });

    // The portal is the self-service landing that replaced the limited-access
    // page (#9863). Its account menu's sign-out must hand the administrator back,
    // as the admin shell's does; GET /session/end stays a plain logout.
    it("the portal's sign-out returns the admin", () => {
        cy.visit(`/v2/user/${SELF_SERVICE_USER_ID}`);
        cy.get("#loginAsUser").click();
        cy.get(".bootbox.modal .btn-warning").click();
        cy.url().should("include", "/portal");

        cy.get("#portal-account-toggle").click();
        cy.get("#portalAccountSignOut").should("contain.text", "Exit to your account").click();
        cy.url().should("include", `/v2/user/${SELF_SERVICE_USER_ID}`);
        cy.get("#impersonationBanner").should("not.exist");
        cy.get(".navbar").should("contain.text", "Church Admin");
    });

    // A user created by an administrator must change their password on first
    // login. That obligation is the account owner's, not the masquerading
    // administrator's: before the guard in LocalAuthentication it bounced every
    // request — the banner's Exit included — to the change-password page, and the
    // administrator had no way back to their own account.
    it("is not trapped by the target's forced password change, and Exit still works", () => {
        cy.visit(`/v2/user/${MUST_CHANGE_USER_ID}`);
        cy.get("#loginAsUser").click();
        cy.get(".bootbox.modal").should("contain.text", `Log in as ${MUST_CHANGE_USER_NAME}?`);
        cy.get(".bootbox.modal .btn-warning").click();

        cy.url().should("include", "/v2/dashboard").and("not.include", "changepassword");
        cy.get("#impersonationBanner")
            .should("be.visible")
            .and("contain.text", `You are logged in as ${MUST_CHANGE_USER_NAME}.`);

        // Another page of theirs, and still no password screen.
        cy.visit("/people/cart");
        cy.url().should("include", "/people/cart").and("not.include", "changepassword");
        cy.get("#impersonationBanner").should("be.visible");

        cy.get("#impersonationExit").should("be.visible").click();
        cy.url().should("include", `/v2/user/${MUST_CHANGE_USER_ID}`).and("not.include", "changepassword");
        cy.get("#impersonationBanner").should("not.exist");
        cy.get(".navbar").should("contain.text", "Church Admin");

        // The flag itself is untouched: the owner is still made to change it on
        // their own next login.
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(MUST_CHANGE_USER_LOGIN);
        cy.get("input[name=Password]").type("changeme{enter}");
        cy.url({ timeout: 10000 }).should("include", "/v2/user/current/changepassword");
        cy.get("#OldPassword").should("exist");
    });

    it("does not render the banner or the offset on the anonymous login page", () => {
        cy.visit("/session/begin");
        cy.get("#impersonationBanner").should("not.exist");
        cy.get("body").should("not.have.class", "impersonating");
    });

    it("writes both masquerade lines to the auth log", () => {
        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get("#loginAsUser").click();
        cy.get(".bootbox.modal .btn-warning").click();
        cy.url().should("include", "/v2/dashboard");
        cy.get("#impersonationExit").click();
        cy.url().should("include", `/v2/user/${TARGET_USER_ID}`);

        // The tail of the log file itself, never the download endpoint: `src/logs`
        // is bind-mounted from the host, and fetching the whole day's auth log
        // through the API takes minutes once other specs have filled it (it hung
        // the CI admin-ui job at the 30-minute limit).
        cy.exec(`tail -n 400 src/logs/${authLogFileName()}`).then((result) => {
            const body = result.stdout;
            expect(body).to.contain(
                `Masquerade started: admin ${ADMIN_USER_ID} (Church Admin) as user ${TARGET_USER_ID} (${TARGET_USER_NAME})`,
            );
            expect(body).to.contain(
                `Masquerade ended: admin ${ADMIN_USER_ID} back from user ${TARGET_USER_ID}`,
            );
        });
    });

    describe("negative cases", () => {
        it("POST on the caller's own id answers 400", () => {
            csrfTokenFromPage().then((token) => {
                cy.request({
                    method: "POST",
                    url: `/v2/user/${ADMIN_USER_ID}/impersonate`,
                    form: true,
                    body: { csrf_token: token },
                    headers: { Accept: "application/json" },
                    failOnStatusCode: false,
                }).then((response) => {
                    expect(response.status).to.equal(400);
                });
            });
        });

        it("POST for an unknown user answers 404", () => {
            csrfTokenFromPage().then((token) => {
                cy.request({
                    method: "POST",
                    url: "/v2/user/99999/impersonate",
                    form: true,
                    body: { csrf_token: token },
                    headers: { Accept: "application/json" },
                    failOnStatusCode: false,
                }).then((response) => {
                    expect(response.status).to.equal(404);
                });
            });
        });

        it("POST for another administrator answers 403", () => {
            csrfTokenFromPage().then((token) => {
                cy.request({
                    method: "POST",
                    url: `/v2/user/${PEER_ADMIN_USER_ID}/impersonate`,
                    form: true,
                    body: { csrf_token: token },
                    headers: { Accept: "application/json" },
                    failOnStatusCode: false,
                }).then((response) => {
                    expect(response.status).to.equal(403);
                    expect(response.body.message).to.equal("You cannot log in as another administrator.");
                });
            });
        });

        it("a second POST while already masquerading answers 409", () => {
            csrfTokenFromPage().then((token) => {
                cy.request({
                    method: "POST",
                    url: `/v2/user/${TARGET_USER_ID}/impersonate`,
                    form: true,
                    body: { csrf_token: token },
                    headers: { Accept: "application/json" },
                    failOnStatusCode: false,
                }).then((first) => {
                    expect(first.status, "first masquerade").to.be.oneOf([200, 302]);
                    cy.request({
                        method: "POST",
                        url: `/v2/user/${TARGET_USER_ID}/impersonate`,
                        form: true,
                        body: { csrf_token: token },
                        headers: { Accept: "application/json" },
                        failOnStatusCode: false,
                    }).then((second) => {
                        expect(second.status, "second masquerade").to.equal(409);
                    });
                    // Leave the session clean for the next test
                    cy.request({
                        method: "POST",
                        url: "/v2/user/impersonate/exit",
                        form: true,
                        body: { csrf_token: token },
                        headers: { Accept: "application/json" },
                        failOnStatusCode: false,
                    });
                });
            });
        });

        it("exit without an active masquerade answers 400", () => {
            csrfTokenFromPage().then((token) => {
                cy.request({
                    method: "POST",
                    url: "/v2/user/impersonate/exit",
                    form: true,
                    body: { csrf_token: token },
                    headers: { Accept: "application/json" },
                    failOnStatusCode: false,
                }).then((response) => {
                    expect(response.status).to.equal(400);
                });
            });
        });

        it("an x-api-key caller cannot start a masquerade", () => {
            cy.request({
                method: "POST",
                url: `/v2/user/${TARGET_USER_ID}/impersonate`,
                headers: {
                    "x-api-key": Cypress.testEnv("admin.api.key"),
                    Accept: "application/json",
                },
                form: true,
                body: {},
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.equal(403);
            });
        });

        it("a non-admin session is refused with 403", () => {
            cy.setupStandardSession();
            cy.visit("/v2/dashboard");
            cy.getCookies().then(() => {
                cy.request({
                    method: "POST",
                    url: `/v2/user/${ADMIN_USER_ID}/impersonate`,
                    headers: { Accept: "application/json" },
                    form: true,
                    body: {},
                    failOnStatusCode: false,
                }).then((response) => {
                    expect(response.status).to.equal(403);
                });
            });
        });
    });
});

// CodeRabbit finding on #9844: the masquerade record must not outlive the session
// it was started in. Each test logs in afresh (forceLogin) because a real login
// rotates the session id, which would strand the shared cached admin session.
describe("Masquerade record does not survive its session", () => {
    /** Start a masquerade as the target through the UI and wait for the dashboard. */
    function startMasquerade() {
        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get("#loginAsUser").click();
        cy.get(".bootbox.modal .btn-warning").click();
        cy.url().should("include", "/v2/dashboard");
        cy.get("#impersonationBanner").should("be.visible");
    }

    it("GET /session/end during a live masquerade ends the whole session", () => {
        cy.setupAdminSession({ forceLogin: true });
        startMasquerade();

        cy.visit("/session/end");
        cy.url().should("include", "/session/begin");
        cy.get("#impersonationBanner").should("not.exist");
        cy.visit("/v2/dashboard");
        cy.url().should("include", "/session/begin");
        latestMasqueradeSession().then((row) => {
            expect(row.ums_EndReason).to.eq("signout");
            expect(row.ums_Ended).to.not.eq(null);
        });
    });

    it("a real login in the same browser discards the record", () => {
        cy.setupAdminSession({ forceLogin: true });
        startMasquerade();

        // Sign in as the impersonated user without signing out first
        cy.request({
            method: "POST",
            url: "/session/begin",
            form: true,
            body: {
                User: Cypress.testEnv("standard.username"),
                Password: Cypress.testEnv("standard.password"),
            },
        });

        cy.visit("/v2/dashboard");
        cy.get("#impersonationBanner").should("not.exist");
        cy.get("body").should("not.have.class", "impersonating");
        latestMasqueradeSession().its("ums_EndReason").should("eq", "signout");

        // Signing out now ends the session; it must not restore the administrator
        cy.visit("/session/end");
        cy.url().should("include", "/session/begin");
        cy.visit("/v2/dashboard");
        cy.url().should("include", "/session/begin");
    });

    describe("after a session timeout", () => {
        let savedTimeout;

        // Config calls authenticate by API key; clear cookies first so they do
        // not touch a browser session.
        before(() => {
            cy.clearCookies();
            cy.getSystemConfig("iSessionTimeout").then((value) => {
                savedTimeout = value;
            });
            cy.makePrivateAdminAPICall("POST", "admin/api/system/config/iSessionTimeout", { value: "3" }, 200);
        });

        after(() => {
            cy.clearCookies();
            cy.restoreSystemConfig("iSessionTimeout", savedTimeout);
        });

        it("signing out does not restore the administrator", () => {
            cy.setupAdminSession({ forceLogin: true });
            startMasquerade();

            // Leave the dashboard (its polling requests keep the session alive)
            // for the login page, which makes none, then let the 3-second
            // session timeout lapse.
            cy.visit("/session/begin");
            cy.wait(5000);

            cy.visit("/v2/dashboard");
            cy.url().should("include", "/session/begin");
            latestMasqueradeSession().its("ums_EndReason").should("eq", "timeout");

            cy.visit("/session/end");
            cy.url().should("include", "/session/begin");
            cy.get("#impersonationBanner").should("not.exist");
            cy.visit("/v2/dashboard");
            cy.url().should("include", "/session/begin");
        });
    });
});

describe("Allow Login as User setting", () => {
    after(() => {
        setAllowLoginAsUser("1");
    });

    it("is a switch on System Users and only there", () => {
        cy.setupAdminSession();
        cy.visit("/admin/system/users");
        cy.get('[data-bs-target="#userSettingsPanel"]').click();
        cy.get("#userSettingsPanel").should("contain.text", "Allow Login as User");
        cy.get('#userSettingsPanel input[name="bAllowLoginAsUser"]').should("have.length", 2);

        cy.visit("/SystemSettings.php");
        cy.get("body").should("not.contain.text", "Allow Login as User");
    });

    it("turned off on System Users, hides the button and the routes answer 404", () => {
        cy.setupAdminSession();
        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get('#impersonateForm input[name="csrf_token"]').invoke("val").as("csrfToken");

        cy.intercept("POST", "**/admin/api/system/config/bAllowLoginAsUser").as("saveAllow");
        cy.visit("/admin/system/users");
        cy.get('[data-bs-target="#userSettingsPanel"]').click();
        cy.get("#userSettingsPanel .settings-panel-save").should("not.be.disabled");
        cy.get('#userSettingsPanel input[name="bAllowLoginAsUser"][value="0"]').check({ force: true });
        cy.get("#userSettingsPanel .settings-panel-save").click();
        cy.wait("@saveAllow").its("response.statusCode").should("eq", 200);

        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get("#loginAsUser").should("not.exist");

        cy.get("@csrfToken").then((token) => {
            for (const url of [`/v2/user/${TARGET_USER_ID}/impersonate`, "/v2/user/impersonate/exit"]) {
                cy.request({
                    method: "POST",
                    url,
                    form: true,
                    body: { csrf_token: token },
                    headers: { Accept: "application/json" },
                    failOnStatusCode: false,
                })
                    .its("status")
                    .should("eq", 404);
            }
        });

        cy.visit("/admin/system/users");
        cy.get('[data-bs-target="#userSettingsPanel"]').click();
        cy.get("#userSettingsPanel .settings-panel-save").should("not.be.disabled");
        cy.get('#userSettingsPanel input[name="bAllowLoginAsUser"][value="1"]').check({ force: true });
        cy.get("#userSettingsPanel .settings-panel-save").click();
        cy.wait("@saveAllow").its("response.statusCode").should("eq", 200);

        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get("#loginAsUser").should("be.visible").and("not.be.disabled");
    });

    it("turned off during a masquerade still lets the administrator exit", () => {
        cy.setupAdminSession();
        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get("#loginAsUser").click();
        cy.get(".bootbox.modal .btn-warning").click();
        cy.get("#impersonationBanner").should("be.visible");

        cy.dbQuery("UPDATE config_cfg SET cfg_value = '0' WHERE cfg_name = 'bAllowLoginAsUser'").then((r) =>
            expect(r.error).to.eq(null),
        );
        cy.get("#impersonationExit").click();
        cy.url().should("include", `/v2/user/${TARGET_USER_ID}`);
        cy.get("#impersonationBanner").should("not.exist");
        cy.get(".navbar").should("contain.text", "Church Admin");
        cy.get("#loginAsUser").should("not.exist");
    });
});

describe("Login as User two-factor rule", () => {
    after(() => {
        setTwoFactorAdminSecret(null);
    });

    it("refuses a user with 2FA when the administrator has no 2FA", () => {
        cy.setupAdminSession();
        cy.visit(`/v2/user/${TWOFA_TARGET_USER_ID}`);
        cy.get("#loginAsUser").should("be.disabled");
        cy.get("#loginAsUserRefusal").should("have.text", TARGET_HAS_2FA_ADMIN_HAS_NONE);
        cy.get("#impersonateForm").should("not.exist");

        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get('#impersonateForm input[name="csrf_token"]')
            .invoke("val")
            .then((token) => {
                cy.request({
                    method: "POST",
                    url: `/v2/user/${TWOFA_TARGET_USER_ID}/impersonate`,
                    form: true,
                    body: { csrf_token: token },
                    headers: { Accept: "application/json" },
                    failOnStatusCode: false,
                }).then((response) => {
                    expect(response.status).to.eq(403);
                    expect(response.body.message).to.eq(TARGET_HAS_2FA_ADMIN_HAS_NONE);
                });
            });
        cy.visit("/v2/dashboard");
        cy.get("#impersonationBanner").should("not.exist");
    });

    it("refuses it when the administrator has 2FA but did not sign in with it", () => {
        setTwoFactorAdminSecret(null);
        formLogin(TWOFA_ADMIN_LOGIN, TWOFA_ADMIN_PASSWORD);
        cy.url().should("include", "/v2/dashboard");
        setTwoFactorAdminSecret(TWOFA_ADMIN_SECRET_ENCRYPTED);

        cy.visit(`/v2/user/${TWOFA_TARGET_USER_ID}`);
        cy.get("#loginAsUser").should("be.disabled");
        cy.get("#loginAsUserRefusal").should("have.text", TARGET_HAS_2FA_SESSION_WITHOUT);

        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get('#impersonateForm input[name="csrf_token"]')
            .invoke("val")
            .then((token) => {
                cy.request({
                    method: "POST",
                    url: `/v2/user/${TWOFA_TARGET_USER_ID}/impersonate`,
                    form: true,
                    body: { csrf_token: token },
                    headers: { Accept: "application/json" },
                    failOnStatusCode: false,
                }).then((response) => {
                    expect(response.status).to.eq(403);
                    expect(response.body.message).to.eq(TARGET_HAS_2FA_SESSION_WITHOUT);
                });
            });
    });

    it("allows it after the administrator signed in with their own 2FA code, also after an exit", () => {
        setTwoFactorAdminSecret(TWOFA_ADMIN_SECRET_ENCRYPTED);
        formLogin(TWOFA_ADMIN_LOGIN, TWOFA_ADMIN_PASSWORD);
        cy.url().should("include", "/session/two-factor");
        cy.wrap(null).then(() => totp(TWOFA_ADMIN_SECRET)).then((code) => {
            cy.get("#TwoFACode").type(`${code}{enter}`);
        });
        cy.url().should("include", "/v2/dashboard");

        for (let round = 0; round < 2; round++) {
            cy.visit(`/v2/user/${TWOFA_TARGET_USER_ID}`);
            cy.get("#loginAsUserRefusal").should("not.exist");
            cy.get("#loginAsUser").should("not.be.disabled").click();
            cy.get(".bootbox.modal .btn-warning").click();
            cy.get("#impersonationBanner").should("be.visible");
            cy.get("#impersonationExit").click();
            cy.url().should("include", `/v2/user/${TWOFA_TARGET_USER_ID}`);
            cy.get("#impersonationBanner").should("not.exist");
        }
    });
});

describe("Login as User history", () => {
    const createdNoteIds = [];

    after(() => {
        cy.clearCookies();
        cy.cleanupNotes(createdNoteIds);
    });

    it("records each write with the administrator and lists it on both users' pages", () => {
        cy.setupAdminSession();
        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get("#loginAsUser").click();
        cy.get(".bootbox.modal .btn-warning").click();
        cy.get("#impersonationBanner").should("be.visible");

        cy.request({
            method: "POST",
            url: "/api/person/2/note",
            body: { text: "Written during Login as User" },
        }).then((response) => {
            expect(response.status).to.eq(201);
            createdNoteIds.push(response.body.note.id);
            cy.dbQuery("SELECT nte_EnteredBy FROM note_nte WHERE nte_ID = ?", [response.body.note.id])
                .its("rows.0.nte_EnteredBy")
                .should("eq", TARGET_USER_ID);
        });
        cy.request({
            method: "POST",
            url: "/PersonEditor.php?PersonID=2&token=not-recorded",
            form: true,
            body: {},
        });

        cy.get("#impersonationExit").click();
        cy.url().should("include", `/v2/user/${TARGET_USER_ID}`);

        latestMasqueradeSession().then((session) => {
            expect(session.ums_admin_usr_ID).to.eq(ADMIN_USER_ID);
            expect(session.ums_target_usr_ID).to.eq(TARGET_USER_ID);
            expect(session.ums_EndReason).to.eq("exit");
            cy.dbQuery(
                "SELECT uma_Method, uma_Path, uma_Status FROM user_masquerade_action_uma WHERE uma_ums_ID = ? ORDER BY uma_ID",
                [session.ums_ID],
            ).then((r) => {
                expect(r.rows).to.deep.equal([
                    { uma_Method: "POST", uma_Path: "/api/person/2/note", uma_Status: 201 },
                    { uma_Method: "POST", uma_Path: "/PersonEditor.php", uma_Status: 200 },
                ]);
            });

            for (const userId of [TARGET_USER_ID, ADMIN_USER_ID]) {
                cy.visit(`/v2/user/${userId}`);
                cy.get(`#loginAsUserHistory [data-session-id="${session.ums_ID}"]`).within(() => {
                    cy.contains(`Church Admin logged in as ${TARGET_USER_NAME}`);
                    cy.get('[data-cy="login-as-user-end-reason"]').should("have.text", "Exited");
                    cy.get('[data-cy="login-as-user-action"]').should("have.length", 2);
                    cy.get('[data-cy="login-as-user-action"]').eq(0).should("contain.text", "POST /api/person/2/note");
                    cy.get('[data-cy="login-as-user-action"]').eq(0).should("contain.text", "201");
                    cy.get('[data-cy="login-as-user-action"]').eq(1).should("contain.text", "POST /PersonEditor.php");
                    cy.root().should("not.contain.text", "not-recorded");
                });
            }
        });
    });

    it("is not shown to the user themselves", () => {
        cy.setupStandardSession();
        cy.visit(`/v2/user/${TARGET_USER_ID}`);
        cy.get("#tab-account").should("exist");
        cy.get("#loginAsUserHistory").should("not.exist");
    });
});
