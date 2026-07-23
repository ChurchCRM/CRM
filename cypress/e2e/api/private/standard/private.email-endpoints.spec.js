/// <reference types="cypress" />

/**
 * API specs for email-list endpoints introduced in the in-app email composer PR.
 *
 * Covered endpoints:
 *   GET /api/people/emails        — all active-family people mailing list
 *   GET /api/cart/emails          — people currently in the session cart
 *   GET /api/groups/:id/emails    — members of a specific group
 *
 * All three require Email role permission (bEmailMailto=1). In the seed
 * data all API-key users have email enabled, so we use the admin key for
 * happy-path tests and an unauthenticated request for 401.
 *
 * Canonical response shape: { emails: string[], byRole?: Record<string, string[]> }
 *
 * `emails`/`byRole` contain member recipients only. The church default address
 * (sToEmailAddress) is a system setting the composer adds from the button at render
 * time — it is never returned by these endpoints.
 *
 * NOTE on cart tests: the cart is stored in $_SESSION['aPeopleCart'], which is
 * session-scoped. cy.makePrivateAdminAPICall() sets withCredentials:false (no
 * cookies) so each call starts a fresh PHP session — cart state would be empty.
 * Cart state tests instead use cy.setupAdminSession() to establish a login session
 * and cy.request() (with the session cookie) to seed and read the cart.
 */

