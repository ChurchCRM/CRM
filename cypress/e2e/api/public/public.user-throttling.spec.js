/// <reference types="cypress" />

/**
 * Login throttling, temporary lockout and uniform answers (#10197).
 * Seed users, password "changeme": login_lockout_user (907), which
 * public.user.spec.js also locks, so it is unlocked before and after each test;
 * locked.user (7), locked until 2100.
 */
const JSON_HEADERS = { "content-type": "application/json" };
const PASSWORD = "changeme";
const GENERIC_ERROR = "Invalid login or password";
const LOCKOUT_USER = "login_lockout_user";
const LOCKOUT_USER_ID = 907;
const LOCKOUT_EMAIL = "login.lockout@example.com";

const login = (userName, password) =>
    cy.apiRequest({ method: "POST", url: "/api/public/user/login", headers: JSON_HEADERS, body: { userName, password } });

const resetRequest = (userName) =>
    cy.apiRequest({ method: "POST", url: "/api/public/user/password-reset", headers: JSON_HEADERS, body: { userName } });

const unlock = (userId) => cy.makePrivateAdminAPICall("POST", `/admin/api/user/${userId}/login/reset`, null, 200);

const setConfig = (name, value) =>
    cy.makePrivateAdminAPICall("POST", `/admin/api/system/config/${name}`, { value: String(value) }, 200);

const expectRefused = (resp) => {
    expect(resp.status).to.eq(401);
    expect(resp.body.error).to.eq(GENERIC_ERROR);
};

const failTimes = (count, userName) => {
    Cypress._.times(count, () => login(userName, "wrong_password").then(expectRefused));
};

const resetMailTo = (address) =>
    cy.task("mail:list", { limit: 100 }).then((result) =>
        (result.body?.messages || []).filter(
            (message) =>
                (message.To || []).some((to) => (to.Address || "").toLowerCase() === address) &&
                (message.Subject || "").includes("Password Reset"),
        ),
    );

