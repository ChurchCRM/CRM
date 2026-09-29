/// <reference types="cypress" />

// Records are changed through the page's own session, the way a second tab would,
// because an API-key request in the middle of a test can drop the browser session.

const createdEventIds = [];
const createdGroupIds = [];

function createGroupEvent(groupName, memberIds, checkedInIds) {
    return cy.makePrivateAdminAPICall("POST", "/api/groups/", { groupName }).then((groupResp) => {
        const groupId = groupResp.body.Id;
        createdGroupIds.push(groupId);
        memberIds.forEach((personId) => {
            cy.makePrivateAdminAPICall("POST", `/api/groups/${groupId}/addperson/${personId}`, {});
        });
        return cy.makePrivateAdminAPICall("POST", "/api/events/quick-create", { groupId }).then((eventResp) => {
            expect(eventResp.body.created).to.eq(true);
            const eventId = eventResp.body.eventId;
            createdEventIds.push(eventId);
            checkedInIds.forEach((personId) => {
                cy.makePrivateAdminAPICall("POST", `/api/events/${eventId}/checkin`, { personId });
            });
            return cy.wrap(eventId);
        });
    });
}

function fromPage(method, path, body) {
    cy.window().then((win) =>
        win
            .fetch(`${win.CRM.root}/api/${path}`, {
                method,
                headers: { "Content-Type": "application/json" },
                body: body ? JSON.stringify(body) : undefined,
            })
            .then((res) => expect(res.status, `${method} ${path}`).to.eq(200)),
    );
}

function expectServerReason(alias) {
    cy.wait(alias).then(({ response }) => {
        expect(response.statusCode).to.be.within(400, 599);
        expect(response.body.message).to.be.a("string").and.not.be.empty;
        cy.waitForNotification(response.body.message);
    });
}

describe("Event actions show the server's reason (#10131)", () => {
    let checkedInEventId;
    let rosterEventId;
    let saveEventId;

    before(() => {
        createGroupEvent(`Reason Delete ${Date.now()}`, [2], [2]).then((eventId) => {
            checkedInEventId = eventId;
        });
        createGroupEvent(`Reason Roster ${Date.now()}`, [2, 3], [2]).then((eventId) => {
            rosterEventId = eventId;
        });
        createGroupEvent(`Reason Save ${Date.now()}`, [], []).then((eventId) => {
            saveEventId = eventId;
        });
    });

    after(() => {
        cy.cleanupEvents(createdEventIds);
        createdGroupIds.forEach((groupId) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/groups/${groupId}`, null, [200, 404]);
        });
    });

    beforeEach(() => cy.setupAdminSession());

    it("Event editor: Delete an event with someone checked in", () => {
        cy.intercept("DELETE", `**/api/events/${checkedInEventId}`).as("deleteEvent");
        cy.visit(`/event/editor/${checkedInEventId}`);
        cy.get("#event-editor-delete").click();
        cy.get(".bootbox .btn-danger").click();
        expectServerReason("@deleteEvent");
    });

    it("Calendar: Delete the same event from its modal", () => {
        cy.intercept("DELETE", `**/api/events/${checkedInEventId}`).as("deleteEvent");
        cy.visit("/event/calendars");
        cy.window().then((win) => win.showEventForm({ id: checkedInEventId }));
        cy.get("#eventDeleteBtn").click();
        cy.get(".bootbox .btn-danger").click();
        expectServerReason("@deleteEvent");
    });

    it("Event editor: Save an event deleted elsewhere", () => {
        cy.intercept("POST", `**/api/events/${saveEventId}`).as("saveEvent");
        cy.visit(`/event/editor/${saveEventId}`);
        cy.get("#event-editor-save").should("be.visible");
        fromPage("DELETE", `events/${saveEventId}`);
        cy.get("#event-editor-save").click();
        expectServerReason("@saveEvent");
    });

    it("Check-in: a roster Check In on an event deactivated elsewhere", () => {
        cy.intercept("POST", `**/api/events/${rosterEventId}/checkin`).as("rosterCheckin");
        cy.visit(`/event/checkin/${rosterEventId}`);
        cy.get('.roster-action-btn[data-action="checkin"][data-person-id="3"]').should("be.visible");
        fromPage("POST", `events/${rosterEventId}/status`, { active: false });
        cy.get('.roster-action-btn[data-action="checkin"][data-person-id="3"]').click();
        expectServerReason("@rosterCheckin");
        fromPage("POST", `events/${rosterEventId}/status`, { active: true });
    });

    it("Check-in: Check In All on an event deactivated elsewhere", () => {
        cy.intercept("POST", `**/api/events/${rosterEventId}/checkin-all`).as("checkinAll");
        cy.visit(`/event/checkin/${rosterEventId}`);
        cy.get('.roster-action-btn[data-action="checkin"]').should("be.visible");
        fromPage("POST", `events/${rosterEventId}/status`, { active: false });
        cy.get("#checkinAllBtn").click();
        expectServerReason("@checkinAll");
    });

    it("Check-in: delete an attendance record deleted elsewhere", () => {
        cy.visit(`/event/checkin/${rosterEventId}`);
        cy.get('.delete-attendance-btn[data-person-id="2"]').should("exist");
        fromPage("DELETE", `events/${rosterEventId}/attendance/2`);
        cy.intercept("DELETE", `**/api/events/${rosterEventId}/attendance/2`).as("deleteAttendance");
        cy.get('.delete-attendance-btn[data-person-id="2"]')
            .closest(".dropdown")
            .find('[data-bs-toggle="dropdown"]')
            .click();
        cy.get('.delete-attendance-btn[data-person-id="2"]').click();
        cy.get(".bootbox .btn-danger").click();
        expectServerReason("@deleteAttendance");
    });
});
