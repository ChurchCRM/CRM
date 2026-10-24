/// <reference types="cypress" />

/**
 * The email log table (#10601): GET /api/email/log without personId or familyId pages the
 * whole log for an administrator and only the caller's own mail for a sender without admin
 * rights, filtered by status, type, sender and text, with a link to each recipient's record.
 */

const ADMIN_ID = 1;
const SENDER_ID = 3;
const TURNER_FAMILY_ID = 23;
const tag = `crm-10601-${Date.now()}`;

const CHAOS_OFF = {
    Sender: { ErrorCode: 451, Probability: 0 },
    Recipient: { ErrorCode: 451, Probability: 0 },
    Authentication: { ErrorCode: 535, Probability: 0 },
};

const setChaos = (triggers = {}) =>
    cy
        .request({ method: "PUT", url: `${Cypress.mailpitUrl()}/api/v1/chaos`, body: { ...CHAOS_OFF, ...triggers } })
        .its("status")
        .should("eq", 200);

const callers = {
    admin: (method, url, body, status) => cy.makePrivateAdminAPICall(method, url, body, status),
    sender: (method, url, body, status) => cy.makePrivateUserAPICall(method, url, body, status),
};

const queue = (as, recipients, subject) =>
    callers[as]("POST", "/api/email/send", { ...recipients, subject, body: "Email log table" }, 200).its("body.batch");

const queueAndSend = (as, recipients, subject) =>
    queue(as, recipients, subject).then((batch) => callers[as]("POST", `/api/email/batches/${batch}/send`, null, 200));

const log = (params, { as = "admin", status = 200 } = {}) =>
    callers[as]("GET", `/api/email/log?${new URLSearchParams(params)}`, null, status);

const subjects = (resp) => resp.body.rows.map((r) => r.subject).sort();

before(() => {
    setChaos();
    queue("admin", { personIds: [2] }, `${tag} admin queued`);
    queueAndSend("admin", { familyIds: [TURNER_FAMILY_ID] }, `${tag} admin family`);
    queue("sender", { personIds: [2] }, `${tag} sender queued`);
    setChaos({ Recipient: { ErrorCode: 550, Probability: 100 } });
    queueAndSend("admin", { personIds: [2] }, `${tag} admin failed`);
    queueAndSend("sender", { personIds: [2] }, `${tag} sender failed`);
    setChaos();
});

after(() => {
    setChaos();
    cy.makePrivateAdminAPICall("DELETE", "/admin/api/email/queue", null, 200);
});

