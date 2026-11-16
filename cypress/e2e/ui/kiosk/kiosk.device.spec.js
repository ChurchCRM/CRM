/// <reference types="cypress" />

/**
 * Kiosk Device UI Tests
 *
 * End-to-end flow: visit /kiosk when disabled, enable registration via admin,
 * register a new kiosk device, and verify the "Awaiting Acceptance" screen.
 *
 * Cleans up the created kiosk device in afterEach() to leave the DB tidy.
 */

describe("Kiosk Device UI", () => {
    let createdKioskId = null;

    afterEach(() => {
        // Reset registration window so subsequent specs see it as closed
        cy.setupAdminSession();
        cy.request({
            method: "POST",
            url: "/admin/api/system/config/sKioskVisibilityTimestamp",
            body: { value: "2000-01-01 00:00:00" },
        });

        // Clean up any kiosk device created during the test
        if (createdKioskId) {
            cy.request({
                method: "DELETE",
                url: `/kiosk/api/devices/${createdKioskId}`,
                failOnStatusCode: false,
            });
            createdKioskId = null;
        }
    });

    // NOTE: the pure-API "registration disabled when window is closed" check
    // lives in cypress/e2e/api/private/kiosk/kiosk.api.spec.js.

    it("should register a new kiosk and show Awaiting Acceptance", () => {
        cy.setupAdminSession();

        // Snapshot existing kiosk IDs BEFORE opening the registration window
        cy.request({
            method: "GET",
            url: "/kiosk/api/devices",
        }).then((beforeResponse) => {
            const existingIds = new Set(
                (beforeResponse.body.KioskDevices || []).map((k) => k.Id),
            );

            // Step 1: Enable kiosk registration via admin API
            cy.request({
                method: "POST",
                url: "/kiosk/api/allowRegistration",
            }).then((regResponse) => {
                expect(regResponse.status).to.equal(200);
                expect(regResponse.body).to.have.property("visibleUntil");
            });

            // Step 2: Clear cookies and visit /kiosk/ as a new device
            cy.clearCookies();
            cy.visit("/kiosk/", { failOnStatusCode: false });

            // Step 3: The kiosk device page should show "Awaiting Acceptance"
            cy.get("#noEvent", { timeout: 15000 }).should("be.visible");
            cy.contains("Awaiting Acceptance", { timeout: 10000 }).should(
                "be.visible",
            );
            cy.get("#noEvent .kiosk-status-brand").should("be.visible");
            cy.get("#kioskChurchBrand").then(($brand) => {
                const logoPath = $brand.attr("data-church-logo");
                const churchName = $brand.attr("data-church-name") || "ChurchCRM";
                expect(logoPath, "configured or default church logo").to.be.a("string").and.not.be.empty;
                cy.get("#noEvent .kiosk-status-brand img").should("have.attr", "src", logoPath);
                cy.get("#noEvent .kiosk-status-brand").should("contain", churchName);
            });

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

    it("renders church branding on each kiosk status at responsive widths and RTL", () => {
        const viewports = [
            [1440, 900],
            [820, 1024],
            [390, 844],
        ];
        const logoCases = [
            ["Uploaded church logo", "/Images/church-logo.png"],
            ["Default ChurchCRM logo", "/Images/churchcrm-logo-ink-blue.svg"],
        ];

        cy.setupAdminSession();
        cy.request({ method: "GET", url: "/kiosk/api/devices" }).then((beforeResponse) => {
            const existingIds = new Set((beforeResponse.body.KioskDevices || []).map((k) => k.Id));
            cy.request({ method: "POST", url: "/kiosk/api/allowRegistration" });
            cy.clearCookies();
            cy.visit("/kiosk/", { failOnStatusCode: false });
            cy.get("#noEvent .kiosk-status-brand", { timeout: 15000 }).should("be.visible");
            cy.getCookie("kioskCookie").then((cookie) => {
                const kioskCookie = cookie.value;
                const kioskCookiePath = cookie.path;
                cy.clearCookies();
                cy.setupAdminSession({ forceLogin: true });
                cy.request({ method: "GET", url: "/kiosk/api/devices" }).then((afterResponse) => {
                    const device = (afterResponse.body.KioskDevices || []).find((k) => !existingIds.has(k.Id));
                    expect(device, "registered kiosk for branding checks").to.not.be.undefined;
                    createdKioskId = device.Id;
                    cy.clearCookies();
                    cy.setCookie("kioskCookie", kioskCookie, { path: kioskCookiePath });
                    cy.visit("/kiosk/", { failOnStatusCode: false });
                    cy.get("#noEvent .kiosk-status-brand", { timeout: 15000 }).should("be.visible");
                });
            });
        });

        const statuses = [
            ["Awaiting Acceptance", (kiosk) => kiosk.renderStatusCard(
                "pending", "fa-hourglass-half", "Awaiting Acceptance", "Branding Test Kiosk", null,
            )],
            ["Check-in Opens Soon", (kiosk, win) => kiosk.renderCountdown(
                win.moment().add(1, "hour"), "Branding Test Event", "Branding Test Kiosk",
            )],
            ["Event Has Ended", (kiosk) => kiosk.renderEventEnded("Branding Test Event")],
            ["No Group Members Found", (kiosk) => kiosk.renderNoMembersMessage()],
        ];

        logoCases.forEach(([logoLabel, logoPath]) => {
            viewports.forEach(([width, height]) => {
                ["ltr", "rtl"].forEach((direction) => {
                    cy.log(`${logoLabel}; ${width}x${height}; ${direction}`);
                    cy.viewport(width, height);
                    cy.get("#kioskChurchBrand")
                        .invoke("attr", "data-church-name", "Branding Test Church")
                        .invoke("attr", "data-church-logo", logoPath);
                    cy.document().then((doc) => doc.documentElement.setAttribute("dir", direction));
                    cy.get("html").should("have.attr", "dir", direction);
                    cy.get("#event").invoke("show");
                    cy.get(".kiosk-header .kiosk-church-logo")
                        .invoke("attr", "src", logoPath)
                        .should("be.visible");
                    cy.get(".kiosk-header .kiosk-church-name")
                        .invoke("text", "Branding Test Church")
                        .should("be.visible")
                        .and("contain", "Branding Test Church");
                    cy.get(".kiosk-header .kiosk-church").then(($brand) => {
                        const bounds = $brand[0].getBoundingClientRect();
                        expect(bounds.left, "header brand stays inside the viewport").to.be.at.least(0);
                        expect(bounds.right, "header brand stays inside the viewport").to.be.at.most(width);
                    });

                    statuses.forEach(([statusName, render]) => {
                        cy.window().then((win) => {
                            const html = render(win.CRM.kiosk, win);
                            return cy.document().then((doc) => {
                                doc.getElementById("noEvent").innerHTML = html;
                            });
                        });
                        cy.get("#noEvent .kiosk-status-brand").should("be.visible").and("contain", "Branding Test Church");
                        cy.get("#noEvent .kiosk-status-brand img")
                            .should("have.attr", "src")
                            .and("include", logoPath);
                        cy.get("#noEvent .kiosk-status-brand").then(($brand) => {
                            const bounds = $brand[0].getBoundingClientRect();
                            expect(bounds.left, "brand stays inside the viewport").to.be.at.least(0);
                            expect(bounds.right, "brand stays inside the viewport").to.be.at.most(width);
                        });
                        cy.get("#noEvent").should("contain", statusName);
                    });
                });
            });
        });
    });

    it("should show pending kiosk in admin Kiosk Manager", () => {
        cy.setupAdminSession();

        // Snapshot existing kiosk IDs BEFORE opening the registration window
        cy.request({
            method: "GET",
            url: "/kiosk/api/devices",
        }).then((beforeResponse) => {
            const existingIds = new Set(
                (beforeResponse.body.KioskDevices || []).map((k) => k.Id),
            );

            cy.request({
                method: "POST",
                url: "/kiosk/api/allowRegistration",
            });

            // Register a new device — use cy.visit() so the browser stores
            // the path-scoped kioskCookie correctly
            cy.clearCookies();
            cy.visit("/kiosk/", { failOnStatusCode: false });

            // Wait for the kiosk device page to render (confirms registration)
            cy.get("#noEvent", { timeout: 15000 }).should("exist");

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
                // Verify "Pending" status badge for unaccepted kiosk
                cy.get("#KioskTable tbody tr")
                    .contains(newDevice.Name)
                    .parents("tr")
                    .find(".badge")
                    .contains("Pending")
                    .should("be.visible");
            });
        });
    });

    it("should not show login background image on kiosk device page", () => {
        cy.setupAdminSession();

        // Snapshot existing kiosk IDs BEFORE opening the registration window
        cy.request({
            method: "GET",
            url: "/kiosk/api/devices",
        }).then((beforeResponse) => {
            const existingIds = new Set(
                (beforeResponse.body.KioskDevices || []).map((k) => k.Id),
            );

            cy.request({
                method: "POST",
                url: "/kiosk/api/allowRegistration",
            });

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
