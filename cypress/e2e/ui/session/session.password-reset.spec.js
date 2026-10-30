/// <reference types="cypress" />

import { resetUserFixture, resetUserState, resetTokenState, seedResetToken, blockedResetUser, cleanupResetFixture, resetCsrfFixture } from "../../../support/password-reset-fixtures";
import { absoluteUrl } from "../../../support/urls";

describe("Session Password Reset", () => {
    describe("Password Reset Form", () => {
        beforeEach(() => {
            cy.visit("/session/forgot-password/reset-request");
        });

        it("Should display password reset form with correct elements", () => {
            cy.contains("Reset your password").should("be.visible");
            cy.contains("Enter your login name and we will email you a link to reset your password.").should("be.visible");
            cy.get("input[name='username']").should("exist").should("be.visible");
            cy.get("#resetPassword").should("exist").should("be.visible").should("be.enabled").should("contain", "Send Reset Email");
            cy.contains("Back to login").should("exist").should("be.visible");
        });

        it("Should show validation error when username is empty", () => {
            cy.get("#resetPassword").click();
            cy.contains("Login Name is Required").should("be.visible");
            cy.get("#resetPassword").should("not.be.disabled");
        });

        it("Should submit form on Enter key press", () => {
            cy.get("input[name='username']").type("admin{enter}");
            cy.get("#resetPassword").should("contain", "Sending...");
        });

        it("Should show loading state while submitting", () => {
            cy.get("input[name='username']").type("admin");
            cy.get("#resetPassword").click();
            cy.get("#resetPassword").should("be.disabled");
            cy.get("#resetPassword").should("contain", "Sending...");
        });

        it("Should show success message and redirect on valid user", () => {
            cy.get("input[name='username']").type("admin");
            cy.get("#resetPassword").click();
            
            // Wait for success notification
            cy.contains("Check your email for a password reset link").should("be.visible");
            
            // Should redirect to login after a delay
            cy.url({ timeout: 5000 }).should("not.contain", "/session/forgot-password/reset-request");
        });

        it("Should show error message and re-enable button on API error", () => {
            // Intercept the API call and force an error
            cy.intercept("POST", "**/api/public/user/password-reset", {
                statusCode: 500,
                body: { error: "Server error" }
            }).as("failedReset");
            
            cy.get("input[name='username']").type("admin");
            cy.get("#resetPassword").click();
            
            cy.wait("@failedReset");
            cy.contains("Sorry, we are unable to process your request at this point in time.").should("be.visible");
            cy.get("#resetPassword").should("not.be.disabled").should("contain", "Send Reset Email");
        });

        it("Should handle case-insensitive username", () => {
            cy.get("input[name='username']").type("ADMIN");
            cy.get("#resetPassword").click();
            
            cy.contains("Check your email for a password reset link", { timeout: 5000 }).should("be.visible");
        });

        it("Should trim whitespace from username", () => {
            cy.get("input[name='username']").type("  admin  ");
            cy.get("#resetPassword").click();
            
            cy.contains("Check your email for a password reset link", { timeout: 5000 }).should("be.visible");
        });

        it("Should show error for non-existent user (but success message for security)", () => {
            cy.get("input[name='username']").type("nonexistentuser123");
            cy.get("#resetPassword").click();
            
            // API returns success: true for security reasons, so user sees success message
            cy.contains("Check your email for a password reset link", { timeout: 5000 }).should("be.visible");
        });

        it("Should allow multiple reset attempts after error", () => {
            // First attempt with non-existent user
            cy.get("input[name='username']").type("nonexistentuser123");
            cy.get("#resetPassword").click();
            cy.contains("Check your email for a password reset link").should("be.visible");
            
            // Go back to login and return to reset page
            cy.visit("/session/forgot-password/reset-request");
            
            // Second attempt with valid user
            cy.get("input[name='username']").type("admin");
            cy.get("#resetPassword").click();
            cy.contains("Check your email for a password reset link").should("be.visible");
        });
    });

    describe("Password Reset Token Validation", () => {
        it("Should display error page with proper Tabler UX structure", () => {
            cy.visit("/session/forgot-password/set/invalid-token-12345");

            cy.get(".forgot-password-card").should("be.visible");
            cy.get(".forgot-password-card-logo img").should("be.visible");
            cy.get(".forgot-password-card h2").should("be.visible");
            cy.contains("Account Recovery").should("be.visible");

            cy.get(".forgot-password-card h3").should("contain", "Password Reset Error");
            cy.get(".forgot-password-card h3 i.fa-circle-exclamation").should("exist");
            cy.contains("We were unable to process your password reset request").should("be.visible");
        });

        it("Should display error alert with helpful message", () => {
            cy.visit("/session/forgot-password/set/invalid-token-12345");

            cy.get(".alert.alert-danger").should("be.visible");
            cy.contains("Please try requesting a new password reset link or contact support").should("be.visible");
        });

        it("Should display action buttons below alert", () => {
            cy.visit("/session/forgot-password/set/invalid-token-12345");

            cy.get(".alert-buttons").should("be.visible");
            cy.get(".alert-buttons").contains("Request Password Reset");
            cy.get(".alert-buttons").contains("Back to Login");
        });

        it("Should show error page when token does not exist", () => {
            cy.visit("/session/forgot-password/set/nonexistent-token-xyz");

            cy.contains("Password Reset Error").should("be.visible");
        });

        it("Should navigate to password reset form when clicking Request Password Reset", () => {
            cy.visit("/session/forgot-password/set/invalid-token");

            cy.get(".alert-buttons").contains("Request Password Reset").click();
            cy.url().should("include", "/session/forgot-password/reset-request");
        });

        it("Should navigate back to login when clicking Back to Login button", () => {
            cy.visit("/session/forgot-password/set/bad-token-123");

            cy.get(".alert-buttons").contains("Back to Login").click();
            cy.location("pathname").should("eq", "/admin/");
        });
    });

    describe("Choose a password with a single-use reset link", () => {
        let user;
        let token;
        let storageKey;
        let beforeUser;
        let csrfToken;
        const password = "ChurchReset!10201Strong";
        const state = () => resetUserState(user);
        const tokenState = () => cy.then(() => resetTokenState(storageKey));
        const url = () => `/session/forgot-password/set/${token}`;
        const post = (body) => cy.request({ method: "POST", url: url(), form: true, body: { csrf_token: csrfToken, ...body } });
        const csrf = () => cy.get('input[name="csrf_token"]').invoke("val");

        beforeEach(() => {
            cy.clearCookies();
            resetUserFixture().then((row) => {
                user = row;
                beforeUser = [row];
                return resetCsrfFixture(row).then((value) => { csrfToken = value; });
            });
            token = crypto.randomUUID().replaceAll("-", "") + crypto.randomUUID().replaceAll("-", "");
            storageKey = token;
        });

        afterEach(() => {
            cleanupResetFixture(storageKey, user);
            cy.clearCookies();
        });

        function seedToken(type = "password", expired = false, remaining = 1) {
            return seedResetToken(storageKey, user.usr_per_ID, type, expired, remaining);
        }

        for (const format of ["legacy plaintext", "SHA-256"]) {
            it(`GET preserves password and token; POST works once and rejects reuse (${format})`, () => {
                cy.then(async () => {
                    if (format === "SHA-256") {
                        const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(token));
                        storageKey = "sha256:" + Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, "0")).join("");
                    }
                });
                cy.then(() => seedToken());
                tokenState().then((initialToken) => {
                    cy.request(url()).its("body").should("include", 'id="setPasswordForm"');
                    cy.visit(url());
                    state().should("deep.equal", beforeUser);
                    tokenState().should("deep.equal", initialToken);
                });
                if (format === "SHA-256") {
                    for (const theme of ["light", "dark"]) {
                        for (const width of [1280, 820, 390]) {
                            cy.viewport(width, 900);
                            cy.document().then((document) => document.documentElement.setAttribute("data-bs-theme", theme));
                            cy.get("#setPasswordForm").should("be.visible");
                            cy.document().then((document) => {
                                expect(document.documentElement.scrollWidth).to.be.at.most(width);
                            });
                            cy.screenshot(`password-reset-${theme}-${width}`, { capture: "fullPage" });
                        }
                    }
                    cy.viewport(1920, 1080);
                    cy.document().then((document) => document.documentElement.setAttribute("data-bs-theme", "light"));
                }
                cy.get("#password").type(password, { log: false });
                cy.get("#passwordConfirmation").type(password, { log: false });
                csrf().as("csrf");
                cy.intercept("POST", "**/session/forgot-password/set/*").as("confirmReset");
                cy.get("#setPassword").click();
                cy.wait("@confirmReset");
                cy.contains("Password Reset Successful").should("be.visible");
                tokenState().its("0.remainingUses").should("equal", 0);
                state().then((rows) => {
                    expect(rows[0].usr_Password).not.to.equal(user.usr_Password);
                    expect(rows[0].usr_apiKey).not.to.equal(user.usr_apiKey);
                    expect(rows[0].usr_NeedPasswordChange).to.equal(0);
                    cy.get("@csrf").then((csrf_token) => post({ csrf_token, password: "AnotherPassword!10201", passwordConfirmation: "AnotherPassword!10201" }))
                        .its("body").should("include", "Password Reset Error");
                    state().should("deep.equal", rows);
                });
                cy.request(url()).its("body").should("include", "Password Reset Error");
                cy.request({ method: "POST", url: "/admin/", form: true,
                    body: { User: user.usr_UserName, Password: password }, followRedirect: false }).then((response) => {
                    expect(response.status).to.equal(302);
                    expect(response.headers.location).to.include("v2/dashboard");
                });
            });
        }

        it("emails a usable link, stores only its digest, and never emails the chosen password", () => {
            let previousIds;
            let mailIds;
            cy.task("mail:list").then((result) => {
                expect(result.ok).to.equal(true);
                previousIds = result.body.messages.map((message) => message.ID);
            });
            cy.request("POST", "/api/public/user/password-reset", { userName: "tony.wade@example.com" });
            cy.task("mail:list").then((result) => {
                expect(result.ok).to.equal(true);
                const messages = result.body.messages.filter((message) => !previousIds.includes(message.ID));
                expect(messages).to.have.length(1);
                expect(messages[0].To.some((to) => to.Address === "tony.wade@example.com")).to.equal(true);
                mailIds = result.body.messages.map((message) => message.ID);
                return cy.task("mail:get", { id: messages[0].ID });
            }).then(async (result) => {
                expect(result.ok).to.equal(true);
                const match = result.body.HTML.match(/forgot-password\/set\/([a-f0-9]{64})/);
                expect(match).not.to.equal(null);
                token = match[1];
                const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(token));
                storageKey = "sha256:" + Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, "0")).join("");
            });
            tokenState().should("have.length", 1);
            cy.then(() => resetTokenState(token)).should("have.length", 0);
            for (const prefix of ["sha256:", "SHA256:", "shá256:"]) {
                cy.then(() => cy.request(`/session/forgot-password/set/${encodeURIComponent(prefix + storageKey.slice(7))}`))
                    .its("body").should("include", "Password Reset Error");
            }
            cy.then(() => cy.visit(url()));
            cy.get("#password").type(password, { log: false });
            cy.get("#passwordConfirmation").type(password, { log: false });
            cy.intercept("POST", "**/session/forgot-password/set/*").as("confirmMailedReset");
            cy.get("#setPassword").click();
            cy.wait("@confirmMailedReset");
            cy.contains("Password Reset Successful").should("be.visible");
            cy.task("mail:list").then((result) => {
                expect(result.ok).to.equal(true);
                expect(result.body.messages.map((message) => message.ID)).to.deep.equal(mailIds);
            });
        });

        it("allows only one of two concurrent POSTs from separate sessions", () => {
            cy.then(() => seedToken());
            cy.then(() => cy.task("passwordReset:race", {
                url: absoluteUrl(url()),
                password,
            })).then((bodies) => {
                expect(bodies.filter((body) => body.includes("Password Reset Successful"))).to.have.length(1);
                expect(bodies.filter((body) => body.includes("Password Reset Error"))).to.have.length(1);
            });
            tokenState().its("0.remainingUses").should("equal", 0);
        });

        for (const column of ["per_DateDeceased", "per_DateDeactivated"]) {
            it(`preserves the token for an account that cannot sign in (${column})`, () => {
                blockedResetUser(column).then((userId) => seedResetToken(storageKey, userId));
                cy.request(url()).its("body").should("include", "Password Reset Error");
                post({ password, passwordConfirmation: password }).its("body").should("include", "Password Reset Error");
                tokenState().its("0.remainingUses").should("equal", 1);
            });
        }

        it("rejects a token whose user no longer exists without consuming it", () => {
            cy.then(() => seedResetToken(storageKey, -1));
            cy.request(url()).its("body").should("include", "Password Reset Error");
            post({ password, passwordConfirmation: password }).its("body").should("include", "Password Reset Error");
            tokenState().its("0.remainingUses").should("equal", 1);
        });

        it("rejects missing CSRF and invalid passwords without consuming the link", () => {
            cy.then(() => seedToken());
            cy.visit(url());
            post({ csrf_token: "", password, passwordConfirmation: password }).its("body").should("include", "Password Reset Error");
            csrf().then((csrf_token) => {
                for (const [value, confirmation, message] of [
                    ["short", "short", "Your new password must be at least"],
                    ["password", "password", "Your password choice is too obvious"],
                    [password, "different", "Passwords do not match"],
                    ["", "", "Enter a new password"],
                ]) {
                    post({ csrf_token, password: value, passwordConfirmation: confirmation }).its("body").should("include", message);
                }
            });
            tokenState().its("0.remainingUses").should("equal", 1);
            state().should("deep.equal", beforeUser);
        });

        for (const [name, type, expired, remaining] of [
            ["expired", "password", true, 1],
            ["exhausted", "password", false, 0],
            ["wrong-type", "verifyFamily", false, 1],
        ]) {
            it(`rejects ${name} tokens on both GET and POST without changing them`, () => {
                cy.then(() => seedToken(type, expired, remaining));
                tokenState().then((initialToken) => {
                    cy.request(url()).its("body").should("include", "Password Reset Error");
                    post({ password, passwordConfirmation: password }).its("body").should("include", "Password Reset Error");
                    tokenState().should("deep.equal", initialToken);
                    state().should("deep.equal", beforeUser);
                });
            });
        }
    });
});