// ──────────────────────────────────────────────────────
//  GET /api/people/emails
// ──────────────────────────────────────────────────────
describe("GET /api/people/emails", () => {
    context("authenticated admin", () => {
        beforeEach(() => {
            cy.makePrivateAdminAPICall("GET", "/api/people/emails", "", 200).as("resp");
        });

        it("returns 200 with emails array and byRole object", () => {
            cy.get("@resp").then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property("emails").that.is.an("array");
                expect(response.body).to.have.property("byRole").that.is.an("object");
            });
        });

        it("emails array is non-empty (seed data has people with emails)", () => {
            cy.get("@resp").then((response) => {
                expect(response.body.emails.length).to.be.at.least(1);
            });
        });

        it("every email in the flat list is a non-empty string", () => {
            cy.get("@resp").then((response) => {
                response.body.emails.forEach((email) => {
                    expect(email).to.be.a("string").and.not.be.empty;
                });
            });
        });

        it("byRole values are arrays of strings", () => {
            cy.get("@resp").then((response) => {
                for (const [, roleEmails] of Object.entries(response.body.byRole)) {
                    expect(roleEmails).to.be.an("array");
                    roleEmails.forEach((email) => {
                        expect(email).to.be.a("string").and.not.be.empty;
                    });
                }
            });
        });

        it("no duplicate emails in the flat list (case-insensitive)", () => {
            cy.get("@resp").then((response) => {
                const seen = new Set();
                for (const email of response.body.emails) {
                    const lower = email.toLowerCase();
                    expect(seen.has(lower), `duplicate email: ${email}`).to.be.false;
                    seen.add(lower);
                }
            });
        });
    });

    context("unauthenticated request", () => {
        it("returns 401 without an API key", () => {
            cy.request({
                method: "GET",
                url: "/api/people/emails",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });

    context("user without email permission", () => {
        it("returns 403 for plainauth user (no bEmailMailto row in userconfig_ucfg)", () => {
            // User 900 (john.plainauth) has no bEmailMailto entry in userconfig_ucfg,
            // so isEnabledSecurity('bEmailMailto') returns false → isEmailEnabled() = false.
            cy.makePrivatePlainAuthAPICall("GET", "/api/people/emails", "", 403);
        });
    });
});

// ──────────────────────────────────────────────────────
//  GET /api/cart/emails
// ──────────────────────────────────────────────────────
describe("GET /api/cart/emails", () => {
    // Cart state is session-scoped ($_SESSION['aPeopleCart']).
    // makePrivateAdminAPICall uses withCredentials:false (no cookies) so each
    // call would get a fresh PHP session with an empty cart. Instead, we use
    // cy.setupAdminSession() + cy.request() (which sends the session cookie)
    // for cart-state-dependent tests so state persists across calls.

    before(() => {
        // Establish admin login session so cy.request() sends the session cookie.
        cy.setupAdminSession();
        // Add person 2 (Mathew Campbell) to the admin's cart using the session cookie.
        cy.request({
            method: "POST",
            url: "/api/person/2/addToCart",
        }).then((resp) => expect(resp.status).to.equal(200));
    });

    after(() => {
        // Restore session and clean up the cart
        cy.setupAdminSession();
        cy.request({
            method: "DELETE",
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: { Persons: [2] },
        });
    });

    context("authenticated admin — non-empty cart", () => {
        // Re-establish session before each test so the session cookie is fresh.
        beforeEach(() => {
            cy.setupAdminSession();
        });

        it("returns 200 with emails array", () => {
            cy.request({
                method: "GET",
                url: "/api/cart/emails",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property("emails").that.is.an("array");
            });
        });

        it("emails array is non-empty when cart has people with emails", () => {
            // Person 2 (Mathew Campbell) has an email in seed data
            cy.request({
                method: "GET",
                url: "/api/cart/emails",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.emails.length).to.be.at.least(1);
            });
        });

        it("every email is a non-empty string", () => {
            cy.request({
                method: "GET",
                url: "/api/cart/emails",
                failOnStatusCode: false,
            }).then((response) => {
                response.body.emails.forEach((email) => {
                    expect(email).to.be.a("string").and.not.be.empty;
                });
            });
        });

        it("no duplicate emails in the list (case-insensitive)", () => {
            cy.request({
                method: "GET",
                url: "/api/cart/emails",
                failOnStatusCode: false,
            }).then((response) => {
                const seen = new Set();
                for (const email of response.body.emails) {
                    const lower = email.toLowerCase();
                    expect(seen.has(lower), `duplicate email: ${email}`).to.be.false;
                    seen.add(lower);
                }
            });
        });
    });

    context("authenticated admin — empty cart", () => {
        before(() => {
            // Empty the cart so we get the zero-email path
            cy.setupAdminSession();
            cy.request({
                method: "DELETE",
                url: "/api/cart/",
                headers: { "Content-Type": "application/json" },
                body: { Persons: [2] },
            });
        });

        after(() => {
            // Restore person 2 so the outer after() cleanup can confirm it worked
            cy.setupAdminSession();
            cy.request({
                method: "POST",
                url: "/api/person/2/addToCart",
            });
        });

        it("returns 200 with empty emails array when cart is empty", () => {
            cy.setupAdminSession();
            cy.request({
                method: "GET",
                url: "/api/cart/emails",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property("emails").that.is.an("array");
                expect(response.body.emails).to.deep.equal([]);
            });
        });
    });

    context("unauthenticated request", () => {
        it("returns 401 without an API key", () => {
            cy.request({
                method: "GET",
                url: "/api/cart/emails",
                failOnStatusCode: false,
                withCredentials: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });

    context("user without email permission", () => {
        it("returns 403 for plainauth user", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/cart/emails", "", 403);
        });
    });
});

// ──────────────────────────────────────────────────────
//  GET /api/groups/:id/emails
// ──────────────────────────────────────────────────────
describe("GET /api/groups/:id/emails", () => {
    // Group 11 "Clergy" is seeded and has members with emails (persons 2 and 26).
    const GROUP_WITH_MEMBERS = 11;
    const NONEXISTENT_GROUP = 99999;

    context("authenticated admin — group with members", () => {
        beforeEach(() => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${GROUP_WITH_MEMBERS}/emails`,
                "",
                200,
            ).as("resp");
        });

        it("returns 200 with emails array and byRole object", () => {
            cy.get("@resp").then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property("emails").that.is.an("array");
                expect(response.body).to.have.property("byRole").that.is.an("object");
            });
        });

        it("every email in the flat list is a non-empty string", () => {
            cy.get("@resp").then((response) => {
                response.body.emails.forEach((email) => {
                    expect(email).to.be.a("string").and.not.be.empty;
                });
            });
        });

        it("no duplicate emails in the flat list (case-insensitive)", () => {
            cy.get("@resp").then((response) => {
                const seen = new Set();
                for (const email of response.body.emails) {
                    const lower = email.toLowerCase();
                    expect(seen.has(lower), `duplicate email: ${email}`).to.be.false;
                    seen.add(lower);
                }
            });
        });
    });

    context("non-existent group", () => {
        it("returns 404 for a group that does not exist", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${NONEXISTENT_GROUP}/emails`,
                "",
                404,
            ).then((response) => {
                expect(response.status).to.eq(404);
            });
        });
    });

    context("unauthenticated request", () => {
        it("returns 401 without an API key", () => {
            cy.request({
                method: "GET",
                url: `/api/groups/${GROUP_WITH_MEMBERS}/emails`,
                failOnStatusCode: false,
                withCredentials: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });

    context("user without email permission", () => {
        it("returns 403 for plainauth user", () => {
            cy.makePrivatePlainAuthAPICall("GET", `/api/groups/${GROUP_WITH_MEMBERS}/emails`, "", 403);
        });
    });
});
