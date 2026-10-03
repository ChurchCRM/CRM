/// <reference types="cypress" />

// #10159: roster status must come from the assigned event, not any past attendance row.
describe("Kiosk device roster - check-in status is scoped to the assigned event", () => {
    const createdEventIds = [];
    const runId = Date.now();
    // quick-create reuses an event for the same date and group.
    const runDayOffset = Math.floor(runId / 1000) % 3000;
    const eventDate = (slot) => {
        const d = new Date(Date.UTC(2090, 0, 1 + runDayOffset * 3 + slot));
        return d.toISOString().slice(0, 10);
    };

    let groupId = null;
    let kioskId = null;
    let kioskCookie = null;
    let existingKioskIds = new Set();
    let currentEventId = null;
    let pastEventId = null;
    let checkedInPersonId = null;
    let staleCheckinPersonId = null;
    let originalVisibility;

    const api = (method, url, body, status = 200) =>
        cy.makePrivateAdminAPICall(method, url, body, status);

    before(() => {
        cy.getSystemConfig("sKioskVisibilityTimestamp").then((v) => {
            originalVisibility = v;
        });

        api("GET", "/api/persons/latest").then((response) => {
            const people = response.body.people;
            expect(people.length, "seed has at least two people").to.be.at.least(2);
            checkedInPersonId = Number(people[0].PersonId);
            staleCheckinPersonId = Number(people[1].PersonId);
        });

        api("POST", "/api/groups/", {
            groupName: `Kiosk roster status ${runId}`,
            description: "",
        }).then((response) => {
            groupId = Number(response.body.Id);
        });

        cy.then(() => {
            api("POST", `/api/groups/${groupId}/addperson/${checkedInPersonId}`, {});
            api("POST", `/api/groups/${groupId}/addperson/${staleCheckinPersonId}`, {});
        });

        // Past event gets the lower id; the current event's attendance row is
        // written first. Row order decided which status the old join returned.
        cy.then(() => {
            api("POST", "/api/events/quick-create", { groupId, date: eventDate(0) }).then(
                (response) => {
                    pastEventId = response.body.eventId;
                    createdEventIds.push(pastEventId);
                },
            );
        });

        cy.then(() => {
            api("POST", "/api/events/quick-create", { groupId, date: eventDate(1) }).then(
                (response) => {
                    currentEventId = response.body.eventId;
                    createdEventIds.push(currentEventId);
                },
            );
        });

        cy.then(() => {
            api("POST", `/api/events/${currentEventId}/checkin`, {
                personId: checkedInPersonId,
            });
            api("POST", `/api/events/${pastEventId}/checkin`, { personId: checkedInPersonId });
            api("POST", `/api/events/${pastEventId}/checkout`, { personId: checkedInPersonId });
            api("POST", `/api/events/${pastEventId}/checkin`, {
                personId: staleCheckinPersonId,
            });
        });

        cy.then(() => {
            api("GET", "/kiosk/api/devices").then((before) => {
                existingKioskIds = new Set((before.body.KioskDevices || []).map((k) => k.Id));

                api("POST", "/kiosk/api/allowRegistration");

                // Visiting as an unauthenticated browser is what registers a
                // device and stores its path-scoped kioskCookie in the jar.
                cy.clearCookies();
                cy.visit("/kiosk/", { failOnStatusCode: false });
                // Cypress clears cookies between tests, so keep the value and
                // put it back in beforeEach().
                cy.getCookie("kioskCookie").then((cookie) => {
                    expect(cookie, "kioskCookie set by registration").to.not.be.null;
                    kioskCookie = cookie.value;
                });
                cy.restoreSystemConfig("sKioskVisibilityTimestamp", originalVisibility);

                api("GET", "/kiosk/api/devices").then((after) => {
                    const device = (after.body.KioskDevices || []).find(
                        (k) => !existingKioskIds.has(k.Id),
                    );
                    expect(device, "kiosk registered").to.not.be.undefined;
                    kioskId = device.Id;
                });
            });
        });

        cy.then(() => {
            api("POST", `/kiosk/api/devices/${kioskId}/accept`);
            api("POST", `/kiosk/api/devices/${kioskId}/assignment`, {
                assignmentType: 1,
                eventId: currentEventId,
            });
        });
    });

    beforeEach(() => {
        cy.setCookie("kioskCookie", kioskCookie, { path: "/kiosk/" });
    });

    after(() => {
        api("GET", "/kiosk/api/devices").then((response) => {
            (response.body.KioskDevices || [])
                .filter((k) => !existingKioskIds.has(k.Id))
                .forEach((k) => api("DELETE", `/kiosk/api/devices/${k.Id}`));
        });
        cy.cleanupEvents(createdEventIds);
        if (groupId !== null) {
            api("DELETE", `/api/groups/${groupId}`);
        }
    });

    const rosterEntries = (personId) =>
        cy
            .request({ method: "GET", url: "/kiosk/device/activeClassMembers" })
            .then((response) => {
                expect(response.status).to.equal(200);
                return response.body.People.filter((p) => Number(p.Id) === personId);
            });

    it("lists a person once and as checked in when they are checked in to the assigned event", () => {
        rosterEntries(checkedInPersonId).then((entries) => {
            expect(entries, "one roster row").to.have.length(1);
            expect(Number(entries[0].status)).to.equal(1);
        });
    });

    it("lists a person checked in to an earlier event only as not checked in", () => {
        rosterEntries(staleCheckinPersonId).then((entries) => {
            expect(entries, "one roster row").to.have.length(1);
            expect(Number(entries[0].status)).to.equal(0);
        });
    });

    it("counts only people checked in to the assigned event as checked in", () => {
        cy.request({ method: "GET", url: "/kiosk/device/activeClassMembers" }).then((response) => {
            const members = response.body.People.filter((p) =>
                [checkedInPersonId, staleCheckinPersonId].includes(Number(p.Id)),
            );
            expect(members).to.have.length(2);
            expect(members.filter((p) => Number(p.status) === 1)).to.have.length(1);
        });
    });
});
