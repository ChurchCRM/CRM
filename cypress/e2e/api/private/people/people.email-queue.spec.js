/// <reference types="cypress" />

import { absoluteUrl } from "../../../../support/urls";

/**
 * Queued composer email (#10356): POST /api/email/send only queues; the batch is sent by
 * POST /api/email/batches/{batch}/send (the composer) and by the timer jobs, and an
 * administrator can delete what is still queued (DELETE /admin/api/email/queue).
 *
 * Failures come from Mailpit's chaos triggers (docker-compose sets MP_ENABLE_CHAOS), so the
 * stack's mail server must be recreated after that change.
 */

const CHAOS_OFF = {
    Sender: { ErrorCode: 451, Probability: 0 },
    Recipient: { ErrorCode: 451, Probability: 0 },
    Authentication: { ErrorCode: 535, Probability: 0 },
};

const setChaos = (triggers = {}) =>
    cy
        .then(() => cy.request({ method: "PUT", url: `${Cypress.mailpitUrl()}/api/v1/chaos`, body: { ...CHAOS_OFF, ...triggers } }))
        .its("status")
        .should("eq", 200);

const mailpitMessages = (tag) =>
    cy
        .request(`${Cypress.mailpitUrl()}/api/v1/search?query=${encodeURIComponent(`subject:${tag}`)}&limit=500`)
        .then((resp) => resp.body.messages);

const queue = (personIds, subject, keyName = "admin.api.key") =>
    cy
        .readEnv(keyName)
        .then((key) => cy.makePrivateAPICall(key, "POST", "/api/email/send", { personIds, subject, body: "Queued message" }))
        .then((resp) => {
            expect(resp.body.batch).to.match(/^[0-9a-f]{32}$/);
            return resp.body;
        });

const batchUrl = (batch) => `/api/email/batches/${batch}`;

const historyRows = (subject) =>
    cy
        .makePrivateAdminAPICall("GET", "/api/email/log?limit=100")
        .then((resp) => resp.body.rows.filter((r) => r.subject === subject));

/** Lifts the page-load rate limit, then runs the timer jobs the way the page footer does. */
const runTimerJobs = () => {
    cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/sLastTimerJobsRunDateTime", { value: "" });
    return cy.makePrivateAdminAPICall("POST", "/api/background/timerjobs", {}).its("body.ran").should("eq", true);
};

/** Several requests at once, outside Cypress's one-at-a-time command queue. */
const concurrently = (requests) =>
    cy.then({ timeout: 60000 }, () =>
        Promise.all(
            requests.map(({ method, path, body }) =>
                fetch(absoluteUrl(path), {
                    method,
                    credentials: "omit",
                    headers: { "x-api-key": Cypress.testEnv("admin.api.key"), "content-type": "application/json" },
                    body: body === undefined ? undefined : JSON.stringify(body),
                }).then((r) => r.status),
            ),
        ),
    );

before(() => {
    cy.rememberTestEnv(["admin.api.key"]);
    setChaos();
});

after(() => {
    setChaos();
});

describe("Composer email is queued, then sent outside the request", () => {
    it("queues one row per recipient and sends nothing inside the request", () => {
        const subject = `crm-10356-queued-${Date.now()}`;
        queue([2, 3], subject).then((sent) => {
            expect(sent.counts).to.deep.equal({ queued: 2, sent: 0, skipped: 0, failed: 0 });
            cy.makePrivateAdminAPICall("GET", batchUrl(sent.batch)).then((resp) => {
                expect(resp.body).to.include({ total: 2, queued: 2, due: 2, sent: 0, done: false });
            });
            historyRows(subject).then((rows) => {
                expect(rows).to.have.length(2);
                for (const row of rows) {
                    expect(row).to.include({ status: "queued", statusLabel: "Queued", attempts: 0, kind: "composer" });
                }
            });
            mailpitMessages(subject).should("have.length", 0);
            cy.makePrivateAdminAPICall("POST", `${batchUrl(sent.batch)}/send`);
        });
    });

    it("the timer job sends what is queued and the history moves to sent", () => {
        const subject = `crm-10356-timer-${Date.now()}`;
        queue([2, 3], subject).then((sent) => {
            runTimerJobs();
            cy.makePrivateAdminAPICall("GET", batchUrl(sent.batch)).then((resp) => {
                expect(resp.body).to.include({ total: 2, sent: 2, queued: 0, done: true });
            });
        });
        historyRows(subject).then((rows) => {
            expect(rows.map((r) => [r.status, r.attempts])).to.deep.equal([
                ["sent", 1],
                ["sent", 1],
            ]);
        });
        mailpitMessages(subject).then((messages) => {
            expect(messages.map((m) => m.To[0].Address).sort()).to.deep.equal([
                "mathew.campbell@example.com",
                "tony.wade@example.com",
            ]);
        });
    });

    it("sends each message once when the composer and the timer job drain at the same time", () => {
        const subject = `crm-10356-race-${Date.now()}`;
        const personIds = Array.from({ length: 42 }, (_, i) => i + 2).filter((id) => id !== 19 && id !== 35);
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/sLastTimerJobsRunDateTime", { value: "" });
        queue(personIds, subject).then((sent) => {
            const total = sent.counts.queued;
            expect(total).to.be.greaterThan(20);
            concurrently([
                { method: "POST", path: `${batchUrl(sent.batch)}/send` },
                { method: "POST", path: `${batchUrl(sent.batch)}/send` },
                { method: "POST", path: "/api/background/timerjobs", body: {} },
            ]).should("deep.equal", [200, 200, 200]);

            cy.makePrivateAdminAPICall("GET", batchUrl(sent.batch)).then((resp) => {
                expect(resp.body).to.include({ total, sent: total, failed: 0, done: true });
            });
            historyRows(subject).then((rows) => {
                expect(rows).to.have.length(total);
                expect(rows.every((r) => r.status === "sent" && r.attempts === 1)).to.eq(true);
            });
            mailpitMessages(subject).then((messages) => {
                const addresses = messages.map((m) => m.To[0].Address);
                expect(addresses).to.have.length(total);
                expect(new Set(addresses).size).to.eq(total);
            });
        });
    });
});

