/// <reference types="cypress" />

// Attribution is read back from the saved record through the check-in page, not from
// the POST response. Records change through the page's own session, because an API-key
// request in the middle of a test can drop the browser session.

function fromPage(path, body) {
    cy.window().then((win) =>
        win
            .fetch(`${win.CRM.root}/api/${path}`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(body),
            })
            .then((res) => expect(res.status, `POST ${path}`).to.eq(200)),
    );
}

describe("Check-in attribution on the event check-in page", () => {
    let eventId;

    before(() => {
        // quick-create reuses an event with the same date and type, so use a date no other spec shares.
        const day = Math.floor(Date.now() / 1000) % 3000;
        const date = new Date(Date.UTC(2091, 0, 1 + day)).toISOString().slice(0, 10);
        cy.makePrivateAdminAPICall("POST", "/api/events/quick-create", { eventTypeId: 1, date }, 200).then(
            (response) => {
                expect(response.body.created, "event must be new").to.eq(true);
                eventId = response.body.eventId;
            },
        );
    });

    after(() => {
        cy.cleanupEvents([eventId]);
    });

    beforeEach(() => cy.setupAdminSession());

    it("drops the previous checker when the next check-in names nobody", () => {
        cy.visit(`/event/checkin/${eventId}`);
        fromPage(`events/${eventId}/checkin`, { personId: 4, checkedInById: 1 });
        cy.reload();
        cy.get('.checkout-btn[data-person-id="4"]').should("have.attr", "data-checkin-id", "1");

        fromPage(`events/${eventId}/checkin`, { personId: 4 });
        cy.reload();
        cy.get('.checkout-btn[data-person-id="4"]').should("not.have.attr", "data-checkin-id");
    });

    it("drops the previous checkout person when the next checkout names nobody", () => {
        cy.visit(`/event/checkin/${eventId}`);
        fromPage(`events/${eventId}/checkin`, { personId: 5 });
        fromPage(`events/${eventId}/checkout`, { personId: 5, checkedOutById: 1 });
        cy.reload();
        cy.get('tr[data-person-id="5"] .checkout-by').invoke("text").should("not.be.empty");

        fromPage(`events/${eventId}/checkout`, { personId: 5 });
        cy.reload();
        cy.get('tr[data-person-id="5"] .checkout-by').invoke("text").then((t) => expect(t.trim()).to.eq(""));
    });

    it("drops the checkout person when the person is checked in again", () => {
        cy.visit(`/event/checkin/${eventId}`);
        fromPage(`events/${eventId}/checkin`, { personId: 6 });
        fromPage(`events/${eventId}/checkout`, { personId: 6, checkedOutById: 1 });
        fromPage(`events/${eventId}/checkin`, { personId: 6 });
        cy.reload();
        cy.get('tr[data-person-id="6"] .checkout-by').invoke("text").then((t) => expect(t.trim()).to.eq(""));
    });
});
