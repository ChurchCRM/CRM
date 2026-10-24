/// <reference types="cypress" />

import { db } from "../../support/member-sign-in";

// Login as User (#10463 row 12, docs/member-sign-in-design.md §6.5, §17): read-only
// unless changes are allowed, every write recorded with both people, and how a
// session ends.
const MEMBER = 2; // Mathew Campbell, head of the Campbell family
const ADMIN = 1;
const DEVICE_TOKEN = "c".repeat(64);
const SETTINGS = ["bAllowLoginAsUser", "bLoginAsUserCanWrite", "sVolunteerVersion", "iSessionTimeout"];
const REFUSED = "Changes are turned off during Login as User.";

const admin = (method, url, body = null, status = 200) => cy.makePrivateAdminAPICall(method, url, body, status);

const setSetting = (name, value) => admin("POST", `admin/api/system/config/${name}`, { value });

// Posts the staff sign-in form rather than typing into it. A staff page left open
// in the browser answers its own pending requests with a redirect to /admin/ once
// the session ends, which could reload the form while Cypress typed into it.
const signInAsAdmin = () =>
    cy.env(["admin.username", "admin.password"]).then((env) => {
        cy.clearCookies();
        cy.request({
            method: "POST",
            url: "/admin/",
            form: true,
            followRedirect: false,
            body: { User: env["admin.username"], Password: env["admin.password"] },
        }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.not.match(/\/admin\/?$/);
        });
    });

const auditSince = (afterId) =>
    admin("GET", `admin/api/member-portal/audit?personId=${MEMBER}&length=100`).then(({ body }) =>
        body.rows
            .filter((row) => row.id > afterId)
            .reverse()
            .map((row) => ({ event: row.event, reason: row.reason, actorId: row.actorId })),
    );

const newestAuditId = () =>
    admin("GET", `admin/api/member-portal/audit?personId=${MEMBER}&length=1`).then(({ body }) => body.rows[0]?.id ?? 0);

const startLoginAsUser = () =>
    cy.request(`/people/view/${MEMBER}`).then(({ body }) =>
        cy.request({
            method: "POST",
            url: `/admin/api/person/${MEMBER}/login-as-user`,
            headers: { "X-CSRF-Token": body.match(/data-csrf-token="([^"]+)"/)[1] },
            body: {},
        }),
    );

const portalToken = () =>
    cy.request("/portal/").then(({ body }) => body.match(/name="csrf_token" value="([^"]+)"/)[1]);

const portalJSON = (token, url, body = {}) =>
    cy.request({ method: "POST", url, headers: { "X-CSRF-Token": token }, body, failOnStatusCode: false });

const portalForm = (token, url) =>
    cy.request({
        method: "POST",
        url,
        form: true,
        body: { csrf_token: token },
        headers: { Referer: `${Cypress.config("baseUrl").replace(/\/$/, "")}/portal/devices` },
        followRedirect: false,
        failOnStatusCode: false,
    });

const workPhone = () => cy.request("/api/portal/me").then(({ body }) => body.profile.workPhone);

const stopLoginAsUser = (token) =>
    cy.request({ method: "POST", url: "/portal/login-as-user/stop", form: true, body: { csrf_token: token }, followRedirect: false });

const newestSession = () => {
    cy.visit(`/people/view/${MEMBER}`);
    return cy.get("#login-as-user-history [data-cy=login-as-user-session]").first();
};

const actionsOf = (session) =>
    cy
        .wrap(session)
        .find("[data-cy=login-as-user-action]")
        .then((items) => [...items].map((item) => item.innerText.replace(/\s+/g, " ").trim().split(" ").slice(0, 3).join(" ")));

