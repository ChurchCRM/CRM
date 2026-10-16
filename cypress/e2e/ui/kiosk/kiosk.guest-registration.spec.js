/// <reference types="cypress" />

describe("Kiosk - walk-in guest registration in the browser", () => {
    const createdEventIds = [];
    const runId = Date.now();
    const guestLastName = `Visitor${runId}`;

    let groupId = null;
    let kioskId = null;
    let kioskCookie = null;
    let kioskCookiePath = null;
    let existingKioskIds = new Set();
    let originalVisibility;
    let guestId = null;
    let eventId = null;

    const api = (method, url, body, status = 200) =>
        cy.makePrivateAdminAPICall(method, url, body, status);

    before(() => {
        cy.getSystemConfig("sKioskVisibilityTimestamp").then((v) => {
            originalVisibility = v;
        });

        api("POST", "/api/groups/", { groupName: `Kiosk guest UI ${runId}`, description: "" }).then(
            (response) => {
                groupId = Number(response.body.Id);
            },
        );

        cy.then(() => {
            // Today's event, open all day: the kiosk only shows its roster while check-in is open.
            api("POST", "/api/events/quick-create", { groupId }).then((response) => {
                const id = response.body.eventId;
                createdEventIds.push(id);
                eventId = id;
                cy.wrap(id).as("eventId");
                api("GET", `/api/events/${id}`).then((event) => {
                    const day = String(event.body.Start).slice(0, 10);
                    api("POST", `/api/events/${id}/time`, {
                        startTime: `${day} 00:00:00`,
                        endTime: `${day} 23:59:59`,
                    });
                });
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

        cy.then(function () {
            api("POST", `/kiosk/api/devices/${kioskId}/accept`);
            api("POST", `/kiosk/api/devices/${kioskId}/assignment`, {
                assignmentType: 1,
                eventId: this.eventId,
            });
        });
    });

    after(() => {
        api("GET", "/kiosk/api/devices").then((response) => {
            (response.body.KioskDevices || [])
                .filter((k) => !existingKioskIds.has(k.Id))
                .forEach((k) => api("DELETE", `/kiosk/api/devices/${k.Id}`));
        });
        cy.cleanupEvents(createdEventIds);
        if (guestId !== null) {
            api("DELETE", `/api/person/${guestId}`, null, [200, 404]);
        }
        if (groupId !== null) {
            api("DELETE", `/api/groups/${groupId}`);
        }
    });

    it("offers no guest registration while check-in is not open", () => {
        api("GET", `/api/events/${eventId}`).then((event) => {
            const today = String(event.body.Start).slice(0, 10);
            const later = new Date(`${today}T12:00:00Z`);
            later.setUTCDate(later.getUTCDate() + 2);
            const day = later.toISOString().slice(0, 10);
            api("POST", `/api/events/${eventId}/time`, {
                startTime: `${day} 09:00:00`,
                endTime: `${day} 10:00:00`,
            });

            cy.clearCookies();
            cy.setCookie("kioskCookie", kioskCookie, { path: kioskCookiePath });
            cy.visit("/kiosk/");
            cy.contains("Check-in Opens Soon", { timeout: 15000 }).should("be.visible");
            cy.get("#registerGuestBtn").should("not.be.visible");

            api("POST", `/api/events/${eventId}/time`, {
                startTime: `${today} 00:00:00`,
                endTime: `${today} 23:59:59`,
            });
        });
    });

    it("registers a guest through the modal and lists them for staff review", () => {
        cy.clearCookies();
        cy.setCookie("kioskCookie", kioskCookie, { path: kioskCookiePath });
        cy.visit("/kiosk/");

        cy.getSystemConfig("sChurchName").then((name) => {
            if (name) {
                cy.get(".kiosk-header .kiosk-church-name", { timeout: 15000 }).should("contain", name);
            }
        });

        cy.get("#registerGuestBtn", { timeout: 15000 }).should("be.visible").click();
        cy.get("#guestRegistrationModal.show").should("be.visible");

        // Names alone are not enough: a phone number or email is required.
        cy.get("#guestRegisterSubmitBtn").click();
        cy.get("#guestFirstName").should("have.class", "is-invalid");
        cy.get("#guestLastName").should("have.class", "is-invalid");

        cy.get("#guestFirstName").type("Taylor");
        cy.get("#guestLastName").type(guestLastName);
        cy.get("#guestRegisterSubmitBtn").click();
        cy.get("#guestFormError").should("be.visible").and("contain", "phone number or email");
        cy.get("#guestRegistrationModal").should("have.class", "show");

        cy.get("#guestEmail").type("taylor.visitor@example.com");
        cy.get("#guestRegisterSubmitBtn").click();

        cy.get("#guestRegistrationModal").should("not.have.class", "show");
        cy.contains("#checkedInList .kiosk-member", guestLastName, { timeout: 10000 })
            .find(".kiosk-guest-badge")
            .should("be.visible");

        // The guest is read back from the event attendance after a refresh.
        cy.reload();
        cy.contains("#checkedInList .kiosk-member", guestLastName, { timeout: 15000 })
            .find(".kiosk-guest-badge")
            .should("exist");

        api("GET", "/api/persons/self-register").then((queue) => {
            const queued = queue.body.people.find((p) => p.LastName === guestLastName);
            expect(queued, "guest awaiting review").to.exist;
            guestId = Number(queued.Id);
            expect(Number(queued.ClsId)).to.equal(3);
        });

        // Staff see the same guest on the Self Registrations review page.
        cy.clearCookies();
        cy.setupAdminSession({ forceLogin: true });
        cy.visit("/people/self-register");
        cy.get("#selfRegistrations", { timeout: 15000 }).should("contain", guestLastName);
    });
});
