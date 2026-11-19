/// <reference types="cypress" />

/**
 * Email history UI (#9877): the person timeline and Show all page (paginated,
 * newest first). The email log table is admin.email-log.spec.js and
 * email.sender-history.spec.js.
 */
describe("Email history on the person view", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("shows email history in the timeline and links to the full history", () => {
        cy.visit("/people/view/2");
        cy.get("#email-history-card").should("not.exist");
        cy.get("#nav-item-timeline").click();
        cy.get("#timeline .timeline-filter-chip[data-filter='email']").should("be.visible").click();
        cy.get("#timeline .timeline-event[data-timeline-category='email']")
            .should("have.length.at.least", 6)
            .and("contain.text", "Sent by")
            .and("contain.text", "Church Admin");
        cy.get("#timeline-email-history-link").should("contain.text", "View all").click();
        cy.location("pathname").should("match", /\/people\/view\/2\/emails$/);
        cy.visit("/people/view/2");
        cy.get("#person-actions-dropdown").click();
        cy.get(".dropdown-menu").should("contain.text", "Email History");
    });

    it("tells the viewer when an email's content is not stored", () => {
        // Person 3 has the seeded reset-link row plus whatever other specs sent; pick the
        // newest row without a stored body from the first page (API and page share the order).
        cy.request("/api/email/log?personId=3&limit=25").then((resp) => {
            const row = resp.body.rows.find((r) => !r.hasBody);
            expect(row, "an account email without a stored body on page 1").to.exist;
            cy.visit("/people/view/3/emails");
            cy.get(`.email-history-open[data-email-log-id='${row.id}']`).click();
        });
        cy.get("#email-history-modal").should("be.visible");
        cy.get("#email-history-modal-nobody")
            .should("be.visible")
            .and("contain.text", "messages written in the email composer and of volunteer emails only");
        cy.get("#email-history-modal-body").should("not.be.visible");
    });

    it("Show all opens the full, paginated history newest first", () => {
        cy.visit("/people/view/2");
        cy.get("#nav-item-timeline").click();
        cy.get("#timeline .timeline-filter-chip[data-filter='email']").click();
        cy.get("#timeline-email-history-link").click();
        // Base path may be a sub-directory in CI (/churchcrm/…), so match the tail only
        cy.location("pathname").should("match", /\/people\/view\/2\/emails$/);
        cy.contains("h3", "Email History").should("be.visible");
        cy.get("[data-email-history-table] tbody tr").should("have.length.at.least", 6);
        // newest first on the page
        cy.get("[data-email-history-table] tbody tr td:first-child").then(($cells) => {
            const dates = [...$cells].map((c) => c.textContent.trim());
            expect([...dates].sort().reverse()).to.deep.equal(dates);
        });
        // The oldest seeded row is the last row of the last page, however many pages there are.
        // Read the API with the browser session: an API-key request would replace the
        // logged-in session cookie and the next visit would land on the login page.
        cy.request("/api/email/log?personId=2&limit=25").then((resp) => {
            const lastPage = resp.body.pages;
            cy.visit(`/people/view/2/emails?page=${lastPage}`);
            cy.get("[data-email-history-table] tbody tr").last().should("contain.text", "Oldest message");
            // subject link works here too
            cy.get(".email-history-open[data-email-log-id='6']").click();
            cy.get("#email-history-modal").should("be.visible");
            cy.get("#email-history-modal-title").should("have.text", "Oldest message");
        });
    });

    it("paginates the full history at 25 rows per page", () => {
        cy.request("/api/email/log?personId=2&limit=25").then((resp) => {
            const { total, pages } = resp.body;
            cy.visit("/people/view/2/emails?page=1");
            cy.get("[data-email-history-table] tbody tr").should("have.length", Math.min(25, total));
            if (pages > 1) {
                cy.get("#email-history-pagination").should("be.visible");
                cy.get("#email-history-pagination .page-item.active").should("contain.text", "1");
                cy.contains("#email-history-pagination a", "Next").click();
                cy.location("search").should("eq", "?page=2");
                cy.get("[data-email-history-table] tbody tr").should("have.length.at.least", 1);
            } else {
                // a single page shows no pagination controls
                cy.get("#email-history-pagination").should("not.exist");
            }
        });
    });

    it("the timeline lists emails under an Emails filter", () => {
        cy.visit("/people/view/2");
        cy.get("#nav-item-timeline").click();
        cy.get("#timeline .timeline-filter-chip[data-filter='email']").should("be.visible").click();
        cy.get("#timeline .timeline-event[data-timeline-category='email']").should("be.visible").and("contain.text", "Welcome to the choir");
    });
});
