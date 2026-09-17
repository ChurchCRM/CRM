/// <reference types="cypress" />

/**
 * Member Portal — Email History API (`/api/portal/me/emails`).
 *
 * The portal's read side of the email history #9877 writes. Like every other
 * `/api/portal/*` route it is session-only and takes no person id: the actor is
 * the session (design P11), so a member can only ever page through their own
 * history.
 *
 * What is under test:
 *   - the list returns only the acting member's rows, newest first, with the
 *     same `rows/total/page/limit/pages` envelope the staff endpoint uses
 *   - `limit` paginates and is clamped; `page` is sanitized
 *   - list rows never carry the body; the detail route does
 *   - another person's row is 404, not 403 — even a person in the member's own
 *     family, because family membership is not permission to read their mail
 *   - an unknown id is the same 404
 *   - an API-key caller is refused, exactly as on `/api/portal/me`
 *
 * Fixtures are seeded by sending real email through `POST /api/email/send`
 * (#9876), which is what writes `email_log_eml` rows in the first place, so the
 * test exercises the whole chain rather than hand-written rows.
 *
 * Personas: Lena Black (user 100, person 100, family 20 — the self-service
 * member) and Samantha Black (person 102, the same family).
 */
const MEMBER_USER = "lena.black.editself.notes@example.com";
const MEMBER_PASSWORD = "changeme";
const LENA_PERSON_ID = 100;
const SAMANTHA_PERSON_ID = 102;

const tag = `portal-email-${Date.now()}`;

/** Sign in with the login form so cy.request() inherits a real session. */
const portalLogin = () => {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(MEMBER_USER);
    cy.get("input[name=Password]").type(`${MEMBER_PASSWORD}{enter}`);
    cy.url({ timeout: 10000 }).should("include", "/portal");
};

const portalGet = (url, expectedStatus = 200) =>
    cy.request({ url, failOnStatusCode: false }).then((response) => {
        expect(response.status, url).to.eq(expectedStatus);
        return response;
    });

