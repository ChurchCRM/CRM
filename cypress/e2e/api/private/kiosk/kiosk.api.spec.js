/// <reference types="cypress" />

/**
 * Kiosk API Tests
 * 
 * Tests for the kiosk management API endpoints at /kiosk/api/
 * These endpoints require admin authentication.
 */

describe("Kiosk API - Admin Operations", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /kiosk/api/devices", () => {
        it("should return kiosk devices list with correct structure", () => {
            cy.request({
                method: "GET",
                url: "/kiosk/api/devices",
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property("KioskDevices");
                expect(response.body.KioskDevices).to.be.an("array");
                
                // If there are kiosks, verify structure
                if (response.body.KioskDevices.length > 0) {
                    const kiosk = response.body.KioskDevices[0];
                    expect(kiosk).to.have.property("Id");
                    expect(kiosk).to.have.property("Name");
                    expect(kiosk).to.have.property("Accepted");
                }
            });
        });
    });

    describe("POST /kiosk/api/allowRegistration", () => {
        it("should enable kiosk registration and return visibility window", () => {
            cy.request({
                method: "POST",
                url: "/kiosk/api/allowRegistration",
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property("visibleUntil");
                expect(response.body.visibleUntil).to.have.property("date");
            });
        });
    });

    describe("POST /kiosk/api/devices/{id}/reload", () => {
        it("should return 404 for non-existent kiosk", () => {
            cy.request({
                method: "POST",
                url: "/kiosk/api/devices/99999/reload",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.equal(404);
                expect(response.body.success).to.equal(false);
            });
        });

        it("should successfully reload existing kiosk", () => {
            cy.request({
                method: "GET",
                url: "/kiosk/api/devices",
            }).then((response) => {
                if (response.body.KioskDevices && response.body.KioskDevices.length > 0) {
                    const kioskId = response.body.KioskDevices[0].Id;
                    
                    cy.request({
                        method: "POST",
                        url: `/kiosk/api/devices/${kioskId}/reload`,
                    }).then((reloadResponse) => {
                        expect(reloadResponse.status).to.equal(200);
                        expect(reloadResponse.body.success).to.equal(true);
                    });
                } else {
                    cy.log("No kiosks available for testing");
                }
            });
        });
    });

    describe("POST /kiosk/api/devices/{id}/identify", () => {
        it("should return 404 for non-existent kiosk", () => {
            cy.request({
                method: "POST",
                url: "/kiosk/api/devices/99999/identify",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.equal(404);
                expect(response.body.success).to.equal(false);
            });
        });

        it("should successfully identify existing kiosk", () => {
            cy.request({
                method: "GET",
                url: "/kiosk/api/devices",
            }).then((response) => {
                if (response.body.KioskDevices && response.body.KioskDevices.length > 0) {
                    const kioskId = response.body.KioskDevices[0].Id;
                    
                    cy.request({
                        method: "POST",
                        url: `/kiosk/api/devices/${kioskId}/identify`,
                    }).then((identifyResponse) => {
                        expect(identifyResponse.status).to.equal(200);
                        expect(identifyResponse.body.success).to.equal(true);
                    });
                } else {
                    cy.log("No kiosks available for testing");
                }
            });
        });
    });

    describe("POST /kiosk/api/devices/{id}/accept", () => {
        it("should return 404 for non-existent kiosk", () => {
            cy.request({
                method: "POST",
                url: "/kiosk/api/devices/99999/accept",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.equal(404);
                expect(response.body.success).to.equal(false);
            });
        });
    });

    describe("POST /kiosk/api/devices/{id}/assignment", () => {
        it("should return 404 for non-existent kiosk", () => {
            cy.request({
                method: "POST",
                url: "/kiosk/api/devices/99999/assignment",
                body: {
                    assignmentType: "1",
                    eventId: "1"
                },
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.equal(404);
                expect(response.body.success).to.equal(false);
            });
        });
    });

    describe("DELETE /kiosk/api/devices/{id}", () => {
        it("should return 404 for non-existent kiosk", () => {
            cy.request({
                method: "DELETE",
                url: "/kiosk/api/devices/99999",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.equal(404);
                expect(response.body.success).to.equal(false);
            });
        });
    });
});

