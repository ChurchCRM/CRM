/// <reference types="cypress" />

/**
 * Email history UI (#9877): the Recent Emails card on the person view (5 latest,
 * subject opens a modal with the rendered body), the Show all page (paginated,
 * newest first), the family view card and the admin Recent Sends panel.
 */
describe("Email history on the person view", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("shows the five latest emails with a Show all link and opens the body in a modal", () => {
        cy.visit("/people/view/2");
        cy.get("#email-history-card").should("be.visible");
        cy.get("#email-history-card [data-email-history-table] tbody tr").should("have.length", 5);
        // newest first (other specs may have added newer rows; order is what matters)
        cy.get("#email-history-card [data-email-history-table] tbody tr td:first-child").then(($cells) => {
            const dates = [...$cells].map((c) => c.textContent.trim());
            expect([...dates].sort().reverse()).to.deep.equal(dates);
        });
        cy.get("#email-history-show-all").should("contain.text", "Show all");

        // The seeded composer row (id 1) may have scrolled off the 5-row card: open the
        // first composer row instead and check the modal shows what the row says.
        cy.get("#email-history-card .email-history-open").first().then(($link) => {
            const id = $link.attr("data-email-log-id");
            const subject = $link.text().trim();
            cy.intercept("GET", `**/api/email/log/${id}`).as("detail");
            cy.wrap($link).click();
            cy.wait("@detail");
            cy.get("#email-history-modal").should("be.visible");
            cy.get("#email-history-modal-title").should("have.text", subject);
            cy.get("#email-history-modal [data-field='sentBy']").should("contain.text", "Church Admin");
            cy.get("#email-history-modal-body").should("be.visible").and("have.attr", "srcdoc").and("include", "<");
        });
    });

    it("tells the viewer when an email's content is not stored", () => {
        // The full page lists every row, so the seeded reset-link row (id 7) is always there
        cy.visit("/people/view/3/emails");
        cy.get(".email-history-open[data-email-log-id='7']").click();
        cy.get("#email-history-modal").should("be.visible");
        cy.get("#email-history-modal-nobody").should("be.visible");
        cy.get("#email-history-modal-body").should("not.be.visible");
    });

    it("Show all opens the full, paginated history newest first", () => {
        cy.visit("/people/view/2");
        cy.get("#email-history-show-all").click();
        // Base path may be a sub-directory in CI (/churchcrm/…), so match the tail only
        cy.location("pathname").should("match", /\/people\/view\/2\/emails$/);
        cy.contains("h3", "Email History").should("be.visible");
        cy.get("[data-email-history-table] tbody tr").should("have.length.at.least", 6);
        // newest first on the page
        cy.get("[data-email-history-table] tbody tr td:first-child").then(($cells) => {
            const dates = [...$cells].map((c) => c.textContent.trim());
            expect([...dates].sort().reverse()).to.deep.equal(dates);
        });
        // The oldest seeded row is the last row of the last page, however many pages there are
        cy.makePrivateAdminAPICall("GET", "/api/email/log?personId=2&limit=25").then((resp) => {
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
        cy.makePrivateAdminAPICall("GET", "/api/email/log?personId=2&limit=25").then((resp) => {
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
        cy.get("#timeline .timeline-filter-chip[data-filter='email']").should("be.visible").click();
        cy.get("#timeline .timeline-event[data-timeline-category='email']").should("be.visible").and("contain.text", "Welcome to the choir");
    });
});

describe("Email history on the email dashboard", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("admin email dashboard shows recent sends with a failed count", () => {
        cy.visit("/v2/email/dashboard");
        cy.get("#email-recent-sends").should("be.visible").and("contain.text", "failed");
        cy.get("#email-recent-sends [data-email-history-table] tbody tr").should("have.length.at.least", 7);
    });
});
