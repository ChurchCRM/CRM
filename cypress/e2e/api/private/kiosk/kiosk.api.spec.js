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