describe("Failed sends", () => {
    afterEach(() => {
        setChaos();
    });

    it("puts a message back in the queue when the server answers 4xx, and does not retry it at once", () => {
        const subject = `crm-10356-retry-${Date.now()}`;
        setChaos({ Sender: { ErrorCode: 451, Probability: 100 } });
        queue([2], subject).then((sent) => {
            cy.makePrivateAdminAPICall("POST", `${batchUrl(sent.batch)}/send`).then((resp) => {
                expect(resp.body).to.include({ queued: 1, due: 0, sent: 0, failed: 0, done: false });
                expect(resp.body.nextAttempt).to.be.a("string");
            });
            cy.makePrivateAdminAPICall("POST", `${batchUrl(sent.batch)}/send`);
        });
        historyRows(subject).then(([row]) => {
            expect(row).to.include({ status: "queued", attempts: 1 });
            expect(row.error).to.include("451");
        });
    });

    it("fails a message at once when the server refuses the recipient", () => {
        const subject = `crm-10356-refused-${Date.now()}`;
        setChaos({ Recipient: { ErrorCode: 550, Probability: 100 } });
        queue([3], subject).then((sent) => {
            cy.makePrivateAdminAPICall("POST", `${batchUrl(sent.batch)}/send`).then((resp) => {
                expect(resp.body).to.include({ failed: 1, queued: 0, sent: 0, done: true });
                expect(resp.body.failures[0].address).to.eq("tony.wade@example.com");
                expect(resp.body.failures[0].error).to.include("recipients failed");
            });
        });
        historyRows(subject).then(([row]) => {
            expect(row).to.include({ status: "failed", statusLabel: "Failed", attempts: 1 });
        });
        mailpitMessages(subject).should("have.length", 0);
    });
});

describe("Delete Queue", () => {
    afterEach(() => {
        setChaos();
    });

    it("lets an administrator delete queued messages and nothing else", () => {
        const subject = `crm-10356-delete-${Date.now()}`;
        queue([2], `${subject}-sent`).then((sent) =>
            cy.makePrivateAdminAPICall("POST", `${batchUrl(sent.batch)}/send`).its("body.sent").should("eq", 1),
        );
        setChaos({ Recipient: { ErrorCode: 550, Probability: 100 } });
        queue([3], `${subject}-failed`).then((sent) =>
            cy.makePrivateAdminAPICall("POST", `${batchUrl(sent.batch)}/send`).its("body.failed").should("eq", 1),
        );
        setChaos();

        queue([2, 3], `${subject}-queued`).then((queued) => {
            cy.makePrivateUserAPICall("DELETE", "/admin/api/email/queue", null, 403);
            historyRows(`${subject}-queued`).should("have.length", 2);

            cy.makePrivateAdminAPICall("GET", "/api/email/log?status=queued&limit=1").then((before) => {
                cy.makePrivateAdminAPICall("DELETE", "/admin/api/email/queue")
                    .its("body.deleted")
                    .should("eq", before.body.total);
            });
            cy.makePrivateAdminAPICall("GET", "/api/email/log?status=queued&limit=1").its("body.total").should("eq", 0);

            // The composer's next step learns the batch is gone.
            cy.makePrivateAdminAPICall("GET", batchUrl(queued.batch), null, 404);
            cy.makePrivateAdminAPICall("POST", `${batchUrl(queued.batch)}/send`, null, 404)
                .its("body.message")
                .should("include", "deleted the queue");
        });

        historyRows(`${subject}-queued`).should("have.length", 0);
        historyRows(`${subject}-sent`).then(([row]) => expect(row.status).to.eq("sent"));
        historyRows(`${subject}-failed`).then(([row]) => expect(row.status).to.eq("failed"));
        mailpitMessages(`${subject}-queued`).should("have.length", 0);
    });

    it("answers 401 without credentials", () => {
        cy.request({ method: "DELETE", url: "/admin/api/email/queue", failOnStatusCode: false })
            .its("status")
            .should("eq", 401);
    });
});

describe("Batch access", () => {
    it("lets the sender and administrators follow a batch, and nobody else", () => {
        queue([2], `crm-10356-owner-${Date.now()}`, "user.api.key").then((sent) => {
            cy.makePrivateEditRecordsAPICall("GET", batchUrl(sent.batch), null, 403);
            cy.makePrivateEditRecordsAPICall("POST", `${batchUrl(sent.batch)}/send`, null, 403);
            cy.makePrivateAdminAPICall("GET", batchUrl(sent.batch)).its("body.total").should("eq", 1);
            cy.makePrivateUserAPICall("POST", `${batchUrl(sent.batch)}/send`).its("body.sent").should("eq", 1);
        });
    });

    it("answers 404 for an unknown batch, 403 without the Email permission and 401 without credentials", () => {
        const unknown = "0".repeat(32);
        cy.makePrivateAdminAPICall("GET", batchUrl(unknown), null, 404);
        cy.makePrivateAdminAPICall("POST", `${batchUrl(unknown)}/send`, null, 404);
        cy.makePrivateFinanceOnlyAPICall("GET", batchUrl(unknown), null, 403);
        cy.request({ url: batchUrl(unknown), failOnStatusCode: false }).its("status").should("eq", 401);
    });
});