describe("Login as User: changes and endings", () => {
    const saved = {};

    before(() => {
        SETTINGS.forEach((name) => {
            cy.getSystemConfig(name).then((value) => {
                saved[name] = value;
            });
        });
    });

    beforeEach(() => {
        setSetting("bAllowLoginAsUser", "1");
        setSetting("bLoginAsUserCanWrite", "0");
        setSetting("sVolunteerVersion", "v2");
        signInAsAdmin();
    });

    afterEach(() => {
        Object.entries(saved).forEach(([name, value]) => cy.restoreSystemConfig(name, value));
        db("DELETE FROM person_device_pdv WHERE pdv_TokenHash = ?", [DEVICE_TOKEN]);
    });

    it("refuses every change while changes are off, and records each attempt", () => {
        newestAuditId().then((auditBefore) => {
            startLoginAsUser();
            cy.visit("/portal/");
            cy.get("#login-as-user-changes").should("have.text", "Changes are turned off.");

            workPhone().then((before) => {
                portalToken().then((token) => {
                    portalJSON(token, "/api/portal/me", { workPhone: "555-0199" }).then((response) => {
                        expect(response.status).to.eq(403);
                        expect(response.body.message).to.eq(REFUSED);
                    });
                    portalJSON(token, "/api/portal/family/confirm", { result: "no-change" }).its("body.message").should("eq", REFUSED);
                    portalForm(token, "/portal/devices/sign-out-everywhere").then((response) => {
                        expect(response.status).to.eq(303);
                        expect(response.headers.location).to.match(/\/portal\/devices$/);
                    });
                    portalJSON(token, "/api/ministries/me/swaps/1/withdraw").then((response) => {
                        expect(response.status).to.eq(403);
                        expect(response.body.message).to.eq(REFUSED);
                    });

                    cy.visit("/portal/devices");
                    cy.get("#portal-toasts").should("contain", REFUSED);
                    cy.get("#login-as-user-banner").should("be.visible");
                    workPhone().should("eq", before);
                    stopLoginAsUser(token);
                });
            });

            auditSince(auditBefore).then((rows) => {
                expect(rows.map((row) => row.event)).to.deep.equal(["login_as_started", "login_as_stopped"]);
            });
            newestSession().then((session) => {
                actionsOf(session).should("deep.equal", [
                    "POST /api/portal/me 403",
                    "POST /api/portal/family/confirm 403",
                    "POST /portal/devices/sign-out-everywhere 403",
                    "POST /api/ministries/me/swaps/1/withdraw 403",
                ]);
            });
        });
    });

    it("records every change with both people when changes are allowed", () => {
        setSetting("bLoginAsUserCanWrite", "1");
        db(
            "INSERT INTO person_device_pdv (pdv_per_ID, pdv_TokenHash, pdv_Kind, pdv_Label, pdv_CreatedAt, pdv_ExpiresAt) VALUES (?, ?, 'browser', 'Row 12 test', NOW(), NOW() + INTERVAL 1 DAY)",
            [MEMBER, DEVICE_TOKEN],
        );
        db("SELECT pdv_ID FROM person_device_pdv WHERE pdv_TokenHash = ?", [DEVICE_TOKEN]).then((devices) => {
            const deviceId = devices[0].pdv_ID;
            newestAuditId().then((auditBefore) => {
                startLoginAsUser();
                cy.visit("/portal/");
                cy.get("#login-as-user-changes").should("contain", "Changes are allowed");

                workPhone().then((original) => {
                    portalToken().then((token) => {
                        portalJSON(token, "/api/portal/me?from=row12", { workPhone: "555-0199" }).its("status").should("eq", 200);
                        workPhone().should("eq", "555-0199");
                        portalJSON(token, "/api/portal/me", { workPhone: original }).its("status").should("eq", 200);
                        portalJSON(token, "/api/portal/family/confirm", { result: "no-change" }).its("status").should("eq", 200);
                        portalForm(token, `/portal/devices/${deviceId}/sign-out`).its("status").should("eq", 303);
                        stopLoginAsUser(token);
                    });
                });

                auditSince(auditBefore).should("deep.equal", [
                    { event: "login_as_started", reason: null, actorId: ADMIN },
                    { event: "family_confirmed", reason: null, actorId: ADMIN },
                    { event: "device_removed", reason: "signed_out", actorId: ADMIN },
                    { event: "login_as_stopped", reason: null, actorId: ADMIN },
                ]);
                newestSession().then((session) => {
                    cy.wrap(session).should("contain", "Church Admin as Mathew Campbell").and("contain", "Stopped");
                    actionsOf(session).should("deep.equal", [
                        "POST /api/portal/me 200",
                        "POST /api/portal/me 200",
                        "POST /api/portal/family/confirm 200",
                        `POST /portal/devices/${deviceId}/sign-out 303`,
                    ]);
                });
            });
        });
    });

    it("ends with the timeout reason when the waiting staff session times out", () => {
        newestAuditId().then((auditBefore) => {
            startLoginAsUser();
            setSetting("iSessionTimeout", "1");
            cy.wait(2500);
            cy.request({ url: "/portal/", followRedirect: false }).then((response) => {
                expect(response.status).to.eq(302);
                expect(response.headers.location).to.match(/\/admin\/$/);
            });
            cy.restoreSystemConfig("iSessionTimeout", saved.iSessionTimeout);
            cy.request({ url: "/v2/dashboard", followRedirect: false }).its("status").should("eq", 302);

            auditSince(auditBefore).should("deep.equal", [
                { event: "login_as_started", reason: null, actorId: ADMIN },
                { event: "session_ended", reason: "idle", actorId: ADMIN },
            ]);
            signInAsAdmin();
            newestSession().should("contain", "Timed out");
        });
    });

    it("ends with the sign-out reason on Sign out", () => {
        startLoginAsUser();
        cy.request({ url: "/session/end", followRedirect: false }).its("headers.location").should("match", /\/admin\/$/);
        signInAsAdmin();
        newestSession().should("contain", "Signed out");
    });
});
