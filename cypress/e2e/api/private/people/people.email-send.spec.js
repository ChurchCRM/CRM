/// <reference types="cypress" />

/**
 * POST /api/email/send — server-side composer send (#9876).
 *
 * Contract: recipients are person / family ids, never addresses. The server
 * resolves each id, skips the deceased, do-not-email, address-less and
 * duplicate addresses (reporting each by name and reason), and queues one
 * message per remaining recipient (#10356); the queue is covered by
 * people.email-queue.spec.js.
 *
 * Delivery is verified against the Mailpit sink the test stack ships with.
 *
 * Seed fixtures used (cypress/data/seed.sql):
 *   person 2   Mathew Campbell  mathew.campbell@example.com
 *   person 3   Tony Campbell    tony.wade@example.com
 *   person 35  Shane Stewart    tony.wade@example.com   (same address as 3)
 *   person 19  Laurie Ray       deceased 2019-04-12
 *   person 105 Mary Smith       no email of her own
 *   family 1   Campbell         head of household is person 2
 *   family 2   Hart             head Constance and spouse Marion both have an email
 */
const clearMailpit = () =>
    cy.request({ method: "DELETE", url: `${Cypress.mailpitUrl()}/api/v1/messages`, failOnStatusCode: false });

/** Returns the Mailpit messages whose subject contains `tag`. */
const mailpitMessages = (tag) =>
    cy
        .request({ method: "GET", url: `${Cypress.mailpitUrl()}/api/v1/messages?limit=100` })
        .then((resp) => resp.body.messages.filter((m) => m.Subject.includes(tag)));

const send = (body, expectedStatus = 200) => cy.makePrivateAdminAPICall("POST", "/api/email/send", body, expectedStatus);

