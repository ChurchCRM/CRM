describe("Kiosk pickup notifications", () => {
    const childId = 8;
    const unknownPersonId = 2147483647;
    const kiosks = [];
    let eventId;
    let originalVisibility;

    const admin = (method, path, body) => cy.makePrivateAdminAPICall(method, path, body, 200);
    const listKiosks = () => admin("GET", "/kiosk/api/devices").its("body.KioskDevices");

    const registerKiosk = () => {
        listKiosks().then((before) => {
            const existingIds = new Set(before.map((device) => device.Id));
            cy.clearCookies();
            cy.request("/kiosk/device/heartbeat");
            cy.getCookie("kioskCookie").then((cookie) => {
                expect(cookie, "registered kiosk cookie").to.not.be.null;
                listKiosks().then((after) => {
                    const device = after.find((entry) => !existingIds.has(entry.Id));
                    expect(device, "new kiosk").to.exist;
                    kiosks.push({ id: device.Id, cookie });
                });
            });
        });
    };

    const useKiosk = ({ cookie }) => {
        cy.clearCookies();
        cy.setCookie("kioskCookie", cookie.value, { path: cookie.path });
    };

    const notify = (personId) =>
        cy.request({
            method: "POST",
            url: "/kiosk/device/triggerNotification",
            body: { PersonId: personId },
            failOnStatusCode: false,
        });

    before(() => {
        cy.getSystemConfig("sKioskVisibilityTimestamp").then((value) => {
            originalVisibility = value;
        });
        admin("POST", "/kiosk/api/allowRegistration");
        registerKiosk();
        registerKiosk();
        cy.then(() => cy.restoreSystemConfig("sKioskVisibilityTimestamp", originalVisibility));
    });

    after(() => {
        kiosks.forEach(({ id }) => admin("DELETE", `/kiosk/api/devices/${id}`));
        if (eventId !== undefined) {
            cy.cleanupEvents([eventId]);
        }
    });

    it("returns the same acceptance error for every person ID on a pending kiosk", () => {
        useKiosk(kiosks[0]);
        notify(childId).then((denied) => {
            expect(denied.status).to.equal(403);
            for (const personId of [unknownPersonId, 0]) {
                notify(personId).then((response) => {
                    expect(response.status).to.equal(denied.status);
                    expect(response.body).to.deep.equal(denied.body);
                });
            }
        });
    });

    it("blocks excess dispatches from one accepted kiosk while another can send", () => {
        const date = new Date(Date.UTC(2090, 0, 1 + (Math.floor(Date.now() / 1000) % 3000) * 3))
            .toISOString()
            .slice(0, 10);
        admin("POST", "/api/events/quick-create", { eventTypeId: 2, groupId: 1, date }).then(({ body }) => {
            eventId = body.eventId;
            kiosks.forEach(({ id }) => {
                admin("POST", `/kiosk/api/devices/${id}/accept`);
                admin("POST", `/kiosk/api/devices/${id}/assignment`, { assignmentType: 1, eventId });
            });
        });

        useKiosk(kiosks[0]);
        notify(unknownPersonId).its("status").should("equal", 404);
        Cypress._.times(10, () => notify(childId).its("status").should("equal", 200));
        notify(childId).then(({ status, headers }) => {
            expect(status).to.equal(429);
            expect(Number(headers["retry-after"])).to.be.within(1, 60);
        });

        useKiosk(kiosks[1]);
        notify(childId).its("status").should("equal", 200);
    });
});
