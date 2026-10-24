/// <reference types="cypress" />

import { askForEmail, postCode, signInEmail } from "../../../support/member-sign-in";

// Member sign-in turned off (#10463, docs/member-sign-in-design.md §5): members who
// are signed in are signed out on their next request. Remembered devices stay for
// when it is back on, and a portal session opened from a staff sign-in carries on.
const EMAIL_MEMBER = { personId: 57, firstName: "Jon" }; // Jon Olson
const DEVICE_MEMBER = { personId: 58, firstName: "Sandra", email: "sandra.vasquez@example.com" }; // Sandra Olson
const STAFF = { username: "tony.wade@example.com", password: "basicjoe", personId: 3 };
const DEVICE_COOKIE = "CRM-portal-device";
const UNAVAILABLE = "Member sign-in isn't available yet.";

const admin = (method, url, body = null, status = 200) => cy.makePrivateAdminAPICall(method, url, body, status);

const setMemberSignIn = (on) => admin("POST", "admin/api/system/config/bMemberSignIn", { value: on ? "1" : "0" });

const newestAuditId = (personId) =>
    admin("GET", `admin/api/member-portal/audit?personId=${personId}&length=1`).then(({ body }) => body.rows[0]?.id ?? 0);

const auditSince = (personId, afterId) =>
    admin("GET", `admin/api/member-portal/audit?personId=${personId}&length=100`).then(({ body }) =>
        body.rows
            .filter((row) => row.id > afterId)
            .reverse()
            .map((row) => ({ event: row.event, method: row.method, reason: row.reason })),
    );

const portal = () => cy.request({ url: "/portal/", followRedirect: false });

const expectSignedIn = (firstName) =>
    portal().then((response) => {
        expect(response.status, "the portal opens").to.eq(200);
        expect(response.body).to.include(`Hello ${firstName}`);
    });

const expectSentToSignIn = () =>
    portal().then((response) => {
        expect(response.status).to.eq(302);
        expect(response.redirectedToUrl).to.match(/\/portal\/login$/);
    });

const closeBrowser = () =>
    cy.getCookies().then((cookies) =>
        cookies.filter((cookie) => /^CRM-[0-9a-f]{32}$/.test(cookie.name)).forEach((cookie) => cy.clearCookie(cookie.name)),
    );

const signInOnRememberedDevice = ({ email }) => {
    ["portal-signin.address", "portal-signin.ip", "portal-signin.person"].forEach((bucket) => cy.expireSignInThrottle(bucket));
    cy.clearCookies();
    cy.task("mail:clear");
    askForEmail(email);
    signInEmail(email).then(({ code }) => postCode(code, { rememberDevice: "1" }).its("status").should("eq", 200));
    cy.getCookie(DEVICE_COOKIE).should("exist");
    closeBrowser();
};

describe("Member sign-in turned off", () => {
    before(() => {
        cy.task("mail:available").its("available").should("eq", true);
        // Opens the member rule to everyone for this spec; member-session.js puts it back afterwards.
        cy.signInAsMember(EMAIL_MEMBER.personId);
    });

    beforeEach(() => {
        setMemberSignIn(true);
    });

    it("signs out a member signed in by email on their next request, onto the sign-in page", () => {
        cy.signInAsMember(EMAIL_MEMBER.personId);
        expectSignedIn(EMAIL_MEMBER.firstName);

        newestAuditId(EMAIL_MEMBER.personId).then((afterId) => {
            setMemberSignIn(false);
            cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
            expectSentToSignIn();
            auditSince(EMAIL_MEMBER.personId, afterId).should("deep.equal", [
                { event: "session_ended", method: "email_code", reason: "member_sign_in_off" },
            ]);
        });

        cy.visit("/portal/profile");
        cy.location("pathname").should("eq", "/portal/login");
        cy.contains(UNAVAILABLE).should("be.visible");
    });

    it("signs out a member on a remembered device, and the device signs in again once sign-in is back on", () => {
        signInOnRememberedDevice(DEVICE_MEMBER);
        expectSignedIn(DEVICE_MEMBER.firstName);

        newestAuditId(DEVICE_MEMBER.personId).then((afterId) => {
            setMemberSignIn(false);
            expectSentToSignIn();
            closeBrowser();
            expectSentToSignIn();
            cy.getCookie(DEVICE_COOKIE).should("exist");
            auditSince(DEVICE_MEMBER.personId, afterId).should("deep.equal", [
                { event: "session_ended", method: "device", reason: "member_sign_in_off" },
            ]);
        });

        setMemberSignIn(true);
        expectSignedIn(DEVICE_MEMBER.firstName);
        cy.request({ url: "/session/end", followRedirect: false });
    });

    it("keeps a portal session opened from a staff sign-in, and the staff side", () => {
        cy.clearCookies();
        cy.request({ method: "POST", url: "/admin/", form: true, body: { User: STAFF.username, Password: STAFF.password } });
        portal().its("status").should("eq", 200);

        setMemberSignIn(false);
        portal().its("status").should("eq", 200);
        cy.request("/api/portal/me").its("body.profile.id").should("eq", STAFF.personId);
        cy.request({ url: "/v2/dashboard", followRedirect: false }).its("status").should("eq", 200);
    });
});
