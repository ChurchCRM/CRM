/// <reference types="cypress" />

describe("API Public CSP Report", () => {
    it("should accept CSP violation report with 204 status", () => {
        const cspReport = {
            "csp-report": {
                "document-uri": "http://example.com/page.html",
                "referrer": "",
                "violated-directive": "script-src 'self'",
                "effective-directive": "script-src",
                "original-policy": "default-src 'self'; script-src 'self'",
                "disposition": "report",
                "blocked-uri": "http://evil.example.com/malicious.js",
                "line-number": 1,
                "column-number": 1,
                "source-file": "http://example.com/page.html",
                "status-code": 200,
                "script-sample": ""
            }
        };

        cy.request({
            method: "POST",
            url: "/api/public/csp-report",
            body: cspReport
        }).then((resp) => {
            expect(resp.status).to.eq(204);
            // 204 responses have no body content
        });
    });

    it("should accept CSP report with minimal data", () => {
        const minimalReport = {
            "csp-report": {
                "violated-directive": "img-src 'self'",
                "blocked-uri": "http://untrusted.com/image.png"
            }
        };

        cy.request({
            method: "POST",
            url: "/api/public/csp-report",
            body: minimalReport
        }).then((resp) => {
            expect(resp.status).to.eq(204);
        });
    });

    it("should handle empty report object", () => {
        cy.request({
            method: "POST",
            url: "/api/public/csp-report",
            body: {},
            failOnStatusCode: false
        }).then((resp) => {
            expect(resp.status).to.eq(204);
        });
    });
});
