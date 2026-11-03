/// <reference types="cypress" />

/**
 * API tests for the admin telemetry consent endpoint
 *
 * Covers:
 *   POST /admin/api/system/telemetry-consent
 *
 * Records the admin's choice and lifecycle baseline in SystemConfig.
 * Settings it touches are captured up front and put back afterwards.
 */
describe("Admin API Telemetry Consent Endpoint", () => {
    let originalLevel;
    let originalAskedVersion;
    let originalLifecycleVersion;

    before(() => {
        cy.getSystemConfig("sTelemetryLevel").then((value) => {
            originalLevel = value;
        });
        cy.getSystemConfig("sTelemetryAskedVersion").then((value) => {
            originalAskedVersion = value;
        });
        cy.getSystemConfig("sTelemetryLifecycleVersion").then((value) => {
            originalLifecycleVersion = value;
        });
    });

    after(() => {
        cy.restoreSystemConfig("sTelemetryLevel", originalLevel);
        cy.restoreSystemConfig("sTelemetryAskedVersion", originalAskedVersion);
        cy.restoreSystemConfig("sTelemetryLifecycleVersion", originalLifecycleVersion);
    });

    it("stores a valid consent level", () => {
        cy.restoreSystemConfig("sTelemetryLifecycleVersion", "");
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/telemetry-consent",
            { level: "errors" },
            200,
        ).then((resp) => {
            expect(resp.body.status).to.eq("ok");
        });
        cy.getSystemConfig("sTelemetryLevel").should("eq", "errors");
        cy.getSystemConfig("sTelemetryLifecycleVersion").should("not.be.empty");
    });

    it("declining records the version the prompt was answered for", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/telemetry-consent",
            { level: "none" },
            200,
        );
        cy.getSystemConfig("sTelemetryLevel").should("eq", "none");
        cy.getSystemConfig("sTelemetryAskedVersion").should("not.be.empty");
    });

    it("falls back to none for an unknown level", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/telemetry-consent",
            { level: "full" },
            200,
        );
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/telemetry-consent",
            { level: "not-a-level" },
            200,
        );
        cy.getSystemConfig("sTelemetryLevel").should("eq", "none");
    });

    it("falls back to none when no level is sent", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/telemetry-consent",
            { level: "warnings" },
            200,
        );
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/telemetry-consent", {}, 200);
        cy.getSystemConfig("sTelemetryLevel").should("eq", "none");
    });

    it("non-admin cannot change the level", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/telemetry-consent",
            { level: "none" },
            200,
        );
        cy.makePrivateUserAPICall(
            "POST",
            "/admin/api/system/telemetry-consent",
            { level: "full" },
            403,
        );
        cy.getSystemConfig("sTelemetryLevel").should("eq", "none");
    });
});