describe("Member Portal API — /api/portal/me/emails", () => {
    before(() => {
        // Three messages to Lena and one to another member of her family. The
        // subjects carry the run's tag so a re-run never reads the last run's
        // rows. Sent oldest first, so "newest first" is a real assertion.
        for (const n of [1, 2, 3]) {
            cy.makePrivateAdminAPICall("POST", "/api/email/send", {
                personIds: [LENA_PERSON_ID],
                subject: `${tag} message ${n}`,
                body: `Body of message ${n} for Lena`,
            }).then((resp) => expect(resp.body.counts.sent).to.eq(1));
        }
        cy.makePrivateAdminAPICall("POST", "/api/email/send", {
            personIds: [SAMANTHA_PERSON_ID],
            subject: `${tag} not for Lena`,
            body: "This one belongs to Samantha",
        }).then((resp) => expect(resp.body.counts.sent).to.eq(1));
    });

    describe("The list", () => {
        beforeEach(() => {
            portalLogin();
        });

        it("Returns the member's own rows, newest first, in the standard envelope", () => {
            portalGet("/api/portal/me/emails").then((resp) => {
                const { rows, total, page, limit, pages } = resp.body;
                expect(page).to.eq(1);
                expect(limit).to.eq(25);
                expect(total).to.be.at.least(3);
                expect(pages).to.be.at.least(1);

                const dates = rows.map((r) => r.dateSent);
                expect([...dates].sort().reverse(), "newest first").to.deep.equal(dates);

                for (const row of rows) {
                    expect(row.personId, "every row belongs to the acting member").to.eq(LENA_PERSON_ID);
                    expect(row, "the list never carries the body").to.not.have.property("body");
                }

                const mine = rows.filter((r) => r.subject.startsWith(tag));
                expect(mine.map((r) => r.subject)).to.deep.equal([
                    `${tag} message 3`,
                    `${tag} message 2`,
                    `${tag} message 1`,
                ]);
                expect(mine[0]).to.include({
                    kind: "composer",
                    kindLabel: "Message",
                    status: "sent",
                    hasBody: true,
                    address: "lena.walker@example.com",
                });
            });
        });

        it("Never lists another person's row, not even one in the same family", () => {
            portalGet("/api/portal/me/emails?limit=100").then((resp) => {
                expect(resp.body.rows.some((r) => r.subject === `${tag} not for Lena`)).to.eq(false);
                expect(resp.body.rows.some((r) => r.personId !== LENA_PERSON_ID)).to.eq(false);
            });
        });

        it("Paginates on limit and page", () => {
            portalGet("/api/portal/me/emails?limit=2").then((resp) => {
                expect(resp.body.limit).to.eq(2);
                expect(resp.body.rows).to.have.length(2);
                expect(resp.body.pages).to.be.at.least(2);

                portalGet("/api/portal/me/emails?limit=2&page=2").then((second) => {
                    expect(second.body.page).to.eq(2);
                    expect(second.body.rows.length).to.be.at.least(1);
                    const firstIds = resp.body.rows.map((r) => r.id);
                    for (const row of second.body.rows) {
                        expect(firstIds, "page 2 is a different slice").to.not.include(row.id);
                    }
                });
            });
        });

        it("Sanitizes page and limit rather than failing on them", () => {
            // Not a positive integer means "unset", not "as small as possible":
            // each falls back to its default rather than to the clamp's floor.
            portalGet("/api/portal/me/emails?page=0&limit=0").then((resp) => {
                expect(resp.body.page).to.eq(1);
                expect(resp.body.limit).to.eq(25);
            });
            portalGet("/api/portal/me/emails?page=-4&limit=nonsense").then((resp) => {
                expect(resp.body.page).to.eq(1);
                expect(resp.body.limit).to.eq(25);
            });
            portalGet("/api/portal/me/emails?limit=5000").then((resp) => {
                expect(resp.body.limit, "limit is capped at 100").to.eq(100);
            });
        });
    });

    describe("One email", () => {
        beforeEach(() => {
            portalLogin();
        });

        it("Returns the member's own row with its stored body", () => {
            portalGet("/api/portal/me/emails?limit=100").then((list) => {
                const row = list.body.rows.find((r) => r.subject === `${tag} message 1`);
                expect(row, "the seeded message is in the history").to.not.be.undefined;

                portalGet(`/api/portal/me/emails/${row.id}`).then((resp) => {
                    expect(resp.body.id).to.eq(row.id);
                    expect(resp.body.subject).to.eq(`${tag} message 1`);
                    expect(resp.body.personId).to.eq(LENA_PERSON_ID);
                    expect(resp.body.body).to.include("Body of message 1 for Lena");
                });
            });
        });

        it("Answers 404 for a row addressed to somebody else", () => {
            // Read the other person's row id with an administrator's key, then
            // ask for it as Lena: the answer must be indistinguishable from an
            // id that does not exist.
            cy.makePrivateAdminAPICall("GET", `/api/email/log?personId=${SAMANTHA_PERSON_ID}&limit=100`).then((resp) => {
                const foreign = resp.body.rows.find((r) => r.subject === `${tag} not for Lena`);
                expect(foreign, "Samantha's row exists").to.not.be.undefined;

                portalLogin();
                portalGet(`/api/portal/me/emails/${foreign.id}`, 404);
            });
        });

        it("Answers 404 for an id that does not exist", () => {
            portalGet("/api/portal/me/emails/99999999", 404);
        });
    });

    describe("Who may call it", () => {
        it("Refuses an API-key caller, even an administrator's key", () => {
            cy.makePrivateAdminAPICall("GET", "/api/portal/me/emails", null, 403);
            cy.makePrivateAdminAPICall("GET", "/api/portal/me/emails/1", null, 403);
        });

        it("Refuses an Edit-Self account's API key too", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/portal/me/emails", null, 403);
        });
    });
});
