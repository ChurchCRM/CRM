/// <reference types="cypress" />

// A checked-in member keeps their row in the left list, highlighted, so a volunteer can
// tap down the list without it reflowing. Undo deletes the check-in.

describe("Quick roster check-in", () => {
    let eventId;

    before(() => {
        // quick-create reuses an event with the same date and type, so use a date no other spec shares.
        const day = Math.floor(Date.now() / 1000) % 3000;
        const date = new Date(Date.UTC(2094, 0, 1 + day)).toISOString().slice(0, 10);
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/quick-create",
            { eventTypeId: 2, groupId: 1, date },
            200,
        ).then((response) => {
            expect(response.body.created, "event must be new").to.eq(true);
            eventId = response.body.eventId;
        });
    });

    after(() => {
        cy.cleanupEvents([eventId]);
    });

    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit(`/event/checkin/${eventId}`);
        cy.get("#rosterGrid", { timeout: 15000 }).should("not.have.class", "d-none");
    });

    it("keeps the row in place and highlights it on check-in", () => {
        cy.get("#notCheckedInList .roster-member").then(($rows) => {
            const total = $rows.length;
            const $first = $rows.first();
            const personId = $first.attr("data-person-id");

            cy.wrap($first).find('.roster-action-btn[data-action="checkin"]').click();

            cy.get(`#notCheckedInList .roster-member[data-person-id="${personId}"]`)
                .should("have.class", "bg-success-subtle")
                .and("have.length", 1);
            cy.get("#notCheckedInList .roster-member").should("have.length", total);
            cy.get("#notCheckedInList .roster-member").first().should("have.attr", "data-person-id", personId);
            cy.get(`#checkedInList .checked-in-member[data-person-id="${personId}"]`).should("exist");
            cy.get("#checkedInCount").should("have.text", "1");
        });
    });

    const waitingRow = () =>
        cy.get('#notCheckedInList [data-action="checkin"]').first().closest(".roster-member");

    it("undo removes the check-in and restores the row", () => {
        cy.get("#checkedInCount").invoke("text").then((before) => {
            const count = before.trim();
            waitingRow().then(($row) => {
                const personId = $row.attr("data-person-id");
                cy.wrap($row).find('[data-action="checkin"]').click();
                cy.get(`#notCheckedInList .roster-member[data-person-id="${personId}"]`).find('[data-action="undo"]').click();
                cy.get(`#notCheckedInList .roster-member[data-person-id="${personId}"]`).should("not.have.class", "bg-success-subtle");
                cy.get("#checkedInCount").should("have.text", count);
            });
            cy.reload();
            cy.get("#rosterGrid", { timeout: 15000 }).should("not.have.class", "d-none");
            cy.get("#checkedInCount").should("have.text", count);
        });
    });

    it("check out leaves the row in the list marked checked out", () => {
        cy.get("#checkedInCount").invoke("text").then((before) => {
            waitingRow().then(($row) => {
                const personId = $row.attr("data-person-id");
                cy.wrap($row).find('[data-action="checkin"]').click();
                cy.get(`#notCheckedInList .roster-member[data-person-id="${personId}"]`).find('[data-action="checkout"]').click();
                cy.get(`#notCheckedInList .roster-member[data-person-id="${personId}"]`)
                    .should("not.have.class", "bg-success-subtle")
                    .and("contain.text", "Checked out");
                cy.get("#checkedInCount").should("have.text", before.trim());
            });
        });
    });

    it("updates the People Checked In table without a reload", () => {
        waitingRow().then(($row) => {
            const personId = $row.attr("data-person-id");
            cy.wrap($row).find('[data-action="checkin"]').click();
            cy.get(`#checkedinTable tbody tr[data-person-id="${personId}"]`, { timeout: 10000 }).should("exist");
        });
    });
});