/**
 * Regression test for: KioskDeviceQuery missing `use` import caused
 * GET /kiosk/api/devices to silently return an empty array even when
 * kiosk devices existed in the database.
 *
 * This test registers a real kiosk device via the device registration flow
 * and then verifies it appears in the admin device list, which would have
 * been empty before the fix.
 */
describe("Kiosk Device Registration and Visibility (Regression)", () => {
    let createdKioskId = null;

    before(() => {
        // Snapshot existing device IDs so we can identify the newly created one
        cy.setupAdminSession();
        cy.request({ method: "GET", url: "/kiosk/api/devices" }).then((before) => {
            const existingIds = new Set((before.body.KioskDevices || []).map((k) => k.Id));

            // Open the registration window
            cy.request({ method: "POST", url: "/kiosk/api/allowRegistration" }).then((r) => {
                expect(r.status).to.equal(200);
            });

            // Simulate a new kiosk device connecting: clear admin session cookies
            // so the kiosk app treats this as an unauthenticated device request.
            // With the registration window open and no kioskCookie, index.php
            // creates a new KioskDevice record and sets the cookie.
            cy.clearCookies();
            cy.request({ method: "GET", url: "/kiosk/device/heartbeat", failOnStatusCode: false });

            // Re-establish admin session and identify the newly created device
            cy.setupAdminSession({ forceLogin: true });
            cy.request({ method: "GET", url: "/kiosk/api/devices" }).then((after) => {
                const newDevice = (after.body.KioskDevices || []).find((k) => !existingIds.has(k.Id));
                expect(newDevice, "A new kiosk device should have been created during the registration window").to.not.be.undefined;
                createdKioskId = newDevice.Id;
            });
        });
    });

    after(() => {
        if (createdKioskId) {
            cy.setupAdminSession({ forceLogin: true });
            cy.request({
                method: "DELETE",
                url: `/kiosk/api/devices/${createdKioskId}`,
                failOnStatusCode: false,
            });
        }
    });

    it("GET /kiosk/api/devices returns the registered kiosk device (not an empty list)", () => {
        expect(createdKioskId, "createdKioskId must be set by before() hook").to.not.be.null;
        cy.setupAdminSession();
        cy.request({
            method: "GET",
            url: "/kiosk/api/devices",
        }).then((response) => {
            expect(response.status).to.equal(200);
            expect(response.body).to.have.property("KioskDevices");
            expect(response.body.KioskDevices).to.be.an("array");

            // The specific device created in before() must be present
            const ids = (response.body.KioskDevices || []).map((k) => k.Id);
            expect(ids).to.include(createdKioskId);
        });
    });

    it("registered kiosk has the expected properties in the device list", () => {
        expect(createdKioskId, "createdKioskId must be set by before() hook").to.not.be.null;
        cy.setupAdminSession();
        cy.request({
            method: "GET",
            url: "/kiosk/api/devices",
        }).then((response) => {
            const kiosk = (response.body.KioskDevices || []).find((k) => k.Id === createdKioskId);
            expect(kiosk).to.not.be.undefined;
            expect(kiosk).to.have.property("Id", createdKioskId);
            expect(kiosk).to.have.property("Name").and.to.be.a("string");
            expect(kiosk).to.have.property("Accepted");
            expect(kiosk).to.have.property("LastHeartbeat");
        });
    });
});

describe("Kiosk Registration Window - Disabled State", () => {
    beforeEach(() => {
        // Explicitly close the registration window so the test doesn't depend
        // on ambient DB state from a previous spec.
        cy.setupAdminSession();
        cy.request({
            method: "POST",
            url: "/admin/api/system/config/sKioskVisibilityTimestamp",
            body: { value: "2000-01-01 00:00:00" },
        });
    });

    it("GET /kiosk/ returns 401 + 'Kiosk Registration Disabled' when window is closed and no cookie is set", () => {
        // Clear any existing kiosk/admin cookies so we hit the
        // no-cookie + window-closed path
        cy.clearCookies();
        cy.request({
            method: "GET",
            url: "/kiosk/",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.equal(401);
            expect(response.body).to.contain("Kiosk Registration Disabled");
        });
    });
});

