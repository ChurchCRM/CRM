/// <reference types="cypress" />

import { askForEmail, resetEmailSignIn, restoreSettings, signInEmail, useMemberSignIn } from "../../../support/member-sign-in";
import { absoluteUrl } from "../../../support/urls";

/**
 * The grace for a remembered device's replaced token (#10463,
 * docs/member-sign-in-design.md §6.2, §14): two tabs or parallel requests that
 * resume an idle session with the same cookie both get a session.
 * Seed person 57, Jon Olson: head of household, Member, born 1981.
 */

const member = { personId: 57, firstName: "Jon", email: "jon.olson@example.com" };
const DEVICE_COOKIE = "CRM-portal-device";

const signInRemembered = () => {
    resetEmailSignIn([member.personId]);
    cy.clearCookies();
    askForEmail(member.email);
    signInEmail(member.email).then(({ code }) => {
        cy.visit("/portal/login/code");
        cy.get("#portalRememberDevice").check();
        cy.get("#portalSignInCode").type(code);
        cy.get("#portalSignInCodeSubmit").click();
        cy.location("pathname").should("match", /\/portal\/$/);
    });
    return cy.getCookie(DEVICE_COOKIE).its("value");
};

/** A request from a browser that holds only this device cookie, as after the session went idle or closed. */
const resumeWith = (token) => {
    cy.clearCookies();
    cy.setCookie(DEVICE_COOKIE, token, { path: "/portal", httpOnly: true });
    return cy.request({ url: "/portal/", followRedirect: false });
};

const setsDeviceCookie = (response) =>
    [].concat(response.headers["set-cookie"] || []).some((header) => header.startsWith(`${DEVICE_COOKIE}=`));

const expectSignedIn = (response) => {
    expect(response.status, "the portal opens").to.eq(200);
    expect(response.body).to.include(`Hello ${member.firstName}`);
};

const expectSignedOut = (response) => {
    expect(response.status).to.eq(302);
    expect(response.redirectedToUrl).to.match(/\/portal\/login$/);
};

const auditRows = () =>
    cy
        .makePrivateAdminAPICall("GET", `/admin/api/member-portal/audit?personId=${member.personId}&length=100`, null, 200)
        .its("body.rows");

const lastAuditId = () => auditRows().then((rows) => (rows.length > 0 ? rows[0].id : 0));

const deviceSignInsSince = (startId) =>
    auditRows().then((rows) =>
        rows
            .filter((row) => row.id > startId && row.event === "signed_in")
            .reverse()
            .map((row) => `${row.method}:${row.detail ?? ""}`),
    );

const signOutEverywhere = () =>
    cy.makePrivateAdminAPICall("POST", `/admin/api/person/${member.personId}/sign-in/sign-out-everywhere`, {}, 200);

describe("Member Portal device token grace", () => {
    const saved = {};

    before(() => {
        cy.task("mail:available").its("available").should("eq", true);
        useMemberSignIn(saved, { iRememberDeviceDays: "30" });
    });

    after(() => {
        signOutEverywhere();
        restoreSettings(saved);
    });

    it("lets a second request with the token just replaced sign in too, without replacing it again", () => {
        signInRemembered().then((first) => {
            lastAuditId().then((startId) => {
                resumeWith(first).then((response) => {
                    expectSignedIn(response);
                    expect(setsDeviceCookie(response), "the first request gets the new token").to.eq(true);
                });
                cy.getCookie(DEVICE_COOKIE)
                    .its("value")
                    .should("not.eq", first)
                    .then((second) => {
                        resumeWith(first).then((response) => {
                            expectSignedIn(response);
                            expect(setsDeviceCookie(response), "the second request leaves the cookie alone").to.eq(false);
                        });

                        resumeWith(second).then(expectSignedIn);
                        resumeWith(first).then(expectSignedOut);

                        deviceSignInsSince(startId).should("deep.equal", ["device:", "device:previous_token", "device:"]);
                        auditRows().then((rows) => {
                            const grace = rows.find((row) => row.id > startId && row.detail === "previous_token");
                            expect(grace.summary).to.eq("With the token the device replaced moments ago");
                        });
                    });
            });
        });
    });

    it("gives two requests that resume at the same moment with the same cookie a session each", () => {
        signInRemembered().then((first) => {
            cy.clearCookies();
            cy.visit("/portal/login");
            cy.clearCookies();
            cy.setCookie(DEVICE_COOKIE, first, { path: "/portal", httpOnly: true });
            cy.window()
                .then((win) =>
                    Promise.all(
                        [1, 2].map((tab) =>
                            win
                                .fetch(absoluteUrl(`/portal/?tab=${tab}`), { cache: "no-store", credentials: "same-origin", redirect: "manual" })
                                .then((response) => response.text().then((body) => ({ status: response.status, body }))),
                        ),
                    ),
                )
                .then((responses) => {
                    responses.forEach(({ status, body }) => {
                        expect(status, "each request opens the portal").to.eq(200);
                        expect(body).to.include(`Hello ${member.firstName}`);
                    });
                });
        });
    });

    it("refuses the replaced token once its grace has passed, and keeps the device", () => {
        signInRemembered().then((first) => {
            resumeWith(first).then(expectSignedIn);
            cy.getCookie(DEVICE_COOKIE)
                .its("value")
                .then((second) => {
                    cy.endDeviceTokenGrace(member.personId);
                    resumeWith(first).then(expectSignedOut);
                    cy.getCookie(DEVICE_COOKIE).should("be.null");
                    resumeWith(second).then(expectSignedIn);
                });
        });
    });

    it("refuses the replaced token of a device the member signed out", () => {
        signInRemembered().then((first) => {
            resumeWith(first).then(expectSignedIn);
            cy.visit("/portal/devices");
            cy.get("[data-this-device]").parents("[data-device-row]").find("[data-device-sign-out]").click();
            cy.location("pathname").should("match", /\/portal\/login$/);

            resumeWith(first).then(expectSignedOut);
            cy.getCookie(DEVICE_COOKIE).should("be.null");
        });
    });

    it("refuses the replaced token after an administrator signs the person out everywhere", () => {
        signInRemembered().then((first) => {
            resumeWith(first).then(expectSignedIn);
            cy.getCookie(DEVICE_COOKIE)
                .its("value")
                .then((second) => {
                    signOutEverywhere();
                    resumeWith(first).then(expectSignedOut);
                    resumeWith(second).then(expectSignedOut);
                });
        });
    });
});