describe("API POST /api/email/send", () => {
    describe("resolution and delivery", () => {
        const tag = `crm-9876-${Date.now()}`;

        before(() => {
            clearMailpit();
        });

        it("queues one message per resolvable recipient and reports every skipped one by name", () => {
            cy.sendComposerEmailNow({
                personIds: [2, 105, 19, 3, 35, 2],
                familyIds: [1],
                subject: `Hello <b>there</b> ${tag}`,
                body: "Line one\nLine two <script>alert(1)</script>",
            }).then((result) => {
                expect(result.counts).to.deep.equal({ queued: 2, sent: 0, skipped: 4, failed: 0 });
                expect(result.batch).to.match(/^[0-9a-f]{32}$/);
                expect(result.sent).to.deep.equal([]);
                expect(result.failed).to.deep.equal([]);
                expect(result.progress).to.include({ total: 2, sent: 2, failed: 0, done: true });

                expect(result.queued.map((r) => r.personId)).to.deep.equal([2, 3]);
                expect(result.queued[0]).to.include({ name: "Mathew Campbell", email: "mathew.campbell@example.com" });

                const skipped = Object.fromEntries(
                    result.skipped.map((r) => [r.personId ?? `family-${r.familyId}`, r]),
                );
                expect(skipped[105]).to.include({ name: "Mary Smith", reason: "no-email" });
                expect(skipped[19]).to.include({ name: "Laurie Ray", reason: "deceased" });
                expect(skipped[35]).to.include({ name: "Shane Stewart", reason: "duplicate-address" });
                expect(skipped["family-1"]).to.include({ name: "Campbell Family", reason: "duplicate-address" });
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
                cy.request(`${Cypress.mailpitUrl()}/api/v1/message/${mathew.ID}`).then((full) => {
                    // The author writes greeting and closing: nothing is generated around the body
                    // except the church header, and there is no marketing footer.
                    expect(full.body.HTML).to.not.include("Dear ");
                    expect(full.body.HTML).to.not.include("Sincerely");
                    expect(full.body.HTML).to.not.include("You received this email");
                    expect(full.body.HTML).to.include("Line one<br");
                    expect(full.body.HTML).to.not.include("<script");
                    // Footer = the Church Information display preview: name, address, phone, email.
                    // Read the live values: other specs in the same run change the church name.
                    cy.makePrivateAdminAPICall("GET", "/admin/api/system/config/sChurchName").then((name) => {
                        expect(full.body.HTML).to.include(`<strong>${name.body.value}</strong>`);
                    });
                    cy.makePrivateAdminAPICall("GET", "/admin/api/system/config/sChurchEmail").then((email) => {
                        expect(full.body.HTML).to.include(`mailto:${email.body.value}`);
                    });
                });
            });
        });
    });

    describe("family recipients", () => {
        const tag = `crm-msi11-${Date.now()}`;
        const registered = [];
        let savedSelfReg;
        let spouseFamilyId;
        let noAdultFamilyId;

        const register = (name, people) =>
            cy
                .request({
                    method: "POST",
                    url: "/api/public/register/family",
                    body: { Name: name, Address1: "1 Test St", City: "Testville", State: "TS", Country: "US", Zip: "00000", people },
                })
                .then((resp) => {
                    registered.push(resp.body.Id);
                    return resp.body.Id;
                });

        before(() => {
            clearMailpit();
            cy.getSystemConfig("bEnableSelfRegistration").then((value) => {
                savedSelfReg = value;
            });
            cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bEnableSelfRegistration", { value: "1" });
            register("SpouseMail", [
                { firstName: "Hank", lastName: "SpouseMail", gender: 1, role: 1, email: "" },
                { firstName: "Sara", lastName: "SpouseMail", gender: 2, role: 2, email: `sara.${tag}@example.com` },
            ]).then((id) => {
                spouseFamilyId = id;
            });
            register("NoAdultMail", [
                { firstName: "Nora", lastName: "NoAdultMail", gender: 2, role: 1, email: "" },
                { firstName: "Kid", lastName: "NoAdultMail", gender: 1, role: 3, email: `kid.${tag}@example.com` },
            ]).then((id) => {
                noAdultFamilyId = id;
            });
        });

        after(() => {
            cy.cleanupFamilies(registered);
            cy.restoreSystemConfig("bEnableSelfRegistration", savedSelfReg);
        });

        it("mails the head of household, else the spouse, and says why when neither has an email", () => {
            cy.sendComposerEmailNow({ familyIds: [2, spouseFamilyId, noAdultFamilyId], subject: `Family ${tag}`, body: "Hello" }).then((result) => {
                expect(result.counts).to.deep.equal({ queued: 2, sent: 0, skipped: 1, failed: 0 });
                expect(result.progress).to.include({ total: 2, sent: 2, failed: 0, done: true });
                expect(result.queued.map((r) => [r.familyId, r.email])).to.deep.equal([
                    [2, "constance.hart@example.com"],
                    [spouseFamilyId, `sara.${tag}@example.com`],
                ]);
                expect(result.skipped[0]).to.include({
                    familyId: noAdultFamilyId,
                    name: "NoAdultMail Family",
                    reason: "no-adult-email",
                });
            });
            mailpitMessages(tag).then((messages) => {
                expect(messages.map((m) => m.To[0].Address).sort()).to.deep.equal(
                    ["constance.hart@example.com", `sara.${tag}@example.com`].sort(),
                );
            });
        });
    });

    describe("preview", () => {
        it("renders the message for the first resolvable recipient without sending it", () => {
            cy.makePrivateAdminAPICall("POST", "/api/email/preview", {
                personIds: [105, 2, 3],
                subject: "Preview <b>only</b>",
                body: "Hello Mathew,\n\nSee you Sunday.\n\nSincerely,\nThe Office",
            }).then((resp) => {
                expect(resp.body.recipient).to.deep.equal({ name: "Mathew Campbell", email: "mathew.campbell@example.com" });
                expect(resp.body.recipientCount).to.eq(2);
                expect(resp.body.html).to.include("Hello Mathew,<br");
                expect(resp.body.html).to.include("The Office");
                expect(resp.body.html).to.not.include("Dear ");
                expect(resp.body.html).to.not.include("You received this email");
            });
            cy.makePrivateAdminAPICall("POST", "/api/email/preview", { personIds: [105], subject: "s", body: "b" }, 400);
            cy.makePrivateAdminAPICall("POST", "/api/email/preview", { personIds: [2], subject: "", body: "b" }, 400);
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
                expect(resp.body.counts).to.deep.equal({ queued: 0, sent: 0, skipped: 1, failed: 0 });
                expect(resp.body.batch).to.be.null;
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
