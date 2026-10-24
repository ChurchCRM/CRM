/// <reference types="cypress" />

/**
 * A sender without admin rights follows the mail they queued (#10601): the composer links to
 * the Email page, whose email log lists only their own mail and offers no Delete Queue.
 */

const tag = `crm-10601-sender-${Date.now()}`;

const composeAndSend = (subject, body) => {
    cy.intercept("POST", "**/api/email/send").as("send");
    cy.get("#crm-email-send-btn").should("be.visible").click();
    cy.get("#crm-email-subject").should("be.visible").type(subject);
    cy.get("#crm-email-body").type(`{moveToStart}${body}`);
    cy.get("#crm-email-send-submit").click();
    return cy.wait("@send");
};

before(() => {
    cy.makePrivateAdminAPICall("POST", "/api/email/send", { personIds: [2], subject: `${tag} from admin`, body: "Admin" }, 200);
});

after(() => {
    cy.makePrivateAdminAPICall("DELETE", "/admin/api/email/queue", null, 200);
});

describe("Sender follows the mail they queued", () => {
    beforeEach(() => {
        cy.setupStandardSession();
        // The page footer runs the timer jobs; keep it from sending the queued rows.
        cy.intercept("POST", "**/api/background/timerjobs", { statusCode: 200, body: { ran: false } });
    });

    it("the composer links to the Email page, which lists only their own mail", () => {
        cy.intercept("POST", "**/api/email/batches/*/send", { statusCode: 503, body: {} }).as("batchSend");
        cy.visit("/people/view/2");
        cy.get("[data-email-composer][data-email-person-id='2']").click();
        composeAndSend(`${tag} mine`, "See you Sunday.");
        cy.wait("@batchSend");

        cy.get("#crm-email-history-link").should("have.attr", "href").and("match", /\/people\/email$/);
        cy.get("#crm-email-history-link").click();
        cy.location("pathname").should("match", /\/people\/email$/);
        cy.get("#email-log-sent-by").should("not.exist");
        cy.get("#email-delete-queue").should("not.exist");

        cy.get("#email-log-card .dt-search input").type(tag);
        cy.contains("#email-log-table tbody tr", `${tag} mine`).should("have.attr", "data-email-status", "queued");
        cy.get("#email-log-table tbody tr").should("have.length", 1);
        cy.get("#email-log-table tbody").should("not.contain.text", `${tag} from admin`);

        cy.get("#email-queued-count").click();
        cy.get("#email-log-status").should("have.value", "queued");
        cy.get("#email-log-card .dt-search input").should("have.value", "");
        cy.contains("#email-log-table tbody tr", `${tag} mine`).should("be.visible");
        cy.get("#email-log-table tbody tr").each(($row) => {
            expect($row).to.have.attr("data-email-status", "queued");
            expect($row.find("td").last()).to.contain.text("Tony Campbell");
        });
    });
});
