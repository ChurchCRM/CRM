/// <reference types="cypress" />

/**
 * Member Portal — Email History (`/portal/email-history`).
 *
 * A member's record of what the church has emailed them, newest first and
 * paginated, reached from the header's account menu rather than the main nav.
 *
 * What is under test:
 *   - "Email History" is the first item of the account menu, above
 *     "Sign out"
 *   - the page lists the member's emails newest first, only mail that was
 *     sent, and shows no delivery status (#10356)
 *   - Previous / Next page through them and "Page X of Y" follows
 *   - the detail page shows the subject, the metadata and the body in a
 *     sandboxed iframe — stored HTML is never injected into the portal DOM
 *   - an account email, whose body was deliberately not kept, says so
 *
 * Fixtures are seeded through the send API (#9876), which is what writes the
 * history rows. Each sign-in writes the account email with no body: the
 * sign-in code.
 *
 * Persona: Lena Black (person 100), signed in with her email.
 */
const LENA_PERSON_ID = 100;

const tag = `portal-ui-email-${Date.now()}`;
const SEEDED = 4;

const login = () => {
    cy.signInAsMember(100);
    cy.visit("/portal/");
};

describe("Member Portal — Email History", () => {
    before(() => {
        // Four composer messages, oldest first, so "newest first" is a real
        // assertion and one small page is not the whole history.
        for (let n = 1; n <= SEEDED; n += 1) {
            cy.sendComposerEmailNow({
                personIds: [LENA_PERSON_ID],
                subject: `${tag} message ${n}`,
                body: `<p>Body of message ${n}</p>`,
            }).then((result) => expect(result.progress.sent).to.eq(1));
        }
    });

    beforeEach(() => {
        login();
    });

    describe("The account menu", () => {
        it("Offers Email History as its first item, above Sign out", () => {
            cy.get("#portal-account-toggle").click();
            cy.get("#portal-account-menu").should("be.visible");

            cy.get('#portal-account-menu [role="menuitem"]')
                .first()
                .should("contain.text", "Email History")
                .and("have.attr", "href")
                .and("include", "/portal/email-history");

            cy.get('#portal-account-menu [role="menuitem"]').then(($items) => {
                const labels = [...$items].map((el) => el.textContent.trim());
                expect(labels.indexOf("Email History")).to.eq(0);
                expect(labels.indexOf("Email History")).to.be.lessThan(labels.indexOf("Sign out"));
            });
        });

        it("Keeps the keyboard behaviour with the extra item", () => {
            cy.get("#portal-account-toggle").click();
            cy.focused().should("contain.text", "Email History");
            cy.focused().trigger("keydown", { key: "ArrowDown" });
            cy.focused().should("contain.text", "Sign out");
            cy.focused().trigger("keydown", { key: "ArrowUp" });
            cy.focused().should("contain.text", "Email History");
        });

        it("Opens the page from the menu", () => {
            cy.get("#portal-account-toggle").click();
            cy.get("#portal-account-menu").contains('[role="menuitem"]', "Email History").click();
            cy.url({ timeout: 10000 }).should("include", "/portal/email-history");
            cy.get("#portal-email-history").should("be.visible");
        });
    });

    describe("The list", () => {
        it("Lists the member's emails newest first", () => {
            cy.visit("/portal/email-history");

            cy.get("[data-email-row]").should("have.length.at.least", 1);
            cy.get("[data-email-subject]").then(($subjects) => {
                const mine = [...$subjects]
                    .map((el) => el.textContent.trim())
                    .filter((text) => text.startsWith(tag));
                expect(mine[0]).to.eq(`${tag} message ${SEEDED}`);
                expect(mine[1]).to.eq(`${tag} message ${SEEDED - 1}`);
            });

            cy.get("[data-email-row]").first().should("contain.text", "Member Portal sign-in code");
            cy.contains("[data-email-row]", `${tag} message ${SEEDED}`).within(() => {
                cy.get("[data-email-type]").should("contain.text", "Message");
            });
        });

        it("Shows only mail that was sent, with no status", () => {
            cy.visit("/portal/email-history?limit=100");

            cy.get("#portal-email-table thead").should("not.contain.text", "Status");
            cy.get("[data-email-status]").should("not.exist");
            cy.get("#portal-email-table").should("contain.text", `${tag} message 1`);
            cy.get("#portal-email-table").should("not.contain.text", "Seeded failed message for Lena");
            cy.get("#portal-email-table").should("not.contain.text", "Seeded skipped birthday greeting for Lena");
        });

        it("Pages with Previous and Next", () => {
            cy.visit("/portal/email-history?limit=2");

            cy.get("#portal-email-pager").should("be.visible").and("contain.text", "Page 1 of");
            cy.get("[data-email-row]").should("have.length", 2);
            cy.get("#portal-email-prev").should("not.exist");

            // Rows are compared by id: every sign-in writes a sign-in code email with the same subject.
            cy.get("[data-email-row]")
                .first()
                .invoke("attr", "data-email-row")
                .then((firstOnPageOne) => {
                    cy.get("#portal-email-next").click();
                    cy.url().should("include", "page=2");
                    cy.get("#portal-email-pager").should("contain.text", "Page 2 of");
                    cy.get("[data-email-row]").first().should("not.have.attr", "data-email-row", firstOnPageOne);

                    cy.get("#portal-email-prev").should("be.visible").click();
                    cy.get("#portal-email-pager").should("contain.text", "Page 1 of");
                    cy.get("[data-email-row]").first().should("have.attr", "data-email-row", firstOnPageOne);
                });
        });
    });

    describe("One email", () => {
        it("Shows the subject and the body in a sandboxed iframe", () => {
            cy.visit("/portal/email-history");
            cy.contains("[data-email-subject]", `${tag} message ${SEEDED}`).click();

            cy.url().should("match", /\/portal\/email-history\/\d+$/);
            cy.get("#portal-email-detail").should("contain.text", `${tag} message ${SEEDED}`);
            cy.get("[data-field='status']").should("not.exist");
            cy.get("#portal-email-body")
                .should("exist")
                .and("have.attr", "sandbox")
                .then((sandbox) => {
                    // An empty sandbox attribute is the strictest form: no
                    // scripts, no forms, no top-level navigation.
                    expect(sandbox).to.eq("");
                });
            cy.get("#portal-email-body")
                .should("have.attr", "srcdoc")
                .and("include", `Body of message ${SEEDED}`);

            // The stored HTML must never reach the portal's own document.
            cy.get("#portal-email-detail").should("not.contain.html", `<p>Body of message ${SEEDED}</p>`);

            cy.get("#portal-email-back").should("be.visible").click();
            cy.url().should("match", /\/portal\/email-history\/?(\?.*)?$/);
        });

        it("Says so when the content of an email was not kept", () => {
            cy.visit("/portal/email-history");
            cy.contains("[data-email-row]", "Member Portal sign-in code").find("[data-email-subject]").click();

            cy.get("#portal-email-detail").should("be.visible");
            cy.get("#portal-email-nobody").should("contain.text", "The content of this email was not kept.");
            cy.get("#portal-email-body").should("not.exist");
        });

        it("Refuses an id that is not this member's", () => {
            cy.request({ url: "/portal/email-history/99999999", failOnStatusCode: false })
                .its("status")
                .should("eq", 404);
        });

        it("Refuses this member's own failed and skipped mail the same way", () => {
            // Seeded rows 8 (failed) and 9 (skipped) are addressed to Lena.
            for (const id of [8, 9]) {
                cy.request({ url: `/portal/email-history/${id}`, failOnStatusCode: false })
                    .its("status")
                    .should("eq", 404);
            }
        });
    });

    describe("On a phone", () => {
        it("Stacks each email into its own block instead of a wide table", () => {
            cy.viewport(375, 812);
            cy.visit("/portal/email-history");

            cy.get("#portal-email-history").then(($el) => {
                expect($el[0].scrollWidth, "the page does not scroll sideways").to.be.at.most(375);
            });
            cy.get("[data-email-row]")
                .first()
                .then(($row) => {
                    expect($row[0].getBoundingClientRect().width).to.be.at.most(375);
                });
        });
    });
});
