/// <reference types="cypress" />

/**
 * Server-side send from a single record and from a group (#9876).
 *
 * The person view, family view and photo gallery carry a "Send email" button
 * (data-email-composer + data-email-person-id / data-email-family-id) that opens
 * the composer for that one record; the group view's Email button opens it for
 * the whole group. Both queue through POST /api/email/send with ids, one message
 * per recipient; the composer then sends the batch (#10356), and the outcome is
 * checked in the Mailpit sink.
 */

const mailpitMessages = (tag) =>
    cy
        .request({ method: "GET", url: `${Cypress.mailpitUrl()}/api/v1/messages?limit=100` })
        .then((resp) => resp.body.messages.filter((m) => m.Subject.includes(tag)));

/** Fills the compose form and submits; returns the send response via the alias. */
const composeAndSend = (subject, body) => {
    cy.intercept("POST", "**/api/email/send").as("send");
    cy.get("#crm-email-send-btn").should("be.visible").click();
    cy.get("#crm-email-subject").should("be.visible").type(subject);
    // The body is pre-filled with two blank lines and the closing; the author types above it.
    cy.get("#crm-email-body").type(`{moveToStart}${body}`);
    cy.get("#crm-email-send-submit").click();
    return cy.wait("@send");
};

describe("Send email from a record", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("person view: the Send button opens the composer for that person and sends one message", () => {
        const tag = `crm-9876-person-${Date.now()}`;
        cy.visit("/people/view/2");

        cy.get("[data-email-composer][data-email-person-id='2']").should("be.visible").click();
        cy.get("#crm-email-composer-modal").should("be.visible");
        cy.get("#crm-email-composer-modal .modal-title").should("contain.text", "Mathew Campbell");
        cy.get("#crm-email-composer-modal .modal-title .badge").should("have.text", "1");
        // The church default "to" is not offered for a one-to-one message
        cy.get("#crm-email-include-default").should("not.exist");

        // Compose Message opens the form with the closing pre-filled under two blank lines
        cy.get("#crm-email-send-btn").should("contain.text", "Compose Message").click();
        // "Sincerely," + the logged-in user's name + the church name (read live: other specs
        // rename the church and do not restore it)
        cy.request("/admin/api/system/config/sChurchName").then((cfg) => {
            cy.get("#crm-email-body")
                .invoke("val")
                .should("eq", `\n\nSincerely,\nChurch Admin\n${cfg.body.value}`);
        });
        cy.get("#crm-email-send-btn").should("contain.text", "Cancel").click();

        composeAndSend(`Hello ${tag}`, "See you Sunday.").then(({ request, response }) => {
            expect(request.body).to.deep.include({ personIds: [2], familyIds: [] });
            expect(request.body).to.not.have.property("recipients");
            expect(response.statusCode).to.eq(200);
            expect(response.body.counts).to.deep.equal({ queued: 1, sent: 0, skipped: 0, failed: 0 });
        });
        cy.get("#crm-email-compose-form .alert-success").should("contain.text", "Email sent to 1 recipient");

        mailpitMessages(tag).then((messages) => {
            expect(messages).to.have.length(1);
            expect(messages[0].To[0].Address).to.equal("mathew.campbell@example.com");
        });
    });

    it("Preview shows the formatted message for the first recipient without sending", () => {
        cy.visit("/people/view/2");
        cy.get("[data-email-composer][data-email-person-id='2']").click();
        cy.get("#crm-email-send-btn").should("be.visible").click();
        cy.get("#crm-email-subject").type("Preview check");
        cy.get("#crm-email-body").type("{moveToStart}Hi Mathew,");
        cy.intercept("POST", "**/api/email/preview").as("preview");
        cy.intercept("POST", "**/api/email/send").as("send");
        cy.get("#crm-email-preview-btn").click();
        cy.wait("@preview").its("response.statusCode").should("eq", 200);
        cy.get("#crm-email-preview-modal").should("be.visible");
        cy.get("#crm-email-preview-title").should("contain.text", "Mathew Campbell");
        cy.get("#crm-email-preview-frame")
            .should("have.attr", "srcdoc")
            .and("include", "Hi Mathew,")
            .and("include", "Sincerely,")
            .and("not.include", "Dear ")
            .and("not.include", "You received this email");
        cy.get("@send.all").should("have.length", 0);
        // Bootstrap ignores hide() while the fade-in is still running; let it finish.
        cy.wait(500);
        cy.get("#crm-email-preview-modal .modal-footer button").click();
        cy.get("#crm-email-preview-modal").should("not.be.visible");
        cy.get("#crm-email-composer-modal").should("be.visible");
    });

    it("family view: each member row and the family address carry a Send button", () => {
        cy.visit("/people/family/1");
        cy.get("[data-email-composer][data-email-person-id]").should("have.length.greaterThan", 0);
        cy.get("[data-email-composer][data-email-person-id='2']").first().click();
        cy.get("#crm-email-composer-modal").should("be.visible");
        cy.get("#crm-email-composer-modal .modal-title").should("contain.text", "Mathew Campbell");
    });

    it("family view: the family's Send button mails the head of household", () => {
        const tag = `crm-msi11-family-${Date.now()}`;
        cy.visit("/people/family/2");

        cy.get("#family-email-composer").should("be.visible").click();
        cy.get("#crm-email-composer-modal").should("be.visible");
        cy.get("#crm-email-composer-modal .modal-title").should("contain.text", "Hart Family");

        composeAndSend(`Family ${tag}`, "Hello Harts.").then(({ request, response }) => {
            expect(request.body).to.deep.include({ personIds: [], familyIds: [2] });
            expect(response.body.counts).to.deep.equal({ queued: 1, sent: 0, skipped: 0, failed: 0 });
            expect(response.body.queued[0]).to.include({ familyId: 2, email: "constance.hart@example.com" });
        });
        cy.get("#crm-email-compose-form .alert-success").should("contain.text", "Email sent to 1 recipient");
        mailpitMessages(tag).then((messages) => {
            expect(messages.map((m) => m.To[0].Address)).to.deep.equal(["constance.hart@example.com"]);
        });
    });

    it("group view: the Email button sends one message per member and lists who was skipped", () => {
        const tag = `crm-9876-group-${Date.now()}`;
        cy.visit("/groups/view/1");

        cy.intercept("GET", "**/api/groups/1/emails").as("list");
        cy.get("[data-email-composer][data-email-endpoint='groups/1/emails']").should("be.visible").click();
        cy.wait("@list").then(({ response }) => {
            expect(response.body.recipients).to.be.an("array").with.length(response.body.emails.length);
        });
        cy.get("#crm-email-composer-modal").should("be.visible");
        // Recipient lines show the person's name, not only the address
        cy.get("#crm-email-composer-modal details").click();
        cy.get("#crm-email-composer-modal details div").should("contain.text", "<");

        cy.get("@list").then(({ response }) => {
            const expected = response.body.recipients.length;
            composeAndSend(`Group ${tag}`, "Practice moves to 7pm.").then(({ request, response: sendResp }) => {
                expect(request.body.personIds).to.have.length(expected);
                expect(sendResp.body.counts.queued).to.equal(expected);
            });
            cy.get("#crm-email-compose-form .alert-success").should("contain.text", `Email sent to ${expected} recipient`);
            mailpitMessages(tag).then((messages) => {
                expect(messages).to.have.length(expected);
                for (const m of messages) expect(m.To).to.have.length(1);
            });
        });
    });
});

