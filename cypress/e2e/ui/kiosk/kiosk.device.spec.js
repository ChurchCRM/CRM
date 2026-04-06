/// <reference types="cypress" />

/**
 * Kiosk Device UI Tests
 *
 * End-to-end flow: visit /kiosk when disabled, enable registration via admin,
 * register a new kiosk device, and verify the "Awaiting Acceptance" screen.
 *
 * Cleans up the created kiosk device in after() to leave the DB tidy.
 */

describe("Kiosk Device UI", () => {
    let createdKioskId = null;

    afterEach(() => {
        // Clean up any kiosk device created during the test
        if (createdKioskId) {
            cy.clearCookies();
            cy.setupAdminSession({ forceLogin: true });
            cy.request({
                method: "DELETE",
                url: `/kiosk/api/devices/${createdKioskId}`,
                failOnStatusCode: false,
            });
            createdKioskId = null;
        }
    });

    it("should show registration disabled when window is closed", () => {
        // Clear any existing kiosk cookie so we hit the no-cookie + window-closed path
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

    it("should register a new kiosk and show Awaiting Acceptance", () => {
        // Step 1: Enable kiosk registration via admin API
        cy.setupAdminSession();
        cy.request({
            method: "POST",
            url: "/kiosk/api/allowRegistration",
        }).then((regResponse) => {
            expect(regResponse.status).to.equal(200);
            expect(regResponse.body).to.have.property("visibleUntil");
        });

        // Snapshot existing kiosk IDs before registration
        cy.request({
            method: "GET",
            url: "/kiosk/api/devices",
        }).then((beforeResponse) => {
            const existingIds = new Set(
                (beforeResponse.body.KioskDevices || []).map((k) => k.Id),
            );

            // Step 2: Clear cookies and visit /kiosk/ as a new device
            cy.clearCookies();
            cy.visit("/kiosk/", { failOnStatusCode: false });

            // Step 3: The kiosk device page should show "Awaiting Acceptance"
            cy.get("#noEvent", { timeout: 15000 }).should("be.visible");
            cy.contains("Awaiting Acceptance", { timeout: 10000 }).should(
                "be.visible",
            );

            // Step 4: Verify the kiosk appears as pending in the admin device list
            cy.clearCookies();
            cy.setupAdminSession({ forceLogin: true });
            cy.request({
                method: "GET",
                url: "/kiosk/api/devices",
            }).then((afterResponse) => {
                const newDevice = (
                    afterResponse.body.KioskDevices || []
                ).find((k) => !existingIds.has(k.Id));
                expect(
                    newDevice,
                    "New kiosk device should appear in admin device list",
                ).to.not.be.undefined;
                expect(newDevice.Accepted).to.equal(false);
                createdKioskId = newDevice.Id;
            });
        });
    });

    it("should show pending kiosk in admin Kiosk Manager", () => {
        // Setup: create a kiosk device via API flow
        cy.setupAdminSession();
        cy.request({
            method: "POST",
            url: "/kiosk/api/allowRegistration",
        });

        cy.request({
            method: "GET",
            url: "/kiosk/api/devices",
        }).then((beforeResponse) => {
            const existingIds = new Set(
                (beforeResponse.body.KioskDevices || []).map((k) => k.Id),
            );

            // Register a new device
            cy.clearCookies();
            cy.request({
                method: "GET",
                url: "/kiosk/",
                failOnStatusCode: false,
            });

            // Re-login as admin and find the new device
            cy.clearCookies();
            cy.setupAdminSession({ forceLogin: true });
            cy.request({
                method: "GET",
                url: "/kiosk/api/devices",
            }).then((afterResponse) => {
                const newDevice = (
                    afterResponse.body.KioskDevices || []
                ).find((k) => !existingIds.has(k.Id));
                expect(newDevice).to.not.be.undefined;
                createdKioskId = newDevice.Id;

                // Visit admin page and verify the pending kiosk shows
                cy.visit("/kiosk/admin");
                cy.get("#KioskTable_wrapper", { timeout: 10000 }).should(
                    "exist",
                );
                cy.get("#KioskTable tbody tr", { timeout: 10000 })
                    .contains(newDevice.Name)
                    .should("be.visible");
                // Verify "No" badge in Accepted column
                cy.get("#KioskTable tbody tr")
                    .contains(newDevice.Name)
                    .parents("tr")
                    .find(".badge")
                    .contains("No")
                    .should("be.visible");
            });
        });
    });

    it("should not show login background image on kiosk device page", () => {
        // Enable registration and visit kiosk page
        cy.setupAdminSession();
        cy.request({
            method: "POST",
            url: "/kiosk/api/allowRegistration",
        });

        cy.request({
            method: "GET",
            url: "/kiosk/api/devices",
        }).then((beforeResponse) => {
            const existingIds = new Set(
                (beforeResponse.body.KioskDevices || []).map((k) => k.Id),
            );

            cy.clearCookies();
            cy.visit("/kiosk/", { failOnStatusCode: false });

            // Body should have page-auth but NOT page-login
            cy.get("body").should("have.class", "page-auth");
            cy.get("body").should("not.have.class", "page-login");

            // No ::before overlay should be covering content
            cy.get("#noEvent", { timeout: 15000 }).should("be.visible");

            // Cleanup: find and record new kiosk for afterEach
            cy.clearCookies();
            cy.setupAdminSession({ forceLogin: true });
            cy.request({
                method: "GET",
                url: "/kiosk/api/devices",
            }).then((afterResponse) => {
                const newDevice = (
                    afterResponse.body.KioskDevices || []
                ).find((k) => !existingIds.has(k.Id));
                if (newDevice) {
                    createdKioskId = newDevice.Id;
                }
            });
        });
    });
});
