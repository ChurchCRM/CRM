/// <reference types="cypress" />

/**
 * Email Log (#10601): an administrator finds a failed send and why it failed, follows the
 * queue, narrows the table by type and sender, and deletes the queue (#10356).
 */

const tag = `crm-10601-admin-${Date.now()}`;

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

before(() => {
    setChaos({ Recipient: { ErrorCode: 550, Probability: 100 } });
    cy.makePrivateAdminAPICall("POST", "/api/email/send", { personIds: [2], subject: `${tag} failed`, body: "Log" }, 200)
        .then((resp) => cy.makePrivateAdminAPICall("POST", `/api/email/batches/${resp.body.batch}/send`, null, 200))
        .its("body.failed")
        .should("eq", 1);
    setChaos();
    cy.makePrivateUserAPICall("POST", "/api/email/send", { familyIds: [23], subject: `${tag} queued`, body: "Log" }, 200);
});

after(() => {
    setChaos();
    cy.makePrivateAdminAPICall("DELETE", "/admin/api/email/queue", null, 200);
});

describe("Administrator works through the email log", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        // The page footer runs the timer jobs; keep it from sending the queued rows.
        cy.intercept("POST", "**/api/background/timerjobs", { statusCode: 200, body: { ran: false } });
        cy.intercept("GET", "**/api/email/log?*").as("log");
    });

    it("opens the failed sends from the badge and reads why one failed", () => {
        cy.visit("/admin/email/log");
        cy.wait("@log");
        cy.get("[data-email-failed-badge]").click();
        cy.wait("@log").its("request.url").should("include", "status=failed");
        cy.location("search").should("eq", "?status=failed");
        cy.get("#email-log-status").should("have.value", "failed");
        cy.get("#email-log-table tbody tr").each(($row) => expect($row).to.have.attr("data-email-status", "failed"));

        cy.get("#email-log-card .dt-search input").type(tag);
        cy.get("#email-log-table tbody tr").should("have.length", 1);
        cy.contains("#email-log-table tbody tr", `${tag} failed`).within(() => {
            cy.get("[data-email-failure-error]").should("contain.text", "recipients failed");
            cy.get("[data-email-recipient-link]")
                .should("have.text", "Mathew Campbell")
                .and("have.attr", "href")
                .and("match", /\/people\/view\/2$/);
            cy.get(".email-history-open").click();
        });
        cy.get("#email-history-modal").should("be.visible");
        cy.get("#email-history-modal [data-field='status']").should("contain.text", "recipients failed");

        cy.reload();
        cy.get("#email-log-status").should("have.value", "failed");
        cy.get("#email-log-card .dt-search input").should("have.value", tag);
        cy.contains("#email-log-table tbody tr", `${tag} failed`).should("be.visible");
    });

    it("follows the queue from its badge and narrows it by sender and type", () => {
        cy.visit("/admin/email/log");
        cy.wait("@log");
        cy.get("#email-queued-count").click();
        cy.wait("@log");
        cy.get("#email-log-status").should("have.value", "queued");
        cy.contains("#email-log-table tbody tr", `${tag} queued`)
            .should("have.attr", "data-email-status", "queued")
            .within(() => {
                cy.get("[data-email-recipient-link]")
                    .should("have.text", "Turner")
                    .and("have.attr", "href")
                    .and("match", /\/people\/family\/23$/);
                cy.contains("td", "Tony Campbell");
            });

        cy.get("#email-log-sent-by").select("Tony Campbell");
        cy.wait("@log").then(({ response }) => {
            expect(response.body.rows.length).to.be.at.least(1);
            for (const row of response.body.rows) expect(row).to.include({ sentByUserId: 3, status: "queued" });
        });
        cy.contains("#email-log-table tbody tr", `${tag} queued`).should("be.visible");

        cy.get("#email-log-kind").select("Birthday greeting");
        cy.wait("@log").its("response.body.total").should("eq", 0);
        cy.get("#email-log-table tbody").should("not.contain.text", `${tag} queued`);
        cy.location("search").should("include", "status=queued").and("include", "kind=birthday").and("include", "sentBy=3");
    });

    it("Delete Queue deletes the queued messages after a confirmation that names the count", () => {
        const subject = `Delete crm-10356-${Date.now()}`;
        cy.request("POST", "/api/email/send", { personIds: [2], subject, body: "Never sent" })
            .its("body.counts.queued")
            .should("eq", 1);

        cy.visit("/admin/email/log");
        cy.contains("#email-log-table tbody tr", subject).should("have.attr", "data-email-status", "queued");
        cy.get("#email-delete-queue")
            .should("be.visible")
            .invoke("attr", "data-queued-count")
            .then((count) => {
                cy.intercept("DELETE", "**/admin/api/email/queue").as("deleteQueue");
                cy.get("#email-delete-queue").click();
                cy.get(".bootbox.modal")
                    .should("be.visible")
                    .and("contain.text", `Delete every queued message? They will not be sent. Queued messages: ${count}`);
                cy.get(".bootbox.modal .bootbox-accept").click();
                cy.wait("@deleteQueue").its("response.body.deleted").should("eq", Number(count));
            });

        cy.get("#email-delete-queue").should("not.exist");
        cy.get("#email-queued-count").should("not.exist");
        cy.get("#email-log-card .dt-search input").type(subject);
        cy.get("#email-log-table tbody .dt-empty").should("be.visible");
    });
});
