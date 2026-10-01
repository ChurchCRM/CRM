/**
 * Issue #9843 — admin-only "Login as User" (masquerade) with an exit banner.
 *
 * Fixtures used (cypress/data/seed.sql):
 *   - admin            : usr_ID 1  (Church Admin)
 *   - tony.wade@example.com / basicjoe : usr_ID 3 (Tony Campbell), non-admin
 *
 * The auth-log assertions read the rotating auth log through the admin-only
 * endpoint GET /admin/api/system/logs/{YYYY-MM-DD}-auth.log.
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
                    "x-api-key": Cypress.env("admin.api.key"),
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
                User: Cypress.env("standard.username"),
                Password: Cypress.env("standard.password"),
            },
        });

        cy.visit("/v2/dashboard");
        cy.get("#impersonationBanner").should("not.exist");
        cy.get("body").should("not.have.class", "impersonating");

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

            cy.visit("/session/end");
            cy.url().should("include", "/session/begin");
            cy.get("#impersonationBanner").should("not.exist");
            cy.visit("/v2/dashboard");
            cy.url().should("include", "/session/begin");
        });
    });
});
