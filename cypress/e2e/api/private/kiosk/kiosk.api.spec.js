/// <reference types="cypress" />

/**
 * Kiosk API Tests
 * 
 * Tests for the kiosk management API endpoints at /kiosk/api/
 * These endpoints require ManageGroups role (or Admin) authentication.
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
                // visibleUntil is now an ISO 8601 string (DateTime::ATOM, e.g.
                // "2026-04-25T01:06:54-04:00") instead of the legacy PHP
                // {date, timezone_type, timezone} serialization. Validate by
                // parsing — Date.parse returns NaN on bad input.
                expect(response.body.visibleUntil).to.be.a("string");
                expect(Number.isNaN(Date.parse(response.body.visibleUntil))).to.equal(false);
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
            // tony.wade (standard session) has usr_ManageGroups=1 and now passes
            // the ManageGroupsRoleAuthMiddleware added in #9476 — use the
            // nofinance session (judith.matthews: ManageGroups=0, Finance=0, Admin=0)
            // to correctly exercise the non-ManageGroups denial path.
            cy.setupNoFinanceSession();
        });

        it("should deny GET /kiosk/api/devices for non-ManageGroups user", () => {
            cy.request({
                method: "GET",
                url: "/kiosk/api/devices",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 403, 302]);
            });
        });

        it("should deny POST /kiosk/api/allowRegistration for non-ManageGroups user", () => {
            cy.request({
                method: "POST",
                url: "/kiosk/api/allowRegistration",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 403, 302]);
            });
        });

        it("should deny POST /kiosk/api/devices/1/reload for non-ManageGroups user", () => {
            cy.request({
                method: "POST",
                url: "/kiosk/api/devices/1/reload",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 403, 302, 404]);
            });
        });

        it("should deny DELETE /kiosk/api/devices/1 for non-ManageGroups user", () => {
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


describe("Kiosk API - ManageGroups role access (non-admin)", () => {
    // Verifies that a user with ManageGroups=1 but Admin=0 can access
    // the kiosk API endpoints (issue #9476: Kiosk Manager should not require Admin).
    it("ManageGroups-only user can GET /kiosk/api/devices", () => {
        cy.makePrivateManageGroupsOnlyAPICall("GET", "/kiosk/api/devices", null, 200).then(
            (response) => {
                expect(response.body).to.have.property("KioskDevices");
                expect(response.body.KioskDevices).to.be.an("array");
            },
        );
    });

    it("ManageGroups-only user can POST /kiosk/api/allowRegistration", () => {
        cy.makePrivateManageGroupsOnlyAPICall("POST", "/kiosk/api/allowRegistration", null, 200).then(
            (response) => {
                expect(response.body).to.have.property("visibleUntil");
            },
        );
    });

    it("Non-ManageGroups user is denied GET /kiosk/api/devices", () => {
        // nofinance user has Finance=0, ManageGroups=0 — should be denied
        cy.makePrivateNoFinanceAPICall("GET", "/kiosk/api/devices", null, [401, 403]);
    });

    it("Finance-only user (non-ManageGroups) is denied GET /kiosk/api/devices", () => {
        cy.makePrivateFinanceOnlyAPICall("GET", "/kiosk/api/devices", null, [401, 403]);
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

    it("unaccepted kiosk is denied the guardian photo endpoint", () => {
        cy.setupAdminSession();
        cy.request({ method: "POST", url: "/kiosk/api/allowRegistration" });
        cy.clearCookies();
        cy.visit("/kiosk/", { failOnStatusCode: false });

        cy.request({
            method: "GET",
            url: "/kiosk/device/activeClassMember/1/family/2/photo",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.equal(403);
        });
    });

    it("request without a kiosk cookie is denied the guardian photo endpoint", () => {
        cy.clearCookies();
        cy.request({
            method: "GET",
            url: "/kiosk/device/activeClassMember/1/family/2/photo",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.be.oneOf([401, 403]);
        });
    });
});

// Uses seeded data (cypress/data/seed.sql):
//   group 1 "Angels class" roster includes children 8 (Herminia Hart) and 9 (Jean Hart)
//   Hart family: adults 6 (Constance) and 7 (Marion); minors 8-13
//   person 2 (Mathew Campbell) is an adult in an unrelated family
//   person 10 (Tom Hart) is a child in group 2, not on the Angels class roster
describe("Kiosk Device - Guardian Photo and checkedInBy", () => {
    const CHILD_ID = 8;
    const OTHER_CHILD_ID = 9;
    const GUARDIAN_ID = 6;
    const SECOND_GUARDIAN_ID = 7;
    const MINOR_SIBLING_ID = 13;
    const UNRELATED_ADULT_ID = 2;
    const NON_ROSTER_CHILD_ID = 10;

    let kioskCookie = null;
    let kioskId = null;
    let eventId = null;

    const photoUrl = (childId, memberId) =>
        `/kiosk/device/activeClassMember/${childId}/family/${memberId}/photo`;

    const asKiosk = () => {
        cy.clearCookies();
        cy.setCookie("kioskCookie", kioskCookie, { path: "/kiosk/" });
    };

    before(() => {
        cy.setupAdminSession();
        cy.request({ method: "GET", url: "/kiosk/api/devices" }).then((before) => {
            const existingIds = new Set((before.body.KioskDevices || []).map((k) => k.Id));

            cy.request({ method: "POST", url: "/kiosk/api/allowRegistration" });
            cy.clearCookies();
            cy.visit("/kiosk/", { failOnStatusCode: false });
            cy.getCookie("kioskCookie").then((cookie) => {
                expect(cookie, "kiosk cookie set on registration").to.not.be.null;
                kioskCookie = cookie.value;
            });

            cy.clearCookies();
            cy.setupAdminSession({ forceLogin: true });
            cy.request({ method: "GET", url: "/kiosk/api/devices" }).then((after) => {
                const device = (after.body.KioskDevices || []).find((k) => !existingIds.has(k.Id));
                expect(device, "registered kiosk").to.not.be.undefined;
                kioskId = device.Id;
            });
        });

        // Far-future date no other spec or run uses, so quick-create makes a fresh event.
        const runDayOffset = Math.floor(Date.now() / 1000) % 3000;
        const date = new Date(Date.UTC(2090, 0, 1 + runDayOffset * 3 + 2)).toISOString().slice(0, 10);
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/quick-create",
            { eventTypeId: 2, groupId: 1, date },
            200,
        ).then((response) => {
            eventId = response.body.eventId;
            expect(eventId).to.be.a("number");
        });

        cy.then(() => {
            cy.request({ method: "POST", url: `/kiosk/api/devices/${kioskId}/accept` });
            cy.request({
                method: "POST",
                url: `/kiosk/api/devices/${kioskId}/assignment`,
                body: { assignmentType: "1", eventId },
            });
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${eventId}/checkin-people`,
                { personIds: [CHILD_ID], checkedInById: GUARDIAN_ID },
                200,
            );
        });
    });

    after(() => {
        cy.setupAdminSession({ forceLogin: true });
        if (kioskId) {
            cy.request({ method: "DELETE", url: `/kiosk/api/devices/${kioskId}`, failOnStatusCode: false });
        }
        if (eventId) {
            cy.cleanupEvents([eventId]);
        }
    });

    describe("GET /kiosk/device/activeClassMember/{PersonId}/family/{MemberId}/photo", () => {
        it("serves the photo of an adult family member of a roster child", () => {
            asKiosk();
            [GUARDIAN_ID, SECOND_GUARDIAN_ID].forEach((memberId) => {
                cy.request({ method: "GET", url: photoUrl(CHILD_ID, memberId) }).then((response) => {
                    expect(response.status).to.equal(200);
                    expect(response.headers["content-type"]).to.match(/^image\//);
                });
            });
        });

        it("denies a minor family member", () => {
            asKiosk();
            cy.request({ method: "GET", url: photoUrl(CHILD_ID, MINOR_SIBLING_ID), failOnStatusCode: false })
                .its("status")
                .should("equal", 403);
        });

        it("denies the child's own photo", () => {
            asKiosk();
            cy.request({ method: "GET", url: photoUrl(CHILD_ID, CHILD_ID), failOnStatusCode: false })
                .its("status")
                .should("equal", 403);
        });

        it("denies an adult from an unrelated family", () => {
            asKiosk();
            cy.request({ method: "GET", url: photoUrl(CHILD_ID, UNRELATED_ADULT_ID), failOnStatusCode: false })
                .its("status")
                .should("equal", 403);
        });

        it("denies a child who is not on the active roster", () => {
            asKiosk();
            cy.request({ method: "GET", url: photoUrl(NON_ROSTER_CHILD_ID, GUARDIAN_ID), failOnStatusCode: false })
                .its("status")
                .should("equal", 403);
        });

        it("rejects invalid ids", () => {
            asKiosk();
            cy.request({ method: "GET", url: photoUrl(0, GUARDIAN_ID), failOnStatusCode: false })
                .its("status")
                .should("equal", 400);
            cy.request({ method: "GET", url: photoUrl(CHILD_ID, 0), failOnStatusCode: false })
                .its("status")
                .should("equal", 400);
        });
    });

    describe("GET /kiosk/device/activeClassMembers checkedInBy", () => {
        it("reports who checked in a checked-in child and null for others", () => {
            asKiosk();
            cy.request({ method: "GET", url: "/kiosk/device/activeClassMembers" }).then((response) => {
                expect(response.status).to.equal(200);
                const byId = Object.fromEntries(response.body.People.map((p) => [p.Id, p]));

                expect(byId[CHILD_ID].checkedInBy).to.include({
                    Id: GUARDIAN_ID,
                    FirstName: "Constance",
                    LastName: "Hart",
                });
                expect(byId[CHILD_ID].checkedInBy.hasPhoto).to.be.a("boolean");
                expect(byId[OTHER_CHILD_ID].checkedInBy).to.equal(null);
            });
        });

        it("clears checkedInBy after checkout", () => {
            cy.makePrivateAdminAPICall("POST", `/api/events/${eventId}/checkout`, { personId: CHILD_ID }, 200);
            asKiosk();
            cy.request({ method: "GET", url: "/kiosk/device/activeClassMembers" }).then((response) => {
                const child = response.body.People.find((p) => p.Id === CHILD_ID);
                expect(child.checkedInBy).to.equal(null);
            });
        });
    });
});
