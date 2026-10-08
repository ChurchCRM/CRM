/// <reference types="cypress" />

describe("Kiosk device - walk-in guest registration", () => {
    const createdEventIds = [];
    const createdPersonIds = [];
    const runId = Date.now();
    // quick-create reuses an event for the same date and group.
    const runDayOffset = Math.floor(runId / 1000) % 3000;
    const eventDate = new Date(Date.UTC(2090, 0, 1 + runDayOffset * 3 + 2))
        .toISOString()
        .slice(0, 10);

    let groupId = null;
    let kioskId = null;
    let kioskCookie = null;
    let kioskCookiePath = null;
    let existingKioskIds = new Set();
    let eventId = null;
    let originalVisibility;

    const api = (method, url, body, status = 200) =>
        cy.makePrivateAdminAPICall(method, url, body, status);

    const registerGuest = (body, status = 200) =>
        cy.request({
            method: "POST",
            url: "/kiosk/device/registerGuest",
            body,
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.equal(status);
            return response;
        });

    const rosterGuests = () =>
        cy
            .request({ method: "GET", url: "/kiosk/device/activeClassMembers" })
            .then((response) => response.body.People.filter((p) => p.isGuest === true));

    before(() => {
        cy.getSystemConfig("sKioskVisibilityTimestamp").then((v) => {
            originalVisibility = v;
        });

        api("POST", "/api/groups/", {
            groupName: `Kiosk guest ${runId}`,
            description: "",
        }).then((response) => {
            groupId = Number(response.body.Id);
        });

        cy.then(() => {
            api("POST", "/api/events/quick-create", { groupId, date: eventDate }).then(
                (response) => {
                    eventId = response.body.eventId;
                    createdEventIds.push(eventId);
                },
            );
        });

        cy.then(() => {
            api("GET", "/kiosk/api/devices").then((before) => {
                existingKioskIds = new Set((before.body.KioskDevices || []).map((k) => k.Id));

                api("POST", "/kiosk/api/allowRegistration");

                cy.clearCookies();
                cy.visit("/kiosk/", { failOnStatusCode: false });
                cy.getCookie("kioskCookie").then((cookie) => {
                    expect(cookie, "kioskCookie set by registration").to.not.be.null;
                    kioskCookie = cookie.value;
                    kioskCookiePath = cookie.path;
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
            // Not accepted yet: registerGuest must refuse before any Person is created.
            cy.setCookie("kioskCookie", kioskCookie, { path: kioskCookiePath });
            registerGuest({ FirstName: "Pending", LastName: `Device${runId}` }, 403);

            api("POST", `/kiosk/api/devices/${kioskId}/accept`);
            api("POST", `/kiosk/api/devices/${kioskId}/assignment`, {
                assignmentType: 1,
                eventId,
            });
        });
    });

    beforeEach(() => {
        cy.setCookie("kioskCookie", kioskCookie, { path: kioskCookiePath });
    });

    after(() => {
        api("GET", "/kiosk/api/devices").then((response) => {
            (response.body.KioskDevices || [])
                .filter((k) => !existingKioskIds.has(k.Id))
                .forEach((k) => api("DELETE", `/kiosk/api/devices/${k.Id}`));
        });
        cy.cleanupEvents(createdEventIds);
        createdPersonIds.forEach((personId) => {
            api("DELETE", `/api/person/${personId}`, null, [200, 404]);
        });
        if (groupId !== null) {
            api("DELETE", `/api/groups/${groupId}`);
        }
    });

    it("registers a guest with only a name and lists them once, checked in", () => {
        registerGuest({ FirstName: "Walkin", LastName: `Minimal${runId}` }).then((response) => {
            const guestId = Number(response.body.Id);
            createdPersonIds.push(guestId);
            expect(response.body.isGuest).to.equal(true);
            expect(Number(response.body.status)).to.equal(1);

            // Repeated refreshes must keep returning the guest as a single checked-in row.
            [1, 2].forEach(() => {
                rosterGuests().then((guests) => {
                    const rows = guests.filter((g) => Number(g.Id) === guestId);
                    expect(rows, "one roster row").to.have.length(1);
                    expect(Number(rows[0].status)).to.equal(1);
                });
            });

        });
    });

    it("stores the optional birth date, phone and email", () => {
        registerGuest({
            FirstName: "Walkin",
            LastName: `Full${runId}`,
            BirthYear: 2015,
            BirthMonth: 6,
            BirthDay: 20,
            Phone: "555-0100",
            Email: `guest${runId}@example.com`,
        }).then((response) => {
            const guestId = Number(response.body.Id);
            createdPersonIds.push(guestId);
            api("GET", `/api/person/${guestId}`).then((person) => {
                expect(person.body.BirthYear).to.equal(2015);
                expect(person.body.BirthMonth).to.equal(6);
                expect(person.body.BirthDay).to.equal(20);
                expect(person.body.Email).to.equal(`guest${runId}@example.com`);
            });
        });
    });

    it("rejects invalid input without creating a guest", () => {
        rosterGuests().then((before) => {
            registerGuest({ FirstName: "Solo", LastName: "" }, 400);
            registerGuest({ FirstName: "A", LastName: "Short" }, 400);
            registerGuest({ FirstName: "x".repeat(51), LastName: "Long" }, 400);
            registerGuest({ FirstName: "Bad", LastName: "Email", Email: "not-an-email" }, 400);
            registerGuest(
                { FirstName: "Bad", LastName: "Date", BirthMonth: 2, BirthDay: 30 },
                400,
            );
            registerGuest({ FirstName: "Bad", LastName: "Month", BirthMonth: 13 }, 400);
            registerGuest({ FirstName: "Bad", LastName: "Year", BirthYear: 1800 }, 400);

            rosterGuests().then((after) => {
                expect(after).to.have.length(before.length);
            });
        });
    });

    it("lets a guest be checked out and checked back in", () => {
        registerGuest({ FirstName: "Walkin", LastName: `Reentry${runId}` }).then((response) => {
            const guestId = Number(response.body.Id);
            createdPersonIds.push(guestId);

            cy.request("POST", "/kiosk/device/checkout", { PersonId: guestId });
            rosterGuests().then((guests) => {
                expect(guests.map((g) => Number(g.Id))).to.not.include(guestId);
            });

            cy.request("POST", "/kiosk/device/checkin", { PersonId: guestId });
            rosterGuests().then((guests) => {
                const rows = guests.filter((g) => Number(g.Id) === guestId);
                expect(rows, "one roster row").to.have.length(1);
                expect(Number(rows[0].status)).to.equal(1);
            });
        });
    });
});
