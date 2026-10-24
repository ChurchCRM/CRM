/// <reference types="cypress" />

/**
 * Member sign-in, row 1 (#10463): the portal principal, the shared step and
 * PortalSessionMiddleware (docs/member-sign-in-design.md §3, §5, §6.4, §11.2).
 *
 * A signed-in staff or self-service session that opens the portal becomes a
 * STAFF portal session for its own person. Once the staff session itself has
 * timed out (iSessionTimeout), only the portal session is left: that is the
 * "portal principal, no user" request the portal must accept and every staff
 * route must refuse. Disable sign-in also blocks the staff session (row 4). The
 * session version and the audit have no HTTP surface until row 9, so they are set
 * and read with cy.dbQuery().
 */

const staffUser = { username: "tony.wade@example.com", password: "basicjoe", personId: 3 };
const memberUser = { username: "lena.black.editself.notes@example.com", password: "changeme", personId: 100 };
const touchedConfig = ["iSessionTimeout", "iPortalIdleMinutes"];

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const setConfig = (name, value) =>
    db(
        "INSERT INTO config_cfg (cfg_name, cfg_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE cfg_value = VALUES(cfg_value)",
        [name, value],
    );

const signIn = ({ username, password }) => {
    cy.clearCookies();
    cy.request({ method: "POST", url: "/session/begin", form: true, body: { User: username, Password: password } });
};

const openPortal = () => cy.request("/portal/").its("status").should("eq", 200);

const endStaffSession = () => {
    setConfig("iSessionTimeout", "1");
    cy.wait(2100);
};

const auditRowsSince = (personId, afterId) =>
    db("SELECT * FROM portal_signin_audit_psa WHERE psa_per_ID = ? AND psa_ID > ? ORDER BY psa_ID", [personId, afterId]);

const lastAuditId = () =>
    db("SELECT COALESCE(MAX(psa_ID), 0) AS id FROM portal_signin_audit_psa").then((rows) => Number(rows[0].id));

