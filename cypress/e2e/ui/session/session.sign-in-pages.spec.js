/// <reference types="cypress" />

import {
    askForEmail,
    auditSince,
    lastAuditId,
    postCode,
    resetEmailSignIn,
    restoreSettings,
    setConfig,
    signInEmail,
    useMemberSignIn,
} from "../../../support/member-sign-in";

/**
 * Member sign-in row 6 (#10463): the sign-in pages and switching between staff
 * and portal (docs/member-sign-in-design.md §6.4, §7, §17 "Sign-in pages").
 * Judith Matthews (person 95) has a staff account and an email of her own;
 * Constance Hart (person 6) is a member with no staff account.
 */

const judith = { personId: 95, email: "judith.matthews@example.com", username: "judith.matthews@example.com", password: "noMoney$" };
const constance = { personId: 6, email: "constance.hart@example.com" };
const standard = { username: "tony.wade@example.com", password: "basicjoe" };

const signInAtAdmin = ({ username, password }) => {
    cy.get("#UserBox").type(username);
    cy.get("#PasswordBox").type(`${password}{enter}`, { log: false });
};

const emailSignIn = (email) => {
    askForEmail(email);
    signInEmail(email).then(({ code }) => postCode(code).its("status").should("eq", 200));
};

const expectStaffSignInRedirect = (url) =>
    cy.request({ url, followRedirect: false }).then((response) => {
        expect(response.status, url).to.eq(302);
        expect(response.redirectedToUrl, url).to.match(/\/admin\/$/);
    });

