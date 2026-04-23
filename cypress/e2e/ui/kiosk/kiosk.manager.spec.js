/// <reference types="cypress" />

describe("Kiosk Manager", () => {
    describe("Admin Access", () => {
        beforeEach(() => {
            cy.setupAdminSession();
        });

        it("should display the Kiosk Manager page", () => {
            cy.visit("kiosk/admin");
            cy.contains("Kiosk Manager");
            cy.contains("Register New Device");
            cy.contains("Kiosk Devices");
        });

        it("should display the kiosk registration toggle", () => {
            cy.visit("kiosk/admin");
            cy.get("#isNewKioskRegistrationActive").should("exist");
        });

        it("should display the kiosk table", () => {
            cy.visit("kiosk/admin");
            cy.get("#KioskTable").should("exist");
            // Wait for DataTable to initialize
            cy.get("#KioskTable_wrapper").should("exist");
        });

        it("should have correct table columns", () => {
            cy.visit("kiosk/admin");
            cy.get("#KioskTable").should("exist");
            // Wait for DataTable headers to load — columns: Status, Kiosk Name, Assignment, Actions
            cy.get("#KioskTable thead th").should("have.length.at.least", 4);
        });

        it("should display dashboard stat cards when kiosks exist", () => {
            cy.visit("kiosk/admin");
            // Stat cards are populated via JS after table loads; they hide when no kiosks
            cy.get("#KioskTable_wrapper", { timeout: 10000 }).should("exist");
            // Check the stat card container exists in DOM (may be hidden if no kiosks)
            cy.get("#kioskStats").should("exist");
        });
    });

    describe("Standard User Access Denied", () => {
        beforeEach(() => {
            cy.setupStandardSession();
        });

        it("should deny access to non-admin users for kiosk admin page", () => {
            cy.visit("kiosk/admin", { failOnStatusCode: false });
            // Should be redirected or show access denied
            cy.url().should("not.include", "kiosk/admin");
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

    it("should load and display kiosk data from API", () => {
        cy.visit("kiosk/admin");
        
        // Wait for the table to load data
        cy.get("#KioskTable_wrapper").should("exist");
        
        // Either shows kiosks or "No data available" message
        cy.get("#KioskTable tbody").should("exist");
    });

    it("should have action buttons for existing kiosks", () => {
        // First check if there are any kiosks
        cy.request({
            method: "GET",
            url: "/kiosk/api/devices",
        }).then((response) => {
            if (response.body.KioskDevices && response.body.KioskDevices.length > 0) {
                cy.visit("kiosk/admin");
                
                // Wait for table to load
                cy.get("#KioskTable tbody tr", { timeout: 10000 }).first().within(() => {
                    // Should have action buttons
                    cy.get(".btn-group").should("exist");
                    cy.get("button[title='Reload']").should("exist");
                    cy.get("button[title='Identify']").should("exist");
                    cy.get("button[title='Delete']").should("exist");
                });
            } else {
                // No kiosks exist, just verify the empty state
                cy.visit("kiosk/admin");
                cy.get("#KioskTable_wrapper").should("exist");
            }
        });
    });
});

describe("Kiosk Manager Menu Integration", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should have Kiosk Manager link in admin menu", () => {
        cy.visit("/");
        
        // Find and click the Admin parent menu to expand it (avoid relying on old sidebar class)
        cy.contains('a', 'Admin').first().click({ force: true });
        
        // Wait for menu to expand and check for Kiosk Manager menu item
        cy.get('a[href*="kiosk/admin"]', { timeout: 10000 }).should("exist").and("contain", "Kiosk Manager");
    });

    it("should navigate to Kiosk Manager from menu", () => {
        cy.visit("/");
        
        // Find and click the Admin parent menu to expand it (avoid relying on old sidebar class)
        cy.contains('a', 'Admin').first().click({ force: true });
        
        // Click Kiosk Manager link
        cy.get('a[href*="kiosk/admin"]', { timeout: 10000 }).click({ force: true });
        
        // Should be on Kiosk Manager page
        cy.url().should("include", "kiosk/admin");
        cy.contains("Kiosk Manager");
    });
});
