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
    // Seed: add person 2 (Mathew Campbell) to the cart so we get a non-empty result.
    before(() => {
        cy.makePrivateAdminAPICall("POST", "/api/cart/add/2", "", 200);
    });

    after(() => {
        cy.makePrivateAdminAPICall("DELETE", "/api/cart/remove/2", "", 200);
    });

    context("authenticated admin — non-empty cart", () => {
        beforeEach(() => {
            cy.makePrivateAdminAPICall("GET", "/api/cart/emails", "", 200).as("resp");
        });

        it("returns 200 with emails array", () => {
            cy.get("@resp").then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property("emails").that.is.an("array");
            });
        });

        it("emails array is non-empty when cart has people with emails", () => {
            cy.get("@resp").then((response) => {
                // Person 2 (Mathew Campbell) has an email in seed data
                expect(response.body.emails.length).to.be.at.least(1);
            });
        });

        it("every email is a non-empty string", () => {
            cy.get("@resp").then((response) => {
                response.body.emails.forEach((email) => {
                    expect(email).to.be.a("string").and.not.be.empty;
                });
            });
        });

        it("no duplicate emails in the list (case-insensitive)", () => {
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

    context("authenticated admin — empty cart", () => {
        before(() => {
            // Empty the cart so we get the zero-email path
            cy.makePrivateAdminAPICall("DELETE", "/api/cart/remove/2", "", 200);
        });

        after(() => {
            // Restore for the after() in the outer context
            cy.makePrivateAdminAPICall("POST", "/api/cart/add/2", "", 200);
        });

        it("returns 200 with empty emails array when cart is empty", () => {
            cy.makePrivateAdminAPICall("GET", "/api/cart/emails", "", 200).then((response) => {
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
    // Group 10 "Worship Service" is seeded and has members with emails.
    const GROUP_WITH_MEMBERS = 10;
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