describe("A send the composer cannot finish stays queued", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("tells the sender it is queued, shows it in the history, and the timer job sends it", () => {
        const tag = `crm-10356-ui-${Date.now()}`;
        cy.intercept("POST", "**/api/email/batches/*/send", { statusCode: 503, body: {} }).as("batchSend");
        // The page footer runs the timer jobs too; keep it from sending the row before we look.
        cy.intercept("POST", "**/api/background/timerjobs", { statusCode: 200, body: { ran: false } });
        cy.visit("/people/view/2");
        cy.get("[data-email-composer][data-email-person-id='2']").click();
        composeAndSend(`Queued ${tag}`, "See you Sunday.");
        cy.wait("@batchSend");

        cy.get("#crm-email-compose-form .alert-info")
            .should("contain.text", "Messages queued: 1. They will be sent in the background.")
            .find("#crm-email-history-link")
            .should("have.attr", "href")
            .and("include", "/admin/email/log");
        cy.get("#crm-email-send-submit").should("contain.text", "Queued").and("be.disabled");

        cy.visit("/admin/email/log");
        cy.get("#email-queued-count").should("be.visible");
        cy.contains("[data-email-history-table] tr", `Queued ${tag}`).should("have.attr", "data-email-status", "queued");

        cy.request("POST", "/api/background/timerjobs", { force: true }).its("body.ran").should("eq", true);

        cy.reload();
        cy.contains("[data-email-history-table] tr", `Queued ${tag}`)
            .should("have.attr", "data-email-status", "sent")
            .and("contain.text", "Sent");
        mailpitMessages(tag).should("have.length", 1);
    });
});

describe("Send email is hidden without the Email permission", () => {
    it("finance-only user sees the mailto link but no Send button on the person view", () => {
        cy.setupFinanceOnlySession();
        cy.visit("/people/view/2");
        cy.get("a[href^='mailto:']").should("exist");
        cy.get("[data-email-composer]").should("not.exist");
    });
});
