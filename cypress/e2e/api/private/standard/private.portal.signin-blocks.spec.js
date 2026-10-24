/// <reference types="cypress" />

/**
 * Member sign-in row 4 (#10463): the blocks for everyone (docs/member-sign-in-design.md
 * §4, A13). An inactive family, awaiting review and Disable sign-in refuse staff sign-in
 * with the same answer as a wrong password, and end an open portal session on its next
 * request. Seed staff account noperm.user (Alice Noperm, person 901, family 1), password
 * changeme; seed member Lena Black (person 100, family 20), who signs in with her email.
 *
 * Remembered devices, passkeys, the session timeout and the audit have no HTTP surface
 * until rows 7, 8 and 9, so they are set and read with cy.dbQuery().
 */

const staff = { username: "noperm.user", password: "changeme", personId: 901, familyId: 1 };
const member = { personId: 100, familyId: 20 };
const GENERIC_MESSAGE = "Invalid login or password";

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const lastAuditId = () =>
    db("SELECT COALESCE(MAX(psa_ID), 0) AS id FROM portal_signin_audit_psa").then((rows) => Number(rows[0].id));

const auditRowsSince = (personId, afterId) =>
    db("SELECT * FROM portal_signin_audit_psa WHERE psa_per_ID = ? AND psa_ID > ? ORDER BY psa_ID", [personId, afterId]);

/** An API-key call answers with a new session cookie; keep the browser's own session. */
const keepingSession = (call) =>
    cy.getCookies().then((cookies) => {
        call();
        cy.clearCookies();
        cookies.forEach(({ name, value, path, domain, httpOnly, secure, sameSite }) => {
            cy.setCookie(name, value, { path, domain, httpOnly, secure, sameSite });
        });
    });

const adminPost = (url, body = null, expectedStatus = 200) =>
    keepingSession(() => cy.makePrivateAdminAPICall("POST", url, body, expectedStatus));

const signIn = () => {
    cy.clearCookies();
    return cy.request({
        method: "POST",
        url: "/admin/",
        form: true,
        followRedirect: false,
        body: { User: staff.username, Password: staff.password },
    });
};

const portalSessionOnly = () => {
    cy.signInAsMember(member.personId);
    cy.request("/api/portal/me").its("body.profile.id").should("eq", member.personId);
};

const randomHash = () => Array.from({ length: 64 }, () => Math.floor(Math.random() * 16).toString(16)).join("");

const addDeviceAndPasskey = () => {
    db(
        "INSERT INTO person_device_pdv (pdv_per_ID, pdv_TokenHash, pdv_Kind, pdv_CreatedAt, pdv_ExpiresAt) VALUES (?, ?, 'browser', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))",
        [member.personId, randomHash()],
    );
    db(
        "INSERT INTO person_passkey_ppk (ppk_per_ID, ppk_CredentialId, ppk_CredentialHash, ppk_PublicKey, ppk_CreatedAt) VALUES (?, 'cypress-credential', ?, 'cypress-key', NOW())",
        [member.personId, randomHash()],
    );
};

const blocks = [
    {
        name: "an inactive family",
        reason: "family_inactive",
        apply: (person) => adminPost(`/api/family/${person.familyId}/activate/false`),
    },
    {
        name: "awaiting review",
        reason: "needs_review",
        apply: (person) => db("UPDATE person_per SET per_NeedsReview = 1 WHERE per_ID = ?", [person.personId]),
    },
    {
        name: "Disable sign-in",
        reason: "sign_in_disabled",
        apply: (person) => adminPost(`/admin/api/person/${person.personId}/sign-in/disable`, { reason: "Blocks spec" }),
    },
];

