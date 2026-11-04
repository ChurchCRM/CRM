// Shared steps for the member email sign-in specs (#10463, design §6.1).
// Settings go through the admin API, which checks them as the Sign-in tab does.
// The throttle windows, sign-in requests and stored audit rows have no HTTP
// surface, so they are ended and read in SQL.

import { inOwnCookieJar } from "./cookie-jar";

export const ANSWER = "If this address can be used to sign in";

const SETTINGS = [
    "bMemberSignIn",
    "sMemberSignInFamilyRoles",
    "sMemberSignInClassifications",
    "iMemberSignInMinAge",
    "sMemberSignInNotificationRecipientIDs",
    "iMemberSignInRequestsPerIP",
    "iRememberDeviceDays",
];

const BUCKETS = [
    "portal-signin.address",
    "portal-signin.ip",
    "portal-signin.person",
    "portal-signin.office-notice",
    "portal-signin.throttle-audit",
];

const checked = (sql) => (result) => {
    expect(result.error, sql).to.be.null;
    return result.rows;
};

/** A spec's own SQL; cypress/db-access.json must list the spec. */
export const db = (sql, params = []) => cy.dbQuery(sql, params).then(checked(sql));

const fixtureDb = (sql, params = []) => cy.dbQuery(sql, params, { fixture: "cypress/support/member-sign-in.js" }).then(checked(sql));

/** Sets one setting through the admin API, keeping the browser's session. */
export const setConfig = (name, value) => inOwnCookieJar(() => cy.setSystemConfig(name, String(value)));

/** Saves the sign-in settings, then turns member sign-in on with the given rule. */
export const useMemberSignIn = (saved, overrides = {}) => {
    SETTINGS.forEach((name) => {
        cy.getSystemConfig(name).then((value) => {
            saved[name] = value;
        });
    });
    const settings = {
        bMemberSignIn: "1",
        sMemberSignInFamilyRoles: "1,2",
        sMemberSignInClassifications: "1,2",
        iMemberSignInMinAge: "13",
        sMemberSignInNotificationRecipientIDs: "",
        iMemberSignInRequestsPerIP: "1000",
        ...overrides,
    };
    Object.entries(settings).forEach(([name, value]) => setConfig(name, value));
};

export const restoreSettings = (saved) => {
    SETTINGS.forEach((name) => cy.restoreSystemConfig(name, saved[name]));
};

export const resetEmailSignIn = (personIds = []) => {
    BUCKETS.forEach((bucket) => cy.expireSignInThrottle(bucket));
    if (personIds.length > 0) {
        fixtureDb(`DELETE FROM person_signin_request_psr WHERE psr_per_ID IN (${personIds.map(() => "?").join(",")})`, personIds);
    }
    cy.task("mail:clear");
};

export const lastAuditId = () =>
    fixtureDb("SELECT COALESCE(MAX(psa_ID), 0) AS id FROM portal_signin_audit_psa").then((rows) => Number(rows[0].id));

export const auditSince = (afterId) =>
    fixtureDb("SELECT * FROM portal_signin_audit_psa WHERE psa_ID > ? ORDER BY psa_ID", [afterId]);

export const requestsFor = (personId) =>
    fixtureDb("SELECT * FROM person_signin_request_psr WHERE psr_per_ID = ? ORDER BY psr_ID", [personId]);

const csrfToken = (html) => {
    const match = html.match(/name="csrf_token" value="([^"]+)"/);
    expect(match, "CSRF token on the page").to.not.eq(null);
    return match[1];
};

/** Posts the email form the way the page does, in the current cookie jar. */
export const askForEmail = (email) =>
    cy.request("/portal/login").then(({ body }) =>
        cy.request({ method: "POST", url: "/portal/login", form: true, body: { csrf_token: csrfToken(body), email } }),
    );

/** Posts a code from whatever page the current cookie jar can open; `fields` adds e.g. `{ rememberDevice: "1" }`. */
export const postCode = (code, fields = {}) =>
    cy.request("/portal/login").then(({ body }) =>
        cy.request({
            method: "POST",
            url: "/portal/login/code",
            form: true,
            body: { csrf_token: csrfToken(body), code, ...fields },
        }),
    );

export const expectAnswerScreen = (response) => {
    expect(response.status).to.eq(200);
    expect(response.redirects.some((url) => url.includes("/portal/login/code")), "answered with the code page").to.eq(true);
    expect(response.body).to.include(ANSWER);
};

export const mailTo = (address) =>
    cy.task("mail:list", { limit: 200 }).then(({ ok, body }) => {
        expect(ok, "Mailpit is reachable").to.eq(true);
        const wanted = address.toLowerCase();
        return (body?.messages || []).filter((message) =>
            (message.To || []).some((to) => String(to.Address).toLowerCase() === wanted),
        );
    });

const MAIL_WAIT_MS = 10000;
const MAIL_POLL_MS = 200;

/** Sign-in mail goes out after the answer page: waits until `count` messages to the address have arrived. */
export const waitForMail = (address, count = 1, waited = 0) =>
    mailTo(address).then((messages) => {
        if (messages.length >= count || waited >= MAIL_WAIT_MS) {
            return messages;
        }
        return cy.wait(MAIL_POLL_MS, { log: false }).then(() => waitForMail(address, count, waited + MAIL_POLL_MS));
    });

/** The code and link in the newest sign-in email to an address. */
export const signInEmail = (address) =>
    waitForMail(address).then((messages) => {
        expect(messages, `sign-in email to ${address}`).to.not.be.empty;
        return cy.task("mail:get", { id: messages[0].ID }).then(({ body }) => {
            const text = `${body.Text || ""}`;
            const code = text.match(/Your code\s+(\d{6})/);
            const link = `${body.HTML || ""} ${text}`.match(/\/portal\/login\/confirm\/[0-9a-f]{64}/);
            expect(code, "code in the email").to.not.eq(null);
            expect(link, "link in the email").to.not.eq(null);
            return { code: code[1], link: link[0], text, html: body.HTML || "" };
        });
    });
