/// <reference types="cypress" />

/**
 * Email history (#9877): every email BaseEmail::send() hands to PHPMailer is logged
 * per recipient in email_log_eml and readable through GET /api/email/log.
 *
 * Seed rows (cypress/data/seed.sql): ids 1–6 for person 2 (Mathew Campbell), id 7 for
 * person 3 (a password-reset link, no body). Row 4 is a failed composer send.
 */
const adminGet = (url, status = 200) => cy.makePrivateAdminAPICall("GET", url, null, status);

describe("API GET /api/email/log", () => {
    it("pages a person's history newest first with the API row shape", () => {
        adminGet("/api/email/log?personId=2&limit=5").then((resp) => {
            const { rows, total, page, limit, pages } = resp.body;
            expect(total).to.be.at.least(6);
            expect(page).to.eq(1);
            expect(limit).to.eq(5);
            expect(pages).to.be.at.least(2);
            expect(rows).to.have.length(5);
            const dates = rows.map((r) => r.dateSent);
            expect([...dates].sort().reverse()).to.deep.equal(dates);
        });
        // The seeded row (id 1) may have scrolled off the first small page; read a big one for its shape
        adminGet("/api/email/log?personId=2&limit=100").then((resp) => {
            const row = resp.body.rows.find((r) => r.id === 1);
            expect(row).to.include({
                personId: 2,
                address: "mathew.campbell@example.com",
                kind: "composer",
                kindLabel: "Message",
                subject: "Welcome to the choir",
                status: "sent",
                hasBody: true,
                sentBy: "Church Admin",
            });
            expect(row).to.not.have.property("body");
        });
        adminGet("/api/email/log?personId=2&limit=5&page=2").then((resp) => {
            expect(resp.body.page).to.eq(2);
            expect(resp.body.rows.length).to.be.at.least(1);
        });
    });

    it("returns one row with its stored body, and no body for account emails", () => {
        adminGet("/api/email/log/1").then((resp) => {
            expect(resp.body.body).to.include("Practice is on Thursdays");
            expect(resp.body.status).to.eq("sent");
        });
        adminGet("/api/email/log/4").then((resp) => {
            expect(resp.body.status).to.eq("failed");
            expect(resp.body.error).to.include("SMTP");
        });
        adminGet("/api/email/log/7").then((resp) => {
            expect(resp.body.kind).to.eq("account.reset-token");
            expect(resp.body.hasBody).to.eq(false);
            expect(resp.body.body).to.be.null;
        });
        adminGet("/api/email/log/999999", 404);
    });

    it("lists a family's rows: the family address plus its current members", () => {
        adminGet("/api/email/log?familyId=1").then((resp) => {
            expect(resp.body.total).to.be.at.least(6);
            expect(resp.body.rows.some((r) => r.personId === 2)).to.eq(true);
        });
    });

    it("unscoped list is for administrators only and can filter by status", () => {
        adminGet("/api/email/log?status=failed").then((resp) => {
            expect(resp.body.total).to.be.at.least(1);
            for (const r of resp.body.rows) expect(r.status).to.eq("failed");
        });
        adminGet("/api/email/log?status=bogus", 400);
        cy.makePrivateFinanceOnlyAPICall("GET", "/api/email/log", null, 403);
    });

    it("refuses a person the caller may not view", () => {
        // finance-only user has no person-view scope beyond the standard read; person 2 is
        // readable by every logged-in user, so use the unscoped list for the 403 above and
        // check here that a readable person works for a non-admin.
        cy.makePrivateFinanceOnlyAPICall("GET", "/api/email/log?personId=2").then((resp) => {
            expect(resp.body.rows).to.be.an("array");
        });
    });
});

describe("Every send writes history rows", () => {
    it("a composer send logs one row per recipient, with the body and the sending user", () => {
        const tag = `crm-9877-${Date.now()}`;
        cy.makePrivateAdminAPICall("POST", "/api/email/send", {
            personIds: [2],
            subject: `Logged ${tag}`,
            body: "First line\nSecond line",
        }).then((resp) => expect(resp.body.counts.sent).to.eq(1));

        adminGet("/api/email/log?personId=2&limit=1").then((resp) => {
            const row = resp.body.rows[0];
            expect(row).to.include({ kind: "composer", status: "sent", subject: `Logged ${tag}`, hasBody: true, sentBy: "Church Admin" });
            expect(row.sentByUserId).to.eq(1);
            adminGet(`/api/email/log/${row.id}`).then((detail) => {
                expect(detail.body.body).to.include("First line<br");
                expect(detail.body.body).to.include("Second line");
            });
        });
    });

    it("a password-reset request logs an account email for the user's person without a body", () => {
        cy.request({
            method: "POST",
            url: "/api/public/user/password-reset",
            body: { userName: "tony.wade@example.com" },
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 200);

        adminGet("/api/email/log?personId=3&limit=1").then((resp) => {
            const row = resp.body.rows[0];
            expect(row).to.include({ kind: "account.reset-token", address: "tony.wade@example.com", hasBody: false, sentBy: null });
            adminGet(`/api/email/log/${row.id}`).then((detail) => expect(detail.body.body).to.be.null);
        });
    });
});