describe("Sign-in pages", () => {
    const saved = {};

    before(() => {
        cy.rememberTestEnv(["admin.username", "admin.password"]);
        cy.task("mail:available").its("available").should("eq", true);
        useMemberSignIn(saved, { sMemberSignInFamilyRoles: "0,1,2", sMemberSignInClassifications: "0,1,2" });
    });

    beforeEach(() => {
        cy.clearCookies();
        resetEmailSignIn([judith.personId, constance.personId]);
    });

    after(() => {
        restoreSettings(saved);
    });

    const admin = () => ({ username: Cypress.testEnv("admin.username"), password: Cypress.testEnv("admin.password") });

    describe("signed out", () => {
        it("sends the web root to /portal/login, whose Staff sign-in link opens the staff sign-in at /admin", () => {
            cy.visit("/");
            cy.location("pathname").should("eq", "/portal/login");
            cy.get("#portalSignInEmail").should("be.visible");
            cy.get("#portalStaffSignIn").should("have.attr", "href", "/admin/").click();
            cy.location("pathname").should("eq", "/admin/");
            cy.get("#UserBox").should("be.visible");
            cy.get("#PasswordBox").should("be.visible");
        });

        it("sends the web root to /portal/login while member sign-in is off, with the same Staff sign-in link", () => {
            setConfig("bMemberSignIn", "0");
            cy.visit("/");
            cy.location("pathname").should("eq", "/portal/login");
            cy.contains("Member sign-in isn't available yet");
            cy.get("#portalStaffSignIn").should("have.attr", "href", "/admin/");
            setConfig("bMemberSignIn", "1");
        });

        it("redirects /session/begin to /admin, keeping the query, and re-posts an old form there", () => {
            cy.request({ url: "/session/begin?username=someone%40example.com", followRedirect: false }).then((response) => {
                expect(response.status).to.eq(302);
                expect(response.redirectedToUrl).to.match(/\/admin\/\?username=someone%40example\.com$/);
            });
            cy.request({ method: "POST", url: "/session/begin", form: true, body: { User: "x", Password: "y" }, followRedirect: false }).then(
                (response) => {
                    expect(response.status).to.eq(307);
                    expect(response.redirectedToUrl).to.match(/\/admin\/$/);
                },
            );
            cy.visit("/session/begin?username=someone%40example.com");
            cy.location("pathname").should("eq", "/admin/");
            cy.get("#UserBox").should("have.value", "someone@example.com");
        });

        it("signs in through a form still posting to /session/begin", () => {
            cy.request({ method: "POST", url: "/session/begin", form: true, body: { User: standard.username, Password: standard.password } })
                .its("redirects")
                .should("satisfy", (redirects) => redirects.some((url) => url.includes("/v2/dashboard")));
            cy.request("/api/persons/latest").its("status").should("eq", 200);
        });

        it("sends a signed-out staff page to /admin and back to it after sign-in", () => {
            cy.visit("/groups/dashboard");
            cy.location("pathname").should("eq", "/admin/");
            signInAtAdmin(admin());
            cy.location("pathname").should("eq", "/groups/dashboard");

            cy.clearCookies();
            cy.visit("/PropertyList.php?Type=p");
            cy.location("pathname").should("eq", "/admin/");
            signInAtAdmin(admin());
            cy.location("pathname").should("eq", "/PropertyList.php");
            cy.location("search").should("eq", "?Type=p");
        });
    });

    describe("staff sessions", () => {
        it("gives an administrator the Admin Dashboard at /admin", () => {
            cy.visit("/admin/");
            signInAtAdmin(admin());
            cy.visit("/admin/");
            cy.location("pathname").should("eq", "/admin/");
            cy.contains("Admin Dashboard");
            cy.get("#UserBox").should("not.exist");
        });

        it("sends other staff from /admin to their dashboard, and keeps every other /admin page admin-only", () => {
            cy.visit("/admin/");
            signInAtAdmin(standard);
            cy.visit("/admin/");
            cy.location("pathname").should("eq", "/v2/dashboard");
            cy.visit("/admin/system/users");
            cy.location("pathname").should("eq", "/v2/access-denied");
            cy.request({ url: "/admin/api/member-portal/sign-in", failOnStatusCode: false }).its("status").should("eq", 403);
        });

        it("goes to its own portal and back without signing in again", () => {
            cy.visit("/admin/");
            signInAtAdmin(standard);
            cy.get('[aria-label="Open user menu"]').click();
            cy.contains(".dropdown-item", "Member Portal").click();
            cy.location("pathname").should("eq", "/portal/");
            cy.get("#portalAccountChangePassword").should("exist");
            cy.get("#portal-account-toggle").click();
            cy.get("#portalAccountAdminConsole").should("have.attr", "href", "/v2/dashboard").click();
            cy.location("pathname").should("eq", "/v2/dashboard");
            cy.get("#UserBox").should("not.exist");
        });

        it("signs out of the staff side and its portal session together", () => {
            cy.visit("/admin/");
            signInAtAdmin(standard);
            cy.visit("/portal/");
            cy.get("#portal-account-toggle").click();
            cy.get("#portalAccountSignOut").click();
            cy.location("pathname").should("eq", "/admin/");
            cy.request({ url: "/portal/", followRedirect: false }).its("redirectedToUrl").should("match", /\/portal\/login$/);
            expectStaffSignInRedirect("/v2/dashboard");
        });
    });

    describe("member sessions", () => {
        it("sends the web root to the portal and refuses every staff route", () => {
            emailSignIn(judith.email);
            cy.visit("/");
            cy.location("pathname").should("eq", "/portal/");
            ["/v2/dashboard", "/admin/system/users", "/people/view/2", "/PropertyList.php"].forEach(expectStaffSignInRedirect);
            cy.request({ url: "/api/persons/latest", failOnStatusCode: false }).its("status").should("eq", 401);
            cy.request({ url: "/admin/api/member-portal/sign-in", failOnStatusCode: false }).its("status").should("eq", 401);
            cy.visit("/admin/");
            cy.get("#UserBox").should("be.visible");
            cy.request("/api/portal/me").its("body.profile.id").should("eq", judith.personId);
        });

        it("hides Change Password and two-factor, whose pages answer 404", () => {
            emailSignIn(judith.email);
            cy.visit("/portal/profile");
            cy.get("#portalAccountChangePassword").should("not.exist");
            cy.get("#portal-profile-security").should("not.exist");
            cy.request({ url: "/portal/profile/password", failOnStatusCode: false }).its("status").should("eq", 404);
            cy.request({ url: "/portal/profile/two-factor", failOnStatusCode: false }).its("status").should("eq", 404);
        });

        it("shows no Admin Console to a member without a staff account", () => {
            emailSignIn(constance.email);
            cy.visit("/portal/");
            cy.get("#portalAccountAdminConsole").should("not.exist");
        });

        it("sends Admin Console to the /admin sign-in, where a staff sign-in replaces the member session", () => {
            emailSignIn(judith.email);
            lastAuditId().then((startId) => {
                cy.visit("/portal/");
                cy.get("#portal-account-toggle").click();
                cy.get("#portalAccountAdminConsole").should("have.attr", "href", "/admin/").click();
                cy.location("pathname").should("eq", "/admin/");
                signInAtAdmin(judith);
                cy.location("pathname").should("eq", "/v2/dashboard");

                cy.visit("/portal/");
                cy.get("#portalAccountAdminConsole").should("have.attr", "href", "/v2/dashboard");
                cy.get("#portalAccountChangePassword").should("exist");
                auditSince(startId).then((rows) => {
                    const events = rows.map((row) => [row.psa_per_ID, row.psa_Event, row.psa_Method, row.psa_Reason]);
                    expect(events).to.deep.eq([
                        [judith.personId, "session_ended", "email_code", "replaced"],
                        [judith.personId, "signed_in", "staff", null],
                    ]);
                });
            });
        });

        it("ends a staff session in the same browser when a member signs in by email", () => {
            cy.visit("/admin/");
            signInAtAdmin(standard);
            cy.location("pathname").should("eq", "/v2/dashboard");
            cy.visit("/portal/login");
            cy.get("#portalSignInEmail").should("be.visible");

            emailSignIn(constance.email);
            cy.request("/api/portal/me").its("body.profile.id").should("eq", constance.personId);
            expectStaffSignInRedirect("/v2/dashboard");
            cy.request({ url: "/api/persons/latest", failOnStatusCode: false }).its("status").should("eq", 401);
            cy.visit("/portal/");
            cy.get("#portalAccountAdminConsole").should("not.exist");
            cy.get("#portalAccountChangePassword").should("not.exist");
        });
    });
});
