/// <reference types="cypress" />

const USER_ID = 901;
const USER_NAME = "noperm.user";
// password_hash() of blank passwords: what an account holds if its password was saved blank.
const EMPTY_PASSWORD_HASH = "$2y$10$vzvYHdh43Ou9F/8KQmRuJ.oQKC4xq7VTTXfYroNpd4a0B1dD8PfPe";
const WHITESPACE_PASSWORDS = [
    { label: "spaces", password: "   ", hash: "$2y$10$OtuJWo69GCiFWW61G4xqKe3BESdZuBpJw5W9XVj.Tl.oD6RLaK0qW" },
    { label: "a no-break space", password: " ", hash: "$2y$10$HUM3BvdrhILAA35f.XdTcOnAy.PzMaZgW1XZhgKkJszWd558p9nIC" },
];

describe("Empty password", () => {
    let storedPassword;
    let storedApiKey;

    before(() => {
        cy.dbQuery("SELECT usr_Password, usr_apiKey FROM user_usr WHERE usr_per_ID = ?", [USER_ID]).then(
            ({ rows }) => {
                storedPassword = rows[0].usr_Password;
                storedApiKey = rows[0].usr_apiKey;
            },
        );
    });

    after(() => {
        cy.dbQuery(
            "UPDATE user_usr SET usr_Password = ?, usr_apiKey = ?, usr_FailedLogins = 0 WHERE usr_per_ID = ?",
            [storedPassword, storedApiKey, USER_ID],
        );
    });

    it("Admin Change password refuses a blank password", () => {
        cy.setupAdminSession();
        cy.visit(`/admin/system/user/${USER_ID}/changePassword`);
        cy.get("#NewPassword1").closest("form").submit();
        cy.get("#NewPasswordError").should("have.text", "Enter a new password");
        cy.contains("Password Change Successful").should("not.exist");
    });

    it("Admin Change password refuses a password of only a no-break space", () => {
        cy.setupAdminSession();
        cy.visit(`/admin/system/user/${USER_ID}/changePassword`);
        cy.get("input[name=csrf_token]")
            .invoke("val")
            .then((csrfToken) =>
                cy.request({
                    method: "POST",
                    url: `/admin/system/user/${USER_ID}/changePassword`,
                    form: true,
                    body: { csrf_token: csrfToken, NewPassword1: " ", NewPassword2: " " },
                }),
            )
            .its("body")
            .should("contain", "Enter a new password")
            .and("not.contain", "Password Change Successful");
    });

    describe("an account that already holds a blank password", () => {
        beforeEach(() => {
            cy.dbQuery("UPDATE user_usr SET usr_Password = ?, usr_FailedLogins = 0 WHERE usr_per_ID = ?", [
                EMPTY_PASSWORD_HASH,
                USER_ID,
            ]);
            cy.clearCookies();
        });

        it("cannot sign in to the browser with a blank password", () => {
            cy.request({
                method: "POST",
                url: "/admin/",
                form: true,
                body: { User: USER_NAME, Password: "" },
                followRedirect: false,
            }).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body).to.contain("Invalid login or password");
            });
        });

        it("cannot get an API key with a blank or missing password", () => {
            [{ userName: USER_NAME, password: "" }, { userName: USER_NAME }].forEach((body) => {
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body,
                    failOnStatusCode: false,
                }).then((resp) => {
                    expect(resp.status).to.eq(401);
                    expect(resp.body).not.to.have.property("apiKey");
                });
            });
        });
    });

    WHITESPACE_PASSWORDS.forEach(({ label, password, hash }) => {
        describe(`an account that already holds a password of ${label}`, () => {
            beforeEach(() => {
                cy.dbQuery("UPDATE user_usr SET usr_Password = ?, usr_FailedLogins = 0 WHERE usr_per_ID = ?", [hash, USER_ID]);
                cy.clearCookies();
            });

            it("cannot sign in with it", () => {
                cy.request({
                    method: "POST",
                    url: "/admin/",
                    form: true,
                    body: { User: USER_NAME, Password: password },
                    followRedirect: false,
                })
                    .its("status")
                    .should("eq", 200);
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: { userName: USER_NAME, password },
                    failOnStatusCode: false,
                }).then((resp) => {
                    expect(resp.status).to.eq(401);
                    expect(resp.body).not.to.have.property("apiKey");
                });
            });
        });
    });
});