describe("Member Portal session (portal principal)", () => {
    const savedConfig = {};

    before(() => {
        db("SELECT cfg_name, cfg_value FROM config_cfg WHERE cfg_name IN (?, ?)", touchedConfig).then((rows) => {
            rows.forEach((row) => {
                savedConfig[row.cfg_name] = row.cfg_value;
            });
        });
    });

    afterEach(() => {
        touchedConfig.forEach((name) => {
            if (Object.hasOwn(savedConfig, name)) {
                setConfig(name, savedConfig[name]);
            } else {
                db("DELETE FROM config_cfg WHERE cfg_name = ?", [name]);
            }
        });
        db("UPDATE person_signin_psi SET psi_SignInDisabled = 0 WHERE psi_per_ID IN (?, ?)", [
            staffUser.personId,
            memberUser.personId,
        ]);
        cy.clearCookies();
    });

    it("starts a STAFF portal session for a self-service sign-in and audits it without secrets", () => {
        lastAuditId().then((startId) => {
            signIn(memberUser);
            openPortal();

            db("SELECT * FROM person_signin_psi WHERE psi_per_ID = ?", [memberUser.personId]).then((rows) => {
                expect(rows).to.have.length(1);
                expect(rows[0].psi_LastMethod).to.eq("staff");
                expect(rows[0].psi_FirstSignIn).to.not.be.null;
                expect(rows[0].psi_LastSignIn).to.not.be.null;
                expect(rows[0].psi_LastActivity).to.not.be.null;
                expect(rows[0].psi_ProvenEmail).to.be.null;
            });

            cy.getCookies().then((cookies) => {
                const sessionValues = cookies.filter((c) => c.name.startsWith("CRM-")).map((c) => c.value);
                expect(sessionValues).to.not.be.empty;
                auditRowsSince(memberUser.personId, startId).then((rows) => {
                    const signedIn = rows.filter((row) => row.psa_Event === "signed_in");
                    expect(signedIn).to.have.length(1);
                    expect(signedIn[0].psa_Method).to.eq("staff");
                    expect(signedIn[0].psa_Reason).to.be.null;
                    expect(signedIn[0].psa_Actor_usr_ID).to.be.null;
                    expect(signedIn[0].psa_Email).to.be.null;
                    expect(signedIn[0].psa_IP).to.be.a("string").and.not.be.empty;

                    const stored = JSON.stringify(rows);
                    expect(stored).to.not.include(memberUser.password);
                    sessionValues.forEach((value) => expect(stored).to.not.include(value));
                });
            });
        });
    });

    it("accepts a portal session without a staff session and refuses it on every staff route", () => {
        signIn(staffUser);
        openPortal();
        cy.request(`/api/person/${staffUser.personId}`).its("status").should("eq", 200);

        endStaffSession();

        cy.request("/api/portal/me").then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.profile.id).to.eq(staffUser.personId);
        });
        cy.request("/portal/profile").its("status").should("eq", 200);

        cy.request({ url: `/api/person/${staffUser.personId}`, failOnStatusCode: false })
            .its("status")
            .should("eq", 401);
        cy.request({ url: "/v2/dashboard", followRedirect: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.redirectedToUrl).to.include("/session/begin");
        });
        cy.request({ url: "/PropertyList.php", followRedirect: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.redirectedToUrl).to.include("/session/begin");
        });
    });

    it("ends a portal session on the next request after its session version changes", () => {
        lastAuditId().then((startId) => {
            signIn(staffUser);
            openPortal();
            endStaffSession();
            cy.request("/api/portal/me").its("status").should("eq", 200);

            db("UPDATE person_signin_psi SET psi_SessionVersion = psi_SessionVersion + 1 WHERE psi_per_ID = ?", [
                staffUser.personId,
            ]);

            cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
            cy.request({ url: "/portal/", followRedirect: false }).then((response) => {
                expect(response.status).to.eq(302);
                expect(response.redirectedToUrl).to.include("/portal/login");
            });

            auditRowsSince(staffUser.personId, startId).then((rows) => {
                const ended = rows.filter((row) => row.psa_Event === "session_ended");
                expect(ended).to.have.length(1);
                expect(ended[0].psa_Reason).to.eq("session_version");
                expect(ended[0].psa_Method).to.eq("staff");
            });
        });
    });

    it("ends a portal session on the next request after Disable sign-in", () => {
        lastAuditId().then((startId) => {
            signIn(staffUser);
            openPortal();
            endStaffSession();

            db("UPDATE person_signin_psi SET psi_SignInDisabled = 1 WHERE psi_per_ID = ?", [staffUser.personId]);

            cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
            auditRowsSince(staffUser.personId, startId).then((rows) => {
                const ended = rows.filter((row) => row.psa_Event === "session_ended");
                expect(ended).to.have.length(1);
                expect(ended[0].psa_Reason).to.eq("sign_in_disabled");
            });
        });
    });

    it("ends both the staff session and its portal session once Disable sign-in is on", () => {
        lastAuditId().then((startId) => {
            signIn(staffUser);
            openPortal();

            db("UPDATE person_signin_psi SET psi_SignInDisabled = 1 WHERE psi_per_ID = ?", [staffUser.personId]);

            cy.request({ url: "/portal/", followRedirect: false }).then((response) => {
                expect(response.status).to.eq(302);
                expect(response.redirectedToUrl).to.include("/portal/login");
            });
            cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
            cy.request({ url: "/v2/dashboard", followRedirect: false }).its("status").should("eq", 302);

            auditRowsSince(staffUser.personId, startId).then((rows) => {
                expect(rows.map((row) => row.psa_Event)).not.to.include("refused");
            });
        });
    });

    it("starts a new STAFF portal session when a live staff session outlasts a version change", () => {
        lastAuditId().then((startId) => {
            signIn(staffUser);
            openPortal();

            db("UPDATE person_signin_psi SET psi_SessionVersion = psi_SessionVersion + 1 WHERE psi_per_ID = ?", [
                staffUser.personId,
            ]);

            cy.request("/api/portal/me").its("status").should("eq", 200);
            auditRowsSince(staffUser.personId, startId).then((rows) => {
                expect(rows.map((row) => `${row.psa_Event}:${row.psa_Reason ?? ""}`)).to.deep.equal([
                    "signed_in:",
                    "session_ended:session_version",
                    "signed_in:",
                ]);
            });
        });
    });

    it("ends a portal session that sat idle for iPortalIdleMinutes", () => {
        lastAuditId().then((startId) => {
            signIn(staffUser);
            openPortal();
            setConfig("iPortalIdleMinutes", "1");
            endStaffSession();
            cy.request("/api/portal/me").its("status").should("eq", 200);

            cy.wait(61500);

            cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
            auditRowsSince(staffUser.personId, startId).then((rows) => {
                const ended = rows.filter((row) => row.psa_Event === "session_ended");
                expect(ended).to.have.length(1);
                expect(ended[0].psa_Reason).to.eq("idle");
            });
        });
    });
});
