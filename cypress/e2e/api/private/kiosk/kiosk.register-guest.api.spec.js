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

    // Open all day on today's date plus dayOffset; the kiosk only takes guests while check-in is open.
    const baseDays = {};
    const setEventDay = (id, dayOffset) =>
        api("GET", `/api/events/${id}`).then((event) => {
            baseDays[id] ??= String(event.body.Start).slice(0, 10);
            const day = new Date(`${baseDays[id]}T12:00:00Z`);
            day.setUTCDate(day.getUTCDate() + dayOffset);
            const date = day.toISOString().slice(0, 10);
            return api("POST", `/api/events/${id}/time`, {
                startTime: `${date} 00:00:00`,
                endTime: `${date} 23:59:59`,
            });
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
            api("POST", "/api/events/quick-create", { groupId }).then((response) => {
                eventId = response.body.eventId;
                createdEventIds.push(eventId);
                setEventDay(eventId, 0);
            });
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

    it("registers a guest with a name and one contact and lists them once, checked in", () => {
        registerGuest({ FirstName: "Walkin", LastName: `Minimal${runId}`, Email: "walkin@example.com" }).then((response) => {
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

            api("GET", "/api/persons/self-register").then((queue) => {
                const queued = queue.body.people.find((p) => Number(p.Id) === guestId);
                expect(queued, "guest in self-register review queue").to.exist;
                expect(queued.NeedsReview).to.equal(true);
            });
        });
    });

    it("notes the kiosk registration on the timeline, queues the guest for review, and clears on approval", () => {
        registerGuest({ FirstName: "Walkin", LastName: `Review${runId}`, Email: "walkin@example.com" }).then((response) => {
            const guestId = Number(response.body.Id);
            createdPersonIds.push(guestId);

            api("GET", `/api/timeline/person/${guestId}`).then((timeline) => {
                const texts = timeline.body.timeline.map((item) => item.text);
                expect(texts.some((t) => /Registered as a walk-in guest at the kiosk during event: /.test(t))).to.equal(true);
                expect(texts.some((t) => /Checked in to event: /.test(t))).to.equal(true);
            });

            api("GET", "/api/persons/self-register").then((queue) => {
                expect(queue.body.people.map((p) => Number(p.Id))).to.include(guestId);
            });

            api("POST", `/api/person/${guestId}/approve-review`);

            api("GET", "/api/persons/self-register").then((queue) => {
                expect(queue.body.people.map((p) => Number(p.Id))).to.not.include(guestId);
            });
            rosterGuests().then((guests) => {
                expect(guests.map((g) => Number(g.Id))).to.include(guestId);
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

    it("classifies guests from the kiosk guest classification setting, defaulting to Guest", () => {
        const classificationOf = (guestId) =>
            api("GET", `/api/person/${guestId}`).then((person) => Number(person.body.ClsId));
        const setClassification = (value) =>
            api("POST", "/admin/api/system/config/iKioskGuestClassification", { value });

        cy.getSystemConfig("iKioskGuestClassification").then((original) => {
            expect(original).to.equal("3");

            registerGuest({ FirstName: "Walkin", LastName: `ClsDefault${runId}`, Email: "walkin@example.com" }).then((response) => {
                createdPersonIds.push(Number(response.body.Id));
                classificationOf(Number(response.body.Id)).should("equal", 3);
            });

            setClassification("2");
            registerGuest({ FirstName: "Walkin", LastName: `ClsChanged${runId}`, Email: "walkin@example.com" }).then((response) => {
                createdPersonIds.push(Number(response.body.Id));
                classificationOf(Number(response.body.Id)).should("equal", 2);
            });

            setClassification("999");
            registerGuest({ FirstName: "Walkin", LastName: `ClsMissing${runId}`, Email: "walkin@example.com" }).then((response) => {
                createdPersonIds.push(Number(response.body.Id));
                classificationOf(Number(response.body.Id)).should("not.equal", 999);
            });

            cy.restoreSystemConfig("iKioskGuestClassification", original);
        });
    });

    it("refuses guests unless the event has a group and check-in is open", () => {
        const guest = { FirstName: "Early", LastName: `Bird${runId}`, Email: "walkin@example.com" };
        const assignEvent = (id) =>
            api("POST", `/kiosk/api/devices/${kioskId}/assignment`, { assignmentType: 1, eventId: id });

        rosterGuests().then((before) => {
            // Not open yet: starts two days from now.
            setEventDay(eventId, 2);
            registerGuest(guest, 409).its("body.message").should("contain", "not opened");

            // Already over: ended yesterday.
            setEventDay(eventId, -1);
            registerGuest(guest, 409).its("body.message").should("contain", "ended");

            // No group: an event made from a type alone has no class to check guests into.
            api("POST", "/api/events/quick-create", { eventTypeId: 1, date: eventDate }).then((response) => {
                const noGroupEventId = response.body.eventId;
                createdEventIds.push(noGroupEventId);
                setEventDay(noGroupEventId, 0);
                assignEvent(noGroupEventId);
                registerGuest(guest, 409).its("body.message").should("contain", "no group");
                assignEvent(eventId);
            });

            setEventDay(eventId, 0);
            rosterGuests().then((after) => {
                expect(after).to.have.length(before.length);
            });
        });
    });

    it("rejects invalid input without creating a guest", () => {
        rosterGuests().then((before) => {
            registerGuest({ FirstName: "Solo", LastName: "" }, 400);
            registerGuest({ FirstName: "No", LastName: "Contact" }, 400);
            registerGuest({ FirstName: "A", LastName: "Short" }, 400);
            registerGuest({ FirstName: "x".repeat(51), LastName: "Long" }, 400);
            registerGuest({ FirstName: "Bad", LastName: "Email", Email: "not-an-email" }, 400);
            registerGuest(
                { FirstName: "Bad", LastName: "Date", Email: "walkin@example.com", BirthMonth: 2, BirthDay: 30 },
                400,
            );
            registerGuest({ FirstName: "Bad", LastName: "Month", Email: "walkin@example.com", BirthMonth: 13 }, 400);
            registerGuest({ FirstName: "Bad", LastName: "Year", Email: "walkin@example.com", BirthYear: 1800 }, 400);

            rosterGuests().then((after) => {
                expect(after).to.have.length(before.length);
            });
        });
    });

    it("lets a guest be checked out and checked back in", () => {
        registerGuest({ FirstName: "Walkin", LastName: `Reentry${runId}`, Email: "walkin@example.com" }).then((response) => {
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
