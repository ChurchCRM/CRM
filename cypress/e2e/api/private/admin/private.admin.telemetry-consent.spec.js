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
        cy.getSystemConfig("sTelemetryLifecycleVersion").should("eq", "");
    });

    it("classifies the test stack and suppresses browser telemetry after opt-in", () => {
        cy.restoreSystemConfig("sTelemetryLevel", "full");
        cy.setupAdminSession();
        const requests = [];
        cy.intercept("https://us.i.posthog.com/**", (request) => {
            requests.push(request.url);
            request.reply({ statusCode: 200, body: "Ok" });
        });
        cy.visit("/admin/system/debug");
        cy.window().its("CRM.telemetry.install_profile").should("eq", "test");
        cy.then(() => expect(requests).to.be.empty);
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
            { level: "install" },
            200,
        );
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/telemetry-consent", {}, 200);
        cy.getSystemConfig("sTelemetryLevel").should("eq", "none");
    });

    it("keeps Install when Not now is chosen and never repeats the prompt", () => {
        cy.restoreSystemConfig("sTelemetryLevel", "install");
        cy.restoreSystemConfig("sTelemetryAskedVersion", "");
        cy.setupAdminSession();
        cy.visit("/admin/");
        cy.get("#telemetry-consent-card").should("be.visible");
        cy.get('.js-telemetry-consent[data-level="install"]').contains("No thanks").click();
        cy.get("#telemetry-consent-card").should("not.exist");
        cy.getSystemConfig("sTelemetryLevel").should("eq", "install");
        cy.restoreSystemConfig("sTelemetryAskedVersion", "7.0.0");
        cy.setupAdminSession();
        cy.visit("/admin/");
        cy.get("#telemetry-consent-card").should("not.exist");
        cy.get('script[src*="/telemetry.min.js"]').should("not.exist");
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
