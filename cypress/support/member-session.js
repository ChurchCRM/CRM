// Acting as a member in specs (#10463 row 13). The release-day cutover removed
// the self-service accounts, so a spec reaches the Member Portal as a member
// does: an emailed code (design §6.1), read from Mailpit. The member rule is
// opened to everyone for the spec and put back to the seed's settings after it.

import { askForEmail, mailTo, postCode, waitForMail } from "./member-sign-in";

const RULE_KEYS = ["bMemberSignIn", "sMemberSignInFamilyRoles", "sMemberSignInClassifications", "iMemberSignInMinAge"];
const BUCKETS = ["portal-signin.address", "portal-signin.ip", "portal-signin.person"];
const SESSION_COOKIE = /^CRM-[0-9a-f]{32}$/;

const sessions = {};
let ruleOpened = false;

const db = (sql, params = []) =>
    cy.dbQuery(sql, params, { fixture: "cypress/support/member-session.js" }).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const setConfig = (name, value) =>
    db("INSERT INTO config_cfg (cfg_name, cfg_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE cfg_value = VALUES(cfg_value)", [name, value]);

const openMemberRule = () => {
    ruleOpened = true;
    db(
        "SELECT lst_ID AS list, GROUP_CONCAT(lst_OptionID ORDER BY lst_OptionID) AS ids FROM list_lst WHERE lst_ID IN (1, 2) GROUP BY lst_ID",
    ).then((rows) => {
        const ids = (list) => ["0", ...String(rows.find((row) => Number(row.list) === list)?.ids ?? "").split(",")].filter(Boolean).join(",");
        setConfig("bMemberSignIn", "1");
        setConfig("sMemberSignInFamilyRoles", ids(2));
        setConfig("sMemberSignInClassifications", ids(1));
        setConfig("iMemberSignInMinAge", "0");
    });
};

after(() => {
    if (ruleOpened) {
        db(`DELETE FROM config_cfg WHERE cfg_name IN (${RULE_KEYS.map(() => "?").join(",")})`, RULE_KEYS);
        ruleOpened = false;
    }
});

// A person id is looked up each time, so a spec that changed the address still signs in.
const emailOf = (who) =>
    typeof who === "number"
        ? db("SELECT per_Email AS email FROM person_per WHERE per_ID = ?", [who]).then((rows) => {
              expect(rows[0]?.email, `person ${who}'s email`).to.be.a("string").and.not.be.empty;
              return rows[0].email;
          })
        : cy.wrap(who, { log: false });

/**
 * Signs in to the Member Portal with an emailed code, as a person id or an
 * email address; the browser then holds that member's session.
 * `rememberDevice` ticks Stay signed in on this device.
 */
Cypress.Commands.add("signInAsMember", (who, { rememberDevice = false } = {}) => {
    openMemberRule();
    BUCKETS.forEach((bucket) => cy.expireSignInThrottle(bucket));
    cy.clearCookies();
    let email = "";
    return emailOf(who)
        .then((address) => {
            email = address;
            return mailTo(email);
        })
        .then((before) => {
            const known = new Set(before.map((message) => message.ID));
            askForEmail(email);
            return waitForMail(email, before.length + 1).then((messages) => messages.filter((message) => !known.has(message.ID)));
        })
        .then((fresh) => {
            expect(fresh, `a new sign-in email to ${email}`).to.not.be.empty;
            return cy.task("mail:get", { id: fresh[0].ID });
        })
        .then(({ body }) => {
            const code = `${body.Text || ""}`.match(/Your code\s+(\d{6})/);
            expect(code, "code in the sign-in email").to.not.eq(null);
            return postCode(code[1], rememberDevice ? { rememberDevice: "1" } : {});
        })
        .then((response) => {
            expect(response.status, "code accepted").to.eq(200);
            return cy.getCookies();
        })
        .then((cookies) => {
            const cookie = cookies.find((candidate) => SESSION_COOKIE.test(candidate.name));
            expect(cookie, "member session cookie").to.not.eq(undefined);
            sessions[String(who)] = { name: cookie.name, value: cookie.value };
        });
});

/** Puts the member's session back in the browser, signing in the first time. */
Cypress.Commands.add("useMemberSession", (who) => {
    const session = sessions[String(who)];
    if (!session) {
        return cy.signInAsMember(who);
    }
    cy.clearCookies();
    return cy.setCookie(session.name, session.value, { httpOnly: true });
});

/** A request in the member's session, as the portal pages make them. */
Cypress.Commands.add("makeMemberAPICall", (who, method, url, body, expectedStatus = 200) => {
    cy.useMemberSession(who);
    return cy
        .request({ method, url, body, failOnStatusCode: false, followRedirect: false, headers: { "content-type": "application/json" } })
        .then((response) => {
            const accepted = Array.isArray(expectedStatus) ? expectedStatus : [expectedStatus];
            expect(response.status, `${method} ${url}`).to.be.oneOf(accepted);
            return response;
        });
});

/** Forgets the cached sessions, e.g. after a test ended them. */
Cypress.Commands.add("forgetMemberSessions", () => {
    Object.keys(sessions).forEach((who) => delete sessions[who]);
});
