/// <reference types="cypress" />

/**
 * POST /api/email/send — server-side composer send (#9876).
 *
 * Contract: recipients are person / family ids, never addresses. The server
 * resolves each id, skips the deceased, do-not-email, address-less and
 * duplicate addresses (reporting each by name and reason), and sends one
 * message per remaining recipient through the configured SMTP server.
 *
 * Delivery is verified against the Mailpit sink the test stack ships with.
 * Override its URL with `--env mailpitUrl=http://localhost:8070` when the
 * stack runs on non-default ports.
 *
 * Seed fixtures used (cypress/data/seed.sql):
 *   person 2   Mathew Campbell  mathew.campbell@example.com
 *   person 3   Tony Campbell    tony.wade@example.com
 *   person 35  Shane Stewart    tony.wade@example.com   (same address as 3)
 *   person 19  Laurie Ray       deceased 2019-04-12
 *   person 105 Mary Smith       no email, family 21 has no email
 *   family 1   Campbell         no family email
 */
const mailpit = () => Cypress.env("mailpitUrl") || "http://localhost:8025";

const clearMailpit = () =>
    cy.request({ method: "DELETE", url: `${mailpit()}/api/v1/messages`, failOnStatusCode: false });

/** Returns the Mailpit messages whose subject contains `tag`. */
const mailpitMessages = (tag) =>
    cy
        .request({ method: "GET", url: `${mailpit()}/api/v1/messages?limit=100` })
        .then((resp) => resp.body.messages.filter((m) => m.Subject.includes(tag)));

const send = (body, expectedStatus = 200) => cy.makePrivateAdminAPICall("POST", "/api/email/send", body, expectedStatus);

describe("API POST /api/email/send", () => {
    describe("resolution and delivery", () => {
        const tag = `crm-9876-${Date.now()}`;

        before(() => {
            clearMailpit();
        });

        it("sends one message per resolvable recipient and reports every skipped one by name", () => {
            send({
                personIds: [2, 105, 19, 3, 35, 2],
                familyIds: [1],
                subject: `Hello <b>there</b> ${tag}`,
                body: "Line one\nLine two <script>alert(1)</script>",
            }).then((resp) => {
                expect(resp.body.counts).to.deep.equal({ sent: 2, skipped: 4, failed: 0 });
                expect(resp.body.failed).to.deep.equal([]);

                expect(resp.body.sent.map((r) => r.personId)).to.deep.equal([2, 3]);
                expect(resp.body.sent[0]).to.include({ name: "Mathew Campbell", email: "mathew.campbell@example.com" });

                const skipped = Object.fromEntries(
                    resp.body.skipped.map((r) => [r.personId ?? `family-${r.familyId}`, r]),
                );
                expect(skipped[105]).to.include({ name: "Mary Smith", reason: "no-email" });
                expect(skipped[19]).to.include({ name: "Laurie Ray", reason: "deceased" });
                expect(skipped[35]).to.include({ name: "Shane Stewart", reason: "duplicate-address" });
                expect(skipped["family-1"]).to.include({ name: "Campbell Family", reason: "no-email" });
            });
        });

        it("delivers a separate, personalised copy to each recipient with tags stripped", () => {
            mailpitMessages(tag).then((messages) => {
                expect(messages).to.have.length(2);
                for (const m of messages) {
                    expect(m.To).to.have.length(1);
                    expect(m.Subject).to.equal(`Hello there ${tag}`);
                }
                const to = messages.map((m) => m.To[0].Address).sort();
                expect(to).to.deep.equal(["mathew.campbell@example.com", "tony.wade@example.com"]);

                const mathew = messages.find((m) => m.To[0].Address === "mathew.campbell@example.com");
                expect(mathew.To[0].Name).to.equal("Mathew Campbell");
                cy.request(`${mailpit()}/api/v1/message/${mathew.ID}`).then((full) => {
                    expect(full.body.HTML).to.include("Mathew,");
                    expect(full.body.HTML).to.include("Line one<br");
                    expect(full.body.HTML).to.not.include("<script");
                });
            });
        });
    });

    describe("validation", () => {
        it("refuses raw email addresses", () => {
            send({ recipients: ["someone@example.com"], subject: "s", body: "b" }, 400).then((resp) => {
                expect(resp.body.message).to.include("personIds and familyIds");
            });
        });

        it("refuses non-integer ids", () => {
            send({ personIds: [2, "abc"], subject: "s", body: "b" }, 400);
            send({ personIds: [0], subject: "s", body: "b" }, 400);
            send({ familyIds: "1", subject: "s", body: "b" }, 400);
        });

        it("requires at least one id, a subject and a body", () => {
            send({ personIds: [], familyIds: [], subject: "s", body: "b" }, 400);
            send({ personIds: [2], subject: "   ", body: "b" }, 400);
            send({ personIds: [2], subject: "s", body: "<b></b>" }, 400);
        });

        it("caps a request at 500 recipients", () => {
            const personIds = Array.from({ length: 501 }, (_, i) => i + 1);
            send({ personIds, subject: "s", body: "b" }, 400);
        });

        it("reports unknown ids as skipped rather than failing the request", () => {
            send({ personIds: [999999], subject: "s", body: "b" }).then((resp) => {
                expect(resp.body.counts).to.deep.equal({ sent: 0, skipped: 1, failed: 0 });
                expect(resp.body.skipped[0]).to.include({ personId: 999999, reason: "not-found" });
            });
        });
    });

    describe("permissions", () => {
        it("returns 403 for a user without the Email permission", () => {
            cy.makePrivateFinanceOnlyAPICall("POST", "/api/email/send", { personIds: [2], subject: "s", body: "b" }, 403);
        });

        it("returns 401 without credentials", () => {
            cy.request({
                method: "POST",
                url: "/api/email/send",
                body: { personIds: [2], subject: "s", body: "b" },
                failOnStatusCode: false,
            })
                .its("status")
                .should("eq", 401);
        });
    });
});

describe("API recipient lists carry person ids for the composer", () => {
    const expectRecipients = (body) => {
        expect(body.recipients).to.be.an("array").with.length(body.emails.length);
        expect(body.recipients.map((r) => r.email)).to.deep.equal(body.emails);
        for (const r of body.recipients) {
            expect(r.personId).to.be.a("number").greaterThan(0);
            expect(r.familyId).to.be.null;
            expect(r.name).to.be.a("string").and.not.be.empty;
        }
    };

    it("GET /api/people/emails", () => {
        cy.makePrivateAdminAPICall("GET", "/api/people/emails").then((resp) => expectRecipients(resp.body));
    });

    it("GET /api/groups/{id}/emails", () => {
        cy.makePrivateAdminAPICall("GET", "/api/groups/1/emails").then((resp) => {
            expect(resp.body.emails.length).to.be.greaterThan(0);
            expectRecipients(resp.body);
        });
    });

    it("GET /api/cart/emails", () => {
        cy.makePrivateAdminAPICall("DELETE", "/api/cart/", {});
        cy.makePrivateAdminAPICall("POST", "/api/cart/", { Persons: [2, 3, 35] });
        cy.makePrivateAdminAPICall("GET", "/api/cart/emails").then((resp) => {
            // 3 and 35 share an address, so two recipients
            expect(resp.body.emails).to.have.length(2);
            expectRecipients(resp.body);
            expect(resp.body.recipients[0]).to.include({ personId: 2, name: "Mathew Campbell" });
        });
        cy.makePrivateAdminAPICall("DELETE", "/api/cart/", {});
    });
});
