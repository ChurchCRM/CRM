/// <reference types="cypress" />

describe("Kiosk Manager", () => {
    describe("Admin Access", () => {
        beforeEach(() => {
            cy.setupAdminSession();
        });

        it("shows an admin the registration toggle, the device table and its stats", () => {
            cy.visit("kiosk/admin");
            cy.contains("Kiosk Manager");
            cy.contains("Register New Device");
            cy.contains("Kiosk Devices");
            cy.get("#isNewKioskRegistrationActive").should("exist");
            // Columns: Status, Kiosk Name, Assignment, Actions
            cy.get("#KioskTable_wrapper", { timeout: 10000 }).should("exist");
            cy.get("#KioskTable thead th").should("have.length.at.least", 4);
            // Stat cards are filled by JS after the table loads and hide when there are no kiosks
            cy.get("#kioskStats").should("exist");
        });

        it("lets an admin pick the guest classification from a dropdown and auto-saves it", () => {
            cy.getSystemConfig("iKioskGuestClassification").then((original) => {
                cy.intercept("POST", "**/admin/api/system/config/iKioskGuestClassification").as("saveGuestClass");
                cy.visit("kiosk/admin");
                cy.get("#kioskSettings .settings-panel-fields", { timeout: 10000 }).should("not.be.disabled");
                cy.get("#kioskSettings select[name='iKioskGuestClassification']").as("guestClass");
                cy.get("@guestClass").find("option").then(($options) => {
                    const labels = [...$options].map((o) => o.textContent.trim());
                    expect(labels).to.include("Guest");
                    expect(labels).to.include("Member");
                });
                cy.get("@guestClass").find("option:selected").should("contain", "Guest");
                cy.get("@guestClass").select("Regular Attender");
                cy.wait("@saveGuestClass").its("response.statusCode").should("eq", 200);
                cy.getSystemConfig("iKioskGuestClassification").should("eq", "2");
                cy.restoreSystemConfig("iKioskGuestClassification", original);
            });
        });
    });

    describe("ManageGroups Role Access (non-admin)", () => {
        beforeEach(() => {
            cy.setupManageGroupsOnlySession();
        });

        it("opens Kiosk Manager for a ManageGroups user but hides the Kiosk Settings card", () => {
            cy.visit("kiosk/admin");
            cy.contains("Kiosk Manager");
            cy.contains("Register New Device");
            cy.get("#kioskSettings").should("not.exist");
        });

        it("does not show a Group Properties button on the Groups dashboard", () => {
            cy.visit("/groups/dashboard");
            cy.contains("Group Reports");
            cy.get('a[href*="PropertyList.php?Type=g"]').should("not.exist");
        });

        it("ManageGroups user who is not an admin does not see Group Properties in the Groups menu (#9172)", () => {
            cy.visit("/");
            cy.contains("a", "Groups").first().click({ force: true });
            // Kiosk Manager is gated on ManageGroups, so it stays; PropertyList.php is admin-only,
            // so the menu must not offer it to this user.
            cy.get('a[href*="kiosk/admin"]', { timeout: 10000 }).should("exist");
            cy.get('a[href*="PropertyList.php?Type=g"]').should("not.exist");
        });
    });
});

// NOTE: pure-API kiosk tests (GET/POST/DELETE /kiosk/api/*, access control)
// live in cypress/e2e/api/private/kiosk/kiosk.api.spec.js.

describe("Kiosk Manager Workflow", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should enable kiosk registration and show countdown", () => {
        cy.visit("kiosk/admin");
        
        // Click the toggle to enable registration
        cy.get("#isNewKioskRegistrationActive").check({ force: true });
        
        // Should show countdown (Active for X seconds) - check the status span
        cy.get("#kioskRegistrationStatus", { timeout: 10000 }).should("contain", "Active");
    });
});

describe("Kiosk Manager name escaping", () => {
    const quotedName = 'Lobby "A" x="1"';
    let kioskId = null;

    before(() => {
        cy.setupAdminSession();
        cy.request({ method: "GET", url: "/kiosk/api/devices" }).then((before) => {
            const existingIds = new Set((before.body.KioskDevices || []).map((k) => k.Id));
            cy.request({ method: "POST", url: "/kiosk/api/allowRegistration" });
            cy.clearCookies();
            cy.request({ method: "GET", url: "/kiosk/device/heartbeat", failOnStatusCode: false });
            cy.setupAdminSession({ forceLogin: true });
            cy.request({ method: "GET", url: "/kiosk/api/devices" }).then((after) => {
                const created = (after.body.KioskDevices || []).find((k) => !existingIds.has(k.Id));
                expect(created, "a new kiosk should have registered").to.not.be.undefined;
                kioskId = created.Id;
                cy.request({ method: "POST", url: `/kiosk/api/devices/${kioskId}/name`, body: { name: quotedName } });
            });
        });
    });

    after(() => {
        if (kioskId) {
            cy.setupAdminSession({ forceLogin: true });
            cy.request({ method: "DELETE", url: `/kiosk/api/devices/${kioskId}`, failOnStatusCode: false });
        }
    });

    it("keeps a kiosk name with double quotes inside its data-kiosk-name attribute", () => {
        cy.setupAdminSession();
        cy.visit("/kiosk/admin");
        cy.get(`button[data-kiosk-action='rename'][data-kiosk-id='${kioskId}']`, { timeout: 10000 }).then(($btn) => {
            expect($btn.attr("data-kiosk-name")).to.equal(quotedName);
            expect($btn[0].getAttributeNames().sort()).to.deep.equal(
                ["class", "data-kiosk-action", "data-kiosk-id", "data-kiosk-name", "title"],
            );
        });
        cy.get(`button[data-kiosk-action='delete'][data-kiosk-id='${kioskId}']`).should("have.attr", "data-kiosk-name", quotedName);
    });
});
