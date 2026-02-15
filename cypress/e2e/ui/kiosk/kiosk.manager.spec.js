/// <reference types="cypress" />

describe("Kiosk Manager", () => {
    describe("Admin Access", () => {
        beforeEach(() => {
            cy.setupAdminSessionFromEnv();
        });

        it("should display the Kiosk Manager page", () => {
            cy.visit("kiosk/admin");
            cy.contains("Kiosk Manager");
            cy.contains("Enable New Kiosk Registration");
            cy.contains("Active Kiosks");
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
            // Wait for DataTable headers to load
            cy.get("#KioskTable thead th").should("have.length.at.least", 5);
        });
    });

    describe("Standard User Access Denied", () => {
        beforeEach(() => {
            cy.setupStandardSessionFromEnv();
        });

        it("should deny access to non-admin users for kiosk admin page", () => {
            cy.visit("kiosk/admin", { failOnStatusCode: false });
            // Should be redirected or show access denied
            cy.url().should("not.include", "kiosk/admin");
        });
    });
});

describe("Kiosk API", () => {
    describe("Admin API Access", () => {
        beforeEach(() => {
            cy.setupAdminSessionFromEnv();
        });

        it("should fetch kiosk devices list", () => {
            cy.request({
                method: "GET",
                url: "/kiosk/api/devices",
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property("KioskDevices");
                expect(response.body.KioskDevices).to.be.an("array");
            });
        });

        it("should enable kiosk registration", () => {
            cy.request({
                method: "POST",
                url: "/kiosk/api/allowRegistration",
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property("visibleUntil");
            });
        });
    });

    describe("Standard User API Access Denied", () => {
        beforeEach(() => {
            cy.setupStandardSessionFromEnv();
        });

        it("should deny access to non-admin users for kiosk API", () => {
            cy.request({
                method: "GET",
                url: "/kiosk/api/devices",
                failOnStatusCode: false,
            }).then((response) => {
                // Should get 401 or 403 or redirect
                expect(response.status).to.be.oneOf([401, 403, 302]);
            });
        });

        it("should deny access to allowRegistration for non-admin users", () => {
            cy.request({
                method: "POST",
                url: "/kiosk/api/allowRegistration",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.be.oneOf([401, 403, 302]);
            });
        });
    });

    describe("Unauthenticated API Access Denied", () => {
        it("should deny access to unauthenticated users for kiosk API", () => {
            cy.request({
                method: "GET",
                url: "/kiosk/api/devices",
                failOnStatusCode: false,
            }).then((response) => {
                // Should get 401 or redirect to login
                expect(response.status).to.be.oneOf([401, 302]);
            });
        });
    });
});

describe("Kiosk Manager Workflow", () => {
    beforeEach(() => {
        cy.setupAdminSessionFromEnv();
    });

    it("should enable kiosk registration and show countdown", () => {
        cy.visit("kiosk/admin");
        
        // Click the toggle to enable registration
        cy.get("#isNewKioskRegistrationActive").check({ force: true });
        
        // Should show countdown (Active for X seconds)
        cy.get(".toggle-on", { timeout: 10000 }).should("contain", "Active");
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

describe("Kiosk Device Operations", () => {
    let testKioskId = null;

    beforeEach(() => {
        cy.setupAdminSessionFromEnv();
    });

    it("should handle reload command for existing kiosk", () => {
        cy.request({
            method: "GET",
            url: "/kiosk/api/devices",
        }).then((response) => {
            if (response.body.KioskDevices && response.body.KioskDevices.length > 0) {
                testKioskId = response.body.KioskDevices[0].Id;
                
                cy.request({
                    method: "POST",
                    url: `/kiosk/api/devices/${testKioskId}/reload`,
                }).then((reloadResponse) => {
                    expect(reloadResponse.status).to.equal(200);
                    expect(reloadResponse.body.success).to.equal(true);
                });
            } else {
                cy.log("No kiosks available for testing reload command");
            }
        });
    });

    it("should handle identify command for existing kiosk", () => {
        cy.request({
            method: "GET",
            url: "/kiosk/api/devices",
        }).then((response) => {
            if (response.body.KioskDevices && response.body.KioskDevices.length > 0) {
                testKioskId = response.body.KioskDevices[0].Id;
                
                cy.request({
                    method: "POST",
                    url: `/kiosk/api/devices/${testKioskId}/identify`,
                }).then((identifyResponse) => {
                    expect(identifyResponse.status).to.equal(200);
                    expect(identifyResponse.body.success).to.equal(true);
                });
            } else {
                cy.log("No kiosks available for testing identify command");
            }
        });
    });

    it("should return 404 for non-existent kiosk", () => {
        cy.request({
            method: "POST",
            url: "/kiosk/api/devices/99999/reload",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.equal(404);
        });
    });

    it("should return 404 for non-existent kiosk identify", () => {
        cy.request({
            method: "POST",
            url: "/kiosk/api/devices/99999/identify",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.equal(404);
        });
    });

    it("should return 404 for non-existent kiosk accept", () => {
        cy.request({
            method: "POST",
            url: "/kiosk/api/devices/99999/accept",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.equal(404);
        });
    });

    it("should return 404 for non-existent kiosk delete", () => {
        cy.request({
            method: "DELETE",
            url: "/kiosk/api/devices/99999",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.equal(404);
        });
    });
});

describe("Kiosk Manager Menu Integration", () => {
    beforeEach(() => {
        cy.setupAdminSessionFromEnv();
    });

    it("should have Kiosk Manager link in admin menu", () => {
        cy.visit("/");
        
        // Find and click the Admin parent menu to expand it
        cy.get('.nav-sidebar').contains('a', 'Admin').first().click({ force: true });
        
        // Wait for menu to expand and check for Kiosk Manager menu item
        cy.get('a[href*="kiosk/admin"]', { timeout: 10000 }).should("exist").and("contain", "Kiosk Manager");
    });

    it("should navigate to Kiosk Manager from menu", () => {
        cy.visit("/");
        
        // Find and click the Admin parent menu to expand it
        cy.get('.nav-sidebar').contains('a', 'Admin').first().click({ force: true });
        
        // Click Kiosk Manager link
        cy.get('a[href*="kiosk/admin"]', { timeout: 10000 }).click({ force: true });
        
        // Should be on Kiosk Manager page
        cy.url().should("include", "kiosk/admin");
        cy.contains("Kiosk Manager");
    });
});