describe("API sign-in throttling (#10197)", () => {
    let savedMaxFailedLogins;
    let maxFailedLogins;

    before(() => {
        cy.getSystemConfig("iMaxFailedLogins").then((value) => {
            savedMaxFailedLogins = value;
            maxFailedLogins = Number(value);
            expect(maxFailedLogins, "iMaxFailedLogins").to.be.greaterThan(0);
        });
    });

    beforeEach(() => unlock(LOCKOUT_USER_ID));

    afterEach(() => {
        cy.restoreSystemConfig("iMaxFailedLogins", savedMaxFailedLogins);
        unlock(LOCKOUT_USER_ID);
    });

    describe("Account lockout", () => {
        it("locks the account at iMaxFailedLogins and refuses the right password", () => {
            login(LOCKOUT_USER, PASSWORD).its("status").should("eq", 200);
            failTimes(maxFailedLogins, LOCKOUT_USER);
            login(LOCKOUT_USER, PASSWORD).then(expectRefused);
        });

        it("unlocks by itself when the lockout period ends", () => {
            failTimes(maxFailedLogins, LOCKOUT_USER);
            login(LOCKOUT_USER, PASSWORD).then(expectRefused);

            cy.expireSignInThrottle("signin.lockout", LOCKOUT_USER_ID);
            login(LOCKOUT_USER, PASSWORD).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body).to.have.property("apiKey");
            });
        });

        it("starts counting from zero after the lock expires", () => {
            failTimes(maxFailedLogins, LOCKOUT_USER);
            cy.expireSignInThrottle("signin.lockout", LOCKOUT_USER_ID);

            login(LOCKOUT_USER, "wrong_password").then(expectRefused);
            login(LOCKOUT_USER, PASSWORD).its("status").should("eq", 200);
        });

        it("an administrator can unlock it at once", () => {
            failTimes(maxFailedLogins, LOCKOUT_USER);
            login(LOCKOUT_USER, PASSWORD).then(expectRefused);

            unlock(LOCKOUT_USER_ID);
            login(LOCKOUT_USER, PASSWORD).its("status").should("eq", 200);
        });

        it("never locks when iMaxFailedLogins is 0", () => {
            setConfig("iMaxFailedLogins", 0);
            failTimes(maxFailedLogins + 1, LOCKOUT_USER);
            login(LOCKOUT_USER, PASSWORD).its("status").should("eq", 200);
        });
    });

    describe("Uniform answers", () => {
        it("an unknown user, a wrong password and a locked account get the same answer", () => {
            const answers = [];
            login("no_such_user_10197", "wrong_password").then((resp) => answers.push(resp));
            login(LOCKOUT_USER, "wrong_password").then((resp) => answers.push(resp));
            login("locked.user", PASSWORD).then((resp) => answers.push(resp));

            cy.then(() => {
                answers.forEach((resp) => {
                    expectRefused(resp);
                    expect(resp.body).to.deep.eq(answers[0].body);
                });
            });
        });
    });

    describe("Per-IP throttle", () => {
        const IP_LIMIT = 3;
        let savedIpLimit;

        before(() => {
            cy.getSystemConfig("iMaxFailedLoginsPerIP").then((value) => {
                savedIpLimit = value;
            });
        });

        beforeEach(() => {
            cy.expireSignInThrottle("signin.ip");
            setConfig("iMaxFailedLoginsPerIP", IP_LIMIT);
        });

        afterEach(() => {
            cy.expireSignInThrottle("signin.ip");
            cy.restoreSystemConfig("iMaxFailedLoginsPerIP", savedIpLimit);
        });

        it("refuses every sign-in from the address once the limit is reached", () => {
            Cypress._.times(IP_LIMIT, (n) => login(`ip_probe_${n}`, "wrong_password").then(expectRefused));
            login("admin", PASSWORD).then(expectRefused);

            cy.expireSignInThrottle("signin.ip");
            login("admin", PASSWORD).its("status").should("eq", 200);
        });

        it("counts password reset requests and still answers success", () => {
            Cypress._.times(IP_LIMIT, () =>
                resetRequest("no_such_user_10197").then((resp) => {
                    expect(resp.status).to.eq(200);
                    expect(resp.body).to.deep.eq({ success: true });
                }),
            );
            resetRequest("admin").then((resp) => expect(resp.body).to.deep.eq({ success: true }));
            login("admin", PASSWORD).then(expectRefused);
        });

        it("stays on when iMaxFailedLogins is 0", () => {
            setConfig("iMaxFailedLogins", 0);
            Cypress._.times(IP_LIMIT, (n) => login(`ip_probe_${n}`, "wrong_password").then(expectRefused));
            login("admin", PASSWORD).then(expectRefused);
        });
    });

    describe("Password reset requests", () => {
        let mailpitAvailable = false;

        before(() => {
            cy.task("mail:available").then((result) => {
                mailpitAvailable = result.available;
            });
        });

        beforeEach(() => {
            cy.expireSignInThrottle("password-reset.account");
            cy.task("mail:clear");
        });

        afterEach(() => cy.expireSignInThrottle("password-reset.account"));

        it("emails one account at most three times an hour", function () {
            if (!mailpitAvailable) {
                this.skip();
            }
            Cypress._.times(4, () =>
                resetRequest(LOCKOUT_USER).then((resp) => expect(resp.body).to.deep.eq({ success: true })),
            );
            resetMailTo(LOCKOUT_EMAIL).should("have.length", 3);
        });

        it("a new request replaces the earlier link", function () {
            if (!mailpitAvailable) {
                this.skip();
            }
            resetRequest(LOCKOUT_USER);
            resetMailTo(LOCKOUT_EMAIL).then((messages) => {
                expect(messages).to.have.length(1);
                cy.task("mail:get", { id: messages[0].ID }).then(({ body }) => {
                    const link = `${body.HTML || ""} ${body.Text || ""}`.match(/\/session\/forgot-password\/set\/[0-9a-f]+/);
                    expect(link, "reset link in the first email").to.not.eq(null);
                    resetRequest(LOCKOUT_USER);
                    cy.request(link[0]).its("body").should("contain", "Password Reset Error");
                });
            });
        });
    });
});