describe("Kiosk API - Access Control", () => {
    describe("Standard User Access", () => {
        beforeEach(() => {
            cy.setupStandardSession();
        });

        it("should deny GET /kiosk/api/devices for non-admin", () => {
            cy.request({
                method: "GET",
                url: "/kiosk/api/devices",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 403, 302]);
            });
        });

        it("should deny POST /kiosk/api/allowRegistration for non-admin", () => {
            cy.request({
                method: "POST",
                url: "/kiosk/api/allowRegistration",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 403, 302]);
            });
        });

        it("should deny POST /kiosk/api/devices/1/reload for non-admin", () => {
            cy.request({
                method: "POST",
                url: "/kiosk/api/devices/1/reload",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 403, 302, 404]);
            });
        });

        it("should deny DELETE /kiosk/api/devices/1 for non-admin", () => {
            cy.request({
                method: "DELETE",
                url: "/kiosk/api/devices/1",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 403, 302, 404]);
            });
        });
    });

    describe("Unauthenticated Access", () => {
        it("should deny GET /kiosk/api/devices for unauthenticated users", () => {
            cy.clearCookies();
            cy.request({
                method: "GET",
                url: "/kiosk/api/devices",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 302]);
            });
        });

        it("should deny POST /kiosk/api/allowRegistration for unauthenticated users", () => {
            cy.clearCookies();
            cy.request({
                method: "POST",
                url: "/kiosk/api/allowRegistration",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 302]);
            });
        });
    });
});

describe("Kiosk Device Endpoint - Acceptance Enforcement", () => {
    let createdKioskId = null;

    before(() => {
        // Register a new unaccepted kiosk device via the proper flow
        cy.setupAdminSession();

        // Snapshot existing IDs
        cy.request({ method: "GET", url: "/kiosk/api/devices" }).then((before) => {
            const existingIds = new Set((before.body.KioskDevices || []).map((k) => k.Id));

            // Open registration window
            cy.request({ method: "POST", url: "/kiosk/api/allowRegistration" });

            // Register as a new device — cy.visit sets the cookie properly
            cy.clearCookies();
            cy.visit("/kiosk/", { failOnStatusCode: false });

            // Re-login as admin to find the new device
            cy.clearCookies();
            cy.setupAdminSession({ forceLogin: true });
            cy.request({ method: "GET", url: "/kiosk/api/devices" }).then((after) => {
                const newDevice = (after.body.KioskDevices || []).find((k) => !existingIds.has(k.Id));
                if (newDevice) {
                    createdKioskId = newDevice.Id;
                }
            });
        });
    });

    after(() => {
        if (createdKioskId) {
            cy.setupAdminSession({ forceLogin: true });
            cy.request({
                method: "DELETE",
                url: `/kiosk/api/devices/${createdKioskId}`,
                failOnStatusCode: false,
            });
        }
    });

    it("newly registered kiosk should not be accepted", () => {
        expect(createdKioskId, "kiosk was created").to.not.be.null;
        cy.setupAdminSession();
        cy.request({ method: "GET", url: "/kiosk/api/devices" }).then((response) => {
            const kiosk = (response.body.KioskDevices || []).find((k) => k.Id === createdKioskId);
            expect(kiosk).to.not.be.undefined;
            expect(kiosk.Accepted).to.equal(false);
        });
    });

    it("unaccepted kiosk device endpoints should deny access", () => {
        // Use the kiosk cookie by visiting the kiosk page first, then
        // verify device endpoints return 401/403 (not 200/500)
        cy.setupAdminSession();
        cy.request({ method: "POST", url: "/kiosk/api/allowRegistration" });
        cy.clearCookies();
        cy.visit("/kiosk/", { failOnStatusCode: false });

        // Now we have a kioskCookie — try to hit checkin endpoint
        cy.request({
            method: "POST",
            url: "/kiosk/device/checkin",
            body: { PersonId: 1 },
            failOnStatusCode: false,
        }).then((response) => {
            // Should be 403 (not accepted) rather than 200 (allowed) or 500 (crash)
            expect(response.status).to.not.equal(200);
            expect(response.status).to.not.equal(500);
        });
    });
});