describe("Sign-in blocks for everyone", () => {
    afterEach(() => {
        cy.clearCookies();
        db("UPDATE family_fam SET fam_DateDeactivated = NULL WHERE fam_ID IN (?, ?)", [staff.familyId, member.familyId]);
        db("UPDATE person_per SET per_NeedsReview = 0 WHERE per_ID IN (?, ?)", [staff.personId, member.personId]);
        db("DELETE FROM person_signin_psi WHERE psi_per_ID IN (?, ?, 27)", [staff.personId, member.personId]);
        db("DELETE FROM person_device_pdv WHERE pdv_per_ID = ?", [member.personId]);
        db("DELETE FROM person_passkey_ppk WHERE ppk_per_ID = ?", [member.personId]);
    });

    blocks.forEach(({ name, reason, apply }) => {
        describe(name, () => {
            it("refuses staff sign-in with the same answer as a wrong password", () => {
                signIn().its("status").should("eq", 302);
                cy.clearCookies();
                apply(staff);

                signIn().then((response) => {
                    expect(response.status).to.eq(200);
                    expect(response.body).to.contain(GENERIC_MESSAGE);
                });
                cy.request({ url: "/v2/dashboard", followRedirect: false }).its("status").should("eq", 302);
            });

            it("refuses the person's API key and the public login API", () => {
                apply(staff);
                cy.makePrivateNoPermAPICall("GET", "/api/person/2", null, 401);
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    body: { userName: staff.username, password: staff.password },
                }).then((blocked) => {
                    cy.apiRequest({
                        method: "POST",
                        url: "/api/public/user/login",
                        body: { userName: staff.username, password: "not-the-password" },
                    }).then((wrong) => {
                        expect(blocked.status).to.eq(401);
                        expect(blocked.body).to.deep.eq(wrong.body);
                    });
                });
            });

            it("ends an open portal session on its next request", () => {
                lastAuditId().then((startId) => {
                    portalSessionOnly();
                    apply(member);
                    cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
                    auditRowsSince(member.personId, startId).then((rows) => {
                        const ended = rows.filter((row) => row.psa_Event === "session_ended");
                        expect(ended).to.have.length(1);
                        expect(ended[0].psa_Reason).to.eq(reason);
                    });
                });
            });
        });
    });

    it("refuses the two-factor step once Disable sign-in is on", () => {
        cy.clearCookies();
        cy.request({ method: "POST", url: "/admin/", form: true, body: { User: "twofa_user", Password: "changeme" } })
            .its("redirects")
            .should("satisfy", (redirects) => redirects.some((url) => url.includes("/session/two-factor")));
        adminPost("/admin/api/person/27/sign-in/disable", { reason: "Blocks spec" });

        cy.request({ method: "POST", url: "/session/two-factor", form: true, body: { TwoFACode: "000000" } }).then(
            (response) => {
                expect(response.redirects.some((url) => url.includes("/session/two-factor"))).to.eq(false);
                expect(response.redirects.some((url) => url.endsWith("/admin/"))).to.eq(true);
            },
        );
        cy.request({ url: "/v2/dashboard", followRedirect: false }).its("status").should("eq", 302);
    });

    it("Disable sign-in removes devices and passkeys and is audited with the administrator", () => {
        lastAuditId().then((startId) => {
            portalSessionOnly();
            addDeviceAndPasskey();

            adminPost(`/admin/api/person/${member.personId}/sign-in/disable`, { reason: "Blocks spec" });

            cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
            db("SELECT COUNT(*) AS n FROM person_device_pdv WHERE pdv_per_ID = ?", [member.personId]).its("0.n").should("eq", 0);
            db("SELECT COUNT(*) AS n FROM person_passkey_ppk WHERE ppk_per_ID = ?", [member.personId]).its("0.n").should("eq", 0);
            db("SELECT * FROM person_signin_psi WHERE psi_per_ID = ?", [member.personId]).then((rows) => {
                expect(rows[0].psi_SignInDisabled).to.eq(1);
                expect(rows[0].psi_DisabledBy_usr_ID).to.eq(1);
                expect(rows[0].psi_DisabledReason).to.eq("Blocks spec");
            });
            auditRowsSince(member.personId, startId).then((rows) => {
                const disabled = rows.find((row) => row.psa_Event === "sign_in_disabled");
                expect(disabled.psa_Actor_usr_ID).to.eq(1);
                expect(disabled.psa_Detail).to.eq("devices=1 passkeys=1");
                expect(JSON.stringify(rows)).not.to.contain("Blocks spec");
            });

            adminPost(`/admin/api/person/${member.personId}/sign-in/enable`);
            auditRowsSince(member.personId, startId).then((rows) => {
                expect(rows.map((row) => row.psa_Event)).to.include("sign_in_enabled");
            });
            portalSessionOnly();
        });
    });

    it("Sign out everywhere ends portal sessions and revokes devices but keeps passkeys", () => {
        lastAuditId().then((startId) => {
            portalSessionOnly();
            addDeviceAndPasskey();

            adminPost(`/admin/api/person/${member.personId}/sign-in/sign-out-everywhere`);

            cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
            db("SELECT pdv_RevokedAt FROM person_device_pdv WHERE pdv_per_ID = ?", [member.personId]).its("0.pdv_RevokedAt").should("not.be.null");
            db("SELECT COUNT(*) AS n FROM person_passkey_ppk WHERE ppk_per_ID = ?", [member.personId]).its("0.n").should("eq", 1);
            auditRowsSince(member.personId, startId).then((rows) => {
                const events = rows.map((row) => `${row.psa_Event}:${row.psa_Reason ?? ""}:${row.psa_Actor_usr_ID ?? ""}`);
                expect(events).to.include("session_ended:signed_out_everywhere:1");
                expect(events).to.include("session_ended:session_version:");
            });
            portalSessionOnly();
        });
    });

    it("lets only administrators change Disable sign-in", () => {
        cy.makePrivateEditRecordsAPICall("POST", `/admin/api/person/${member.personId}/sign-in/disable`, { reason: "x" }, 403);
        cy.makePrivateUserAPICall("POST", `/admin/api/person/${member.personId}/sign-in/sign-out-everywhere`, null, 403);
        cy.makePrivateAdminAPICall("POST", "/admin/api/person/1/sign-in/disable", { reason: "x" }, 403);
        cy.makePrivateAdminAPICall("POST", `/admin/api/person/${member.personId}/sign-in/disable`, { reason: " " }, 400);
        cy.makePrivateAdminAPICall("POST", `/admin/api/person/${member.personId}/sign-in/enable`, null, 409);
        db("SELECT COUNT(*) AS n FROM person_signin_psi WHERE psi_per_ID = ? AND psi_SignInDisabled = 1", [member.personId])
            .its("0.n")
            .should("eq", 0);
    });

    it("refuses a browser request without the CSRF token", () => {
        cy.clearCookies();
        cy.readEnv("admin.username").then((username) =>
            cy.readEnv("admin.password").then((password) =>
                cy.request({ method: "POST", url: "/admin/", form: true, body: { User: username, Password: password } }),
            ),
        );
        cy.request({
            method: "POST",
            url: `/admin/api/person/${member.personId}/sign-in/disable`,
            body: { reason: "x" },
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 403);
    });
});
