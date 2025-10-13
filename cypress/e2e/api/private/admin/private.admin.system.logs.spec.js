/// <reference types="cypress" />

describe("API Private Admin System Logs", () => {
    it("Set log level to WARNING", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/system/logs/loglevel",
            { value: "300" },
            200,
        );
    });

    it("Set log level to ERROR", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/system/logs/loglevel",
            { value: "400" },
            200,
        );
    });

    it("Set log level to INFO", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/system/logs/loglevel",
            { value: "200" },
            200,
        );
    });

    it("Reject invalid log level - string", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/system/logs/loglevel",
            { value: "invalid" },
            400,
        );
    });

    it("Reject invalid log level - empty", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/system/logs/loglevel",
            {},
            400,
        );
    });

    it("Get log file content - reject path traversal", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/system/logs/test..log",
            null,
            400,
        );
    });

    it("Get log file content - reject non-log extension", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/system/logs/config.php",
            null,
            400,
        );
    });

    it("Get log file content - return 404 for non-existent", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/system/logs/nonexistent-file.log",
            null,
            404,
        );
    });

    it("Delete log file - reject path traversal", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/system/logs/test..log",
            null,
            400,
        );
    });

    it("Delete log file - reject non-log extension", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/system/logs/config.php",
            null,
            400,
        );
    });

    it("Delete log file - return 404 for non-existent", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/system/logs/nonexistent-file.log",
            null,
            404,
        );
    });

    it("Delete all logs", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/system/logs",
            null,
            200,
        );
    });
});