describe("GET /api/email/log for the email log table", () => {
    it("pages the whole log newest first for an administrator", () => {
        log({ limit: 2 }).then((first) => {
            expect(first.body).to.include({ page: 1, limit: 2 });
            expect(first.body.rows).to.have.length(2);
            expect(first.body.pages).to.eq(Math.ceil(first.body.total / 2));
            log({ limit: 2, page: 2 }).then((second) => {
                expect(second.body).to.include({ page: 2, total: first.body.total });
                const firstIds = first.body.rows.map((r) => r.id);
                for (const r of second.body.rows) expect(firstIds).to.not.include(r.id);
                const dates = [...first.body.rows, ...second.body.rows].map((r) => r.dateSent);
                expect([...dates].sort().reverse()).to.deep.equal(dates);
            });
        });
    });

    it("filters by each status", () => {
        const ours = {
            queued: [`${tag} admin queued`, `${tag} sender queued`],
            sending: [],
            sent: [`${tag} admin family`],
            failed: [`${tag} admin failed`, `${tag} sender failed`],
            skipped: [],
        };
        cy.wrap(Object.keys(ours)).each((status) => {
            log({ status, limit: 100 }).then((resp) => {
                for (const r of resp.body.rows) expect(r.status).to.eq(status);
            });
            log({ status, q: tag, limit: 100 }).then((resp) => expect(subjects(resp)).to.deep.equal(ours[status]));
        });
        log({ status: "bogus" }, { status: 400 });
    });

    it("filters by type", () => {
        log({ kind: "composer", q: tag, limit: 100 }).its("body.total").should("eq", 5);
        log({ kind: "birthday", q: tag }).its("body.total").should("eq", 0);
        log({ kind: "birthday", limit: 100 }).then((resp) => {
            expect(resp.body.total).to.be.at.least(1);
            for (const r of resp.body.rows) expect(r.kind).to.eq("birthday");
        });
        log({ kind: "x".repeat(51) }, { status: 400 });
    });

    it("filters by who sent it, or by mail ChurchCRM sent on its own", () => {
        log({ sentBy: SENDER_ID, q: tag, limit: 100 }).then((resp) => {
            expect(subjects(resp)).to.deep.equal([`${tag} sender failed`, `${tag} sender queued`]);
        });
        log({ sentBy: ADMIN_ID, q: tag, limit: 100 }).then((resp) => {
            expect(subjects(resp)).to.deep.equal([`${tag} admin failed`, `${tag} admin family`, `${tag} admin queued`]);
        });
        log({ sentBy: "automatic", limit: 100 }).then((resp) => {
            expect(resp.body.total).to.be.at.least(1);
            for (const r of resp.body.rows) expect(r.sentByUserId).to.be.null;
        });
        log({ sentBy: "someone" }, { status: 400 });
    });

    it("finds every word in the subject, the address or the recipient's name", () => {
        log({ q: `${tag} Turner` }).then((resp) => expect(subjects(resp)).to.deep.equal([`${tag} admin family`]));
        log({ q: `michael.turner@example.com ${tag}` }).then((resp) => expect(subjects(resp)).to.deep.equal([`${tag} admin family`]));
        log({ q: `Mathew ${tag} failed` }).then((resp) => {
            expect(subjects(resp)).to.deep.equal([`${tag} admin failed`, `${tag} sender failed`]);
        });
        log({ q: `${tag} %` }).its("body.total").should("eq", 0);
        log({ q: "x".repeat(101) }, { status: 400 });
    });

    it("links each row to the person or family it went to and keeps the server error", () => {
        log({ q: tag, limit: 100 }).then((resp) => {
            const family = resp.body.rows.find((r) => r.subject === `${tag} admin family`);
            expect(family).to.include({ familyId: TURNER_FAMILY_ID, personId: null, recipientName: "Turner" });
            expect(family.recipientUrl).to.match(new RegExp(`/people/family/${TURNER_FAMILY_ID}$`));

            const failed = resp.body.rows.find((r) => r.subject === `${tag} admin failed`);
            expect(failed).to.include({ personId: 2, recipientName: "Mathew Campbell", status: "failed" });
            expect(failed.recipientUrl).to.match(/\/people\/view\/2$/);
            expect(failed.error).to.be.a("string").and.not.be.empty;
        });
    });
});

describe("GET /api/email/log for a sender without admin rights", () => {
    it("lists only the mail they queued, under every filter and search", () => {
        log({ limit: 100 }, { as: "sender" }).then((resp) => {
            expect(resp.body.total).to.be.at.least(2);
            for (const r of resp.body.rows) expect(r.sentByUserId).to.eq(SENDER_ID);
        });
        log({ q: tag, limit: 100 }, { as: "sender" }).then((resp) => {
            expect(subjects(resp)).to.deep.equal([`${tag} sender failed`, `${tag} sender queued`]);
        });
        log({ q: `${tag} admin` }, { as: "sender" }).its("body.total").should("eq", 0);
        log({ status: "failed", q: tag }, { as: "sender" }).then((resp) => {
            expect(subjects(resp)).to.deep.equal([`${tag} sender failed`]);
        });
        log({ sentBy: SENDER_ID, q: tag }, { as: "sender" }).its("body.total").should("eq", 2);
    });

    it("sees a failed message's status but not the mail server's error", () => {
        log({ status: "failed", q: tag }, { as: "sender" }).then((resp) => {
            const failed = resp.body.rows.find((r) => r.subject === `${tag} sender failed`);
            expect(failed).to.include({ status: "failed", error: null });

            callers.sender("GET", `/api/email/log/${failed.id}`, null, 200).its("body.error").should("eq", null);
            callers.admin("GET", `/api/email/log/${failed.id}`, null, 200).its("body.error").should("be.a", "string").and("not.be.empty");
        });
    });

    it("is refused another sender's mail and mail ChurchCRM sent on its own", () => {
        log({ sentBy: ADMIN_ID }, { as: "sender", status: 403 });
        log({ sentBy: "automatic" }, { as: "sender", status: 403 });
    });

    it("needs the Email permission", () => {
        cy.makePrivateNoPermAPICall("GET", "/api/email/log", null, 403);
    });
});
