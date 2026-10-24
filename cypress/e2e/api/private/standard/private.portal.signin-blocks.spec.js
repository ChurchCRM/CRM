/// <reference types="cypress" />

/**
 * Member sign-in row 4 (#10463): the sign-in blocks (docs/member-sign-in-design.md §4,
 * A13). Each ends an open member portal session on its next request. Disable sign-in also
 * refuses staff sign-in with the same answer as a wrong password; an inactive family and
 * awaiting review refuse member sign-in only, so staff sign-in, the API key and a staff
 * portal session go on. Seed staff account noperm.user (Alice Noperm, person 901, family 1),
 * password changeme; seed member Lena Black (person 100, family 20), who signs in with her email.
 *
 * The device comes from an email sign-in that stays signed in; a passkey, which only a
 * WebAuthn ceremony registers, and "awaiting review" are support fixtures. The audit and
 * the person's sign-in status are read through the admin API.
 */

import { inOwnCookieJar } from "../../../../support/cookie-jar";
import { addPasskey, clearAwaitingReview, markAwaitingReview, resetSignIn } from "../../../../support/member-sign-in-fixtures";
import { auditRowsSince, newestAuditId, signInStatus } from "../../../../support/sign-in-audit";

const staff = { username: "noperm.user", password: "changeme", personId: 901, familyId: 1 };
const member = { personId: 100, familyId: 20 };
const GENERIC_MESSAGE = "Invalid login or password";

const adminPost = (url, body = null, expectedStatus = 200) =>
    inOwnCookieJar(() => cy.makePrivateAdminAPICall("POST", url, body, expectedStatus));

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

const portalSessionOnly = (options = {}) => {
    cy.signInAsMember(member.personId, options);
    cy.request("/api/portal/me").its("body.profile.id").should("eq", member.personId);
};

/** A member signed in on a remembered device, who also has a passkey. */
const portalSessionWithDeviceAndPasskey = () => {
    portalSessionOnly({ rememberDevice: true });
    addPasskey(member.personId);
    signInStatus(member.personId).then((status) => {
        expect(status.devices).to.eq(1);
        expect(status.passkeys).to.eq(1);
    });
};

const blocks = [
    {
        name: "an inactive family",
        reason: "family_inactive",
        blocksStaff: false,
        apply: (person) => adminPost(`/api/family/${person.familyId}/activate/false`),
    },
    {
        name: "awaiting review",
        reason: "needs_review",
        blocksStaff: false,
        apply: (person) => markAwaitingReview(person.personId),
    },
    {
        name: "Disable sign-in",
        reason: "sign_in_disabled",
        blocksStaff: true,
        apply: (person) => adminPost(`/admin/api/person/${person.personId}/sign-in/disable`, { reason: "Blocks spec" }),
    },
];

describe("Sign-in blocks for everyone", () => {
    afterEach(() => {
        cy.clearCookies();
        [staff.familyId, member.familyId].forEach((familyId) => cy.makePrivateAdminAPICall("POST", `/api/family/${familyId}/activate/true`));
        clearAwaitingReview([staff.personId, member.personId]);
        resetSignIn([staff.personId, member.personId, 27]);
    });

    blocks.forEach(({ name, reason, blocksStaff, apply }) => {
        describe(name, () => {
            if (!blocksStaff) {
                it("refuses member sign-in but leaves staff sign-in, the API key and a staff portal session open", () => {
                    signIn().its("status").should("eq", 302);
                    cy.request("/api/portal/me").its("body.profile.id").should("eq", staff.personId);
                    apply(staff);

                    cy.request("/api/portal/me").its("body.profile.id").should("eq", staff.personId);
                    cy.request({ url: "/v2/dashboard", followRedirect: false }).its("status").should("eq", 200);
                    signIn().its("status").should("eq", 302);
                    cy.request({ url: "/v2/dashboard", followRedirect: false }).its("status").should("eq", 200);
                    cy.makePrivateNoPermAPICall("GET", "/api/person/2", null, 200);
                    signInStatus(staff.personId).then((status) => {
                        expect(status.maySignIn).to.eq(false);
                        expect(status.reason).to.eq(reason);
                    });
                });
            } else {
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
            }

            it("ends an open portal session on its next request", () => {
                newestAuditId().then((startId) => {
                    portalSessionOnly();
                    apply(member);
                    cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
                    auditRowsSince(member.personId, startId).then((rows) => {
                        const ended = rows.filter((row) => row.event === "session_ended");
                        expect(ended).to.have.length(1);
                        expect(ended[0].reason).to.eq(reason);
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
        newestAuditId().then((startId) => {
            portalSessionWithDeviceAndPasskey();

            adminPost(`/admin/api/person/${member.personId}/sign-in/disable`, { reason: "Blocks spec" });

            cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
            signInStatus(member.personId).then((status) => {
                expect(status.devices).to.eq(0);
                expect(status.passkeys).to.eq(0);
                expect(status.signInDisabled).to.eq(true);
                expect(status.disabledBy.id).to.eq(1);
                expect(status.disabledReason).to.eq("Blocks spec");
            });
            auditRowsSince(member.personId, startId).then((rows) => {
                const disabled = rows.find((row) => row.event === "sign_in_disabled");
                expect(disabled.actorId).to.eq(1);
                expect(disabled.detail).to.eq("devices=1 passkeys=1");
                expect(JSON.stringify(rows)).not.to.contain("Blocks spec");
            });

            adminPost(`/admin/api/person/${member.personId}/sign-in/enable`);
            auditRowsSince(member.personId, startId).then((rows) => {
                expect(rows.map((row) => row.event)).to.include("sign_in_enabled");
            });
            portalSessionOnly();
        });
    });

    it("Sign out everywhere ends portal sessions and revokes devices but keeps passkeys", () => {
        newestAuditId().then((startId) => {
            portalSessionWithDeviceAndPasskey();

            adminPost(`/admin/api/person/${member.personId}/sign-in/sign-out-everywhere`);

            cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
            signInStatus(member.personId).then((status) => {
                expect(status.devices, "no device still signs in").to.eq(0);
                expect(status.passkeys).to.eq(1);
            });
            auditRowsSince(member.personId, startId).then((rows) => {
                const events = rows.map((row) => `${row.event}:${row.reason ?? ""}:${row.actorId ?? ""}`);
                expect(events).to.include("session_ended:signed_out_everywhere:1");
                expect(events).to.include("session_ended:session_version:");
                expect(rows.find((row) => row.reason === "signed_out_everywhere").detail).to.eq("devices=1");
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
        signInStatus(member.personId).its("signInDisabled").should("eq", false);
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
