/// <reference types="cypress" />

describe("XSS: Search dropdown escaping", () => {
    before(() => {
        cy.setupAdminSession();
    });

    it("Search results escape HTML in person names", () => {
        cy.visit("/");
        cy.get("#globalSearch").should("exist");

        cy.window().then((win) => {
            // Verify the escapeHtml utility exists on the CRM object
            expect(win.CRM).to.have.property("escapeHtml");
            expect(win.CRM.escapeHtml).to.be.a("function");

            // Verify it properly escapes dangerous characters
            const dangerous = '<script>alert(1)</script>';
            const escaped = win.CRM.escapeHtml(dangerous);
            expect(escaped).to.not.contain("<script>");
            expect(escaped).to.contain("&lt;script&gt;");
        });
    });

    it("Search dropdown renders escaped content from API", () => {
        cy.visit("/");

        // Intercept search API and inject XSS payload
        cy.intercept("GET", "**/api/search/**", {
            statusCode: 200,
            body: [
                {
                    text: "Persons (1)",
                    children: [
                        {
                            text: '<img src=x onerror=alert(1)>',
                            uri: '/test?a="><script>alert(2)</script>',
                        },
                    ],
                },
            ],
        }).as("searchXSS");

        cy.get("#globalSearch").type("test");
        cy.wait("@searchXSS");

        // Verify no script elements were injected into the dropdown
        cy.get("#searchDropdown").should("exist");
        cy.get("#searchDropdown script").should("not.exist");
        cy.get("#searchDropdown img").should("not.exist");

        // The escaped text should appear as literal text, not rendered HTML
        cy.get("#searchDropdown").should("contain.text", "<img");
    });
});
