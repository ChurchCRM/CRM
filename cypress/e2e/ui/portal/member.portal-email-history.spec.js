/// <reference types="cypress" />

/**
 * Member Portal — Email History (`/portal/email-history`).
 *
 * A member's record of what the church has emailed them, newest first and
 * paginated, reached from the header's account menu rather than the main nav.
 *
 * What is under test:
 *   - "Email History" is the first item of the account menu, above
 *     "Change Password"
 *   - the page lists the member's emails newest first
 *   - Previous / Next page through them and "Page X of Y" follows
 *   - the detail page shows the subject, the metadata and the body in a
 *     sandboxed iframe — stored HTML is never injected into the portal DOM
 *   - an account email, whose body was deliberately not kept, says so
 *
 * Fixtures are seeded through the send API (#9876), which is what writes the
 * history rows, plus one password-reset request, which is what writes an
 * account email with no body.
 *
 * Persona: Lena Black (user 100, person 100). Her username is the address
 * below; the reset request has to name the *username*, not the person's email
 * (`lena.walker@example.com`), because that is what the public endpoint looks
 * up — and the reset mail then goes to the person's address, which is how the
 * row lands on person 100.
 */
const MEMBER_USER = "lena.black.editself.notes@example.com";
const MEMBER_PASSWORD = "changeme";
const LENA_PERSON_ID = 100;

const tag = `portal-ui-email-${Date.now()}`;
const SEEDED = 4;

const login = () => {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(MEMBER_USER);
    cy.get("input[name=Password]").type(`${MEMBER_PASSWORD}{enter}`);
    cy.url({ timeout: 10000 }).should("include", "/portal");
};

describe("Member Portal — Email History", () => {
    before(() => {
        // Four composer messages, oldest first, so "newest first" is a real
        // assertion and one small page is not the whole history.
        for (let n = 1; n <= SEEDED; n += 1) {
            cy.makePrivateAdminAPICall("POST", "/api/email/send", {
                personIds: [LENA_PERSON_ID],
                subject: `${tag} message ${n}`,
                body: `<p>Body of message ${n}</p>`,
            }).then((resp) => expect(resp.body.counts.sent).to.eq(1));
        }

        // An account email: the reset link is never stored, so its detail page
        // is the "not kept" branch.
        cy.request({
            method: "POST",
            url: "/api/public/user/password-reset",
            body: { userName: MEMBER_USER },
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 200);
    });

    beforeEach(() => {
        login();
    });

    describe("The account menu", () => {
        it("Offers Email History as its first item, above Change Password", () => {
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
                expect(labels.indexOf("Email History")).to.be.lessThan(labels.indexOf("Change Password"));
            });
        });

        it("Keeps the keyboard behaviour with the extra item", () => {
            cy.get("#portal-account-toggle").click();
            cy.focused().should("contain.text", "Email History");
            cy.focused().trigger("keydown", { key: "ArrowDown" });
            cy.focused().should("contain.text", "Change Password");
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

            // Type and status are on the row, and a sent message reads "Sent".
            cy.get("[data-email-row]").first().should("contain.text", "Password reset link");
            cy.contains("[data-email-row]", `${tag} message ${SEEDED}`).within(() => {
                cy.get("[data-email-type]").should("contain.text", "Message");
                cy.get("[data-email-status]").should("contain.text", "Sent");
            });
        });

        it("Pages with Previous and Next", () => {
            cy.visit("/portal/email-history?limit=2");

            cy.get("#portal-email-pager").should("be.visible").and("contain.text", "Page 1 of");
            cy.get("[data-email-row]").should("have.length", 2);
            cy.get("#portal-email-prev").should("not.exist");

            cy.get("[data-email-subject]")
                .first()
                .invoke("text")
                .then((firstOnPageOne) => {
                    cy.get("#portal-email-next").click();
                    cy.url().should("include", "page=2");
                    cy.get("#portal-email-pager").should("contain.text", "Page 2 of");
                    cy.get("[data-email-subject]").first().should("not.have.text", firstOnPageOne);

                    cy.get("#portal-email-prev").should("be.visible").click();
                    cy.get("#portal-email-pager").should("contain.text", "Page 1 of");
                    cy.get("[data-email-subject]").first().should("have.text", firstOnPageOne);
                });
        });
    });

    describe("One email", () => {
        it("Shows the subject and the body in a sandboxed iframe", () => {
            cy.visit("/portal/email-history");
            cy.contains("[data-email-subject]", `${tag} message ${SEEDED}`).click();

            cy.url().should("match", /\/portal\/email-history\/\d+$/);
            cy.get("#portal-email-detail").should("contain.text", `${tag} message ${SEEDED}`);
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
            cy.contains("[data-email-row]", "Password reset link").find("[data-email-subject]").click();

            cy.get("#portal-email-detail").should("be.visible");
            cy.get("#portal-email-nobody").should("contain.text", "The content of this email was not kept.");
            cy.get("#portal-email-body").should("not.exist");
        });

        it("Refuses an id that is not this member's", () => {
            cy.request({ url: "/portal/email-history/99999999", failOnStatusCode: false })
                .its("status")
                .should("eq", 404);
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
