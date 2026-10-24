// Shared steps for the member email sign-in specs (#10463, design §6.1).
// The settings, throttles and audit have no HTTP surface until row 9's admin
// pages, so they are set and read in SQL.

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

export const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

export const setConfig = (name, value) =>
    db(
        "INSERT INTO config_cfg (cfg_name, cfg_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE cfg_value = VALUES(cfg_value)",
        [name, String(value)],
    );

/** Saves the sign-in settings, then turns member sign-in on with the given rule. */
export const useMemberSignIn = (saved, overrides = {}) => {
    db(`SELECT cfg_name, cfg_value FROM config_cfg WHERE cfg_name IN (${SETTINGS.map(() => "?").join(",")})`, SETTINGS).then(
        (rows) => {
            rows.forEach((row) => {
                saved[row.cfg_name] = row.cfg_value;
            });
        },
    );
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
    SETTINGS.forEach((name) => {
        if (Object.hasOwn(saved, name)) {
            setConfig(name, saved[name]);
        } else {
            db("DELETE FROM config_cfg WHERE cfg_name = ?", [name]);
        }
    });
};

export const resetEmailSignIn = (personIds = []) => {
    BUCKETS.forEach((bucket) => cy.expireSignInThrottle(bucket));
    if (personIds.length > 0) {
        db(`DELETE FROM person_signin_request_psr WHERE psr_per_ID IN (${personIds.map(() => "?").join(",")})`, personIds);
    }
    cy.task("mail:clear");
};

export const lastAuditId = () =>
    db("SELECT COALESCE(MAX(psa_ID), 0) AS id FROM portal_signin_audit_psa").then((rows) => Number(rows[0].id));

export const auditSince = (afterId) =>
    db("SELECT * FROM portal_signin_audit_psa WHERE psa_ID > ? ORDER BY psa_ID", [afterId]);

export const requestsFor = (personId) =>
    db("SELECT * FROM person_signin_request_psr WHERE psr_per_ID = ? ORDER BY psr_ID", [personId]);

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
