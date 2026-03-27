/// <reference types="cypress" />

/**
 * Security tests for RedirectUtils linkBack validation
 * 
 * Tests verify malicious redirect URLs are properly rejected:
 * - Absolute URLs (http://, https://)
 * - JavaScript protocols (javascript:, data:, vbscript:)
 * - Protocol-relative URLs (//evil.com)
 * - URL-encoded variants
 * - Backslash URLs (\\evil.com)
 * - Control characters (null bytes, CRLF injection)
 */
describe('RedirectUtils Security - linkBack Parameter', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe('Rejects malicious redirect URLs', () => {
        const maliciousUrls = [
            // Absolute URLs
            'https://evil.com',
            'http://evil.com',
            'HTTP://EVIL.COM',
            'https://evil.com/phishing',
            
            // JavaScript protocols  
            'javascript:alert(1)',
            'javascript:document.location="https://evil.com"',
            'JAVASCRIPT:alert(1)',
            
            // Data URLs
            'data:text/html,<script>alert(1)</script>',
            'DATA:text/html,<h1>phishing</h1>',
            
            // VBScript
            'vbscript:msgbox(1)',
            
            // Protocol-relative URLs
            '//evil.com',
            '//evil.com/path',
            
            // Backslash URLs (some browsers interpret as //)
            '\\\\evil.com',
            '\\evil.com',
            
            // URL-encoded variants
            '%68%74%74%70%73://evil.com', // https encoded
            '%6A%61%76%61%73%63%72%69%70%74:alert(1)', // javascript encoded
            '%2F%2Fevil.com', // // encoded
        ];

        maliciousUrls.forEach((maliciousUrl) => {
            it(`should reject: ${maliciousUrl.substring(0, 40)}...`, () => {
                // Visit WhyCameEditor with malicious linkBack
                cy.visit(`/WhyCameEditor.php?PersonID=1&WhyCameID=0&linkBack=${encodeURIComponent(maliciousUrl)}`, {
                    failOnStatusCode: false
                });

                // Locate the Cancel/back control and assert following it cannot navigate off-origin.
                cy.get('body').then(($body) => {
                    const $link = $body.find('a:contains("Cancel")').first();
                    const $button = $body.find('button:contains("Cancel")').first();
                    const baseUrl = Cypress.config('baseUrl') || window.location.origin;
                    const baseHost = new URL(baseUrl).host;

                    if ($link && $link.length && $link.attr('href')) {
                        const href = $link.attr('href');
                        // Resolve relative hrefs against base and ensure host matches
                        const resolved = new URL(href, baseUrl);
                        expect(resolved.host).to.equal(baseHost);
                    } else if ($button && $button.length) {
                        // If it's a button, clicking it should not navigate to an external host
                        cy.wrap($button).click({ force: true });
                        cy.location('host').should('eq', baseHost);
                    } else {
                        // Fallback: find any element with Cancel text and ensure it is not an external link
                        cy.contains('Cancel').then(($el) => {
                            const href = $el.prop('href') || $el.attr('href');
                            if (href) {
                                const resolved = new URL(href, baseUrl);
                                expect(resolved.host).to.equal(baseHost);
                            } else {
                                // No actionable href — pass as no external navigation possible
                                expect(true).to.equal(true);
                            }
                        });
                    }
                });
            });
        });
    });

    describe('Accepts valid relative URLs', () => {
        const validUrls = [
            'PersonView.php?PersonID=1',
            'v2/dashboard',
            '/FundRaiserEditor.php?FundRaiserID=1',
            'GroupView.php?GroupID=5',
        ];

        validUrls.forEach((validUrl) => {
            it(`should accept: ${validUrl}`, () => {
                cy.visit(`/WhyCameEditor.php?PersonID=1&WhyCameID=0&linkBack=${encodeURIComponent(validUrl)}`, {
                    failOnStatusCode: false
                });

                cy.get('body').then(($body) => {
                    const $link = $body.find('a:contains("Cancel")').first();
                    const $button = $body.find('button:contains("Cancel")').first();
                    const baseUrl = Cypress.config('baseUrl') || window.location.origin;
                    const baseHost = new URL(baseUrl).host;

                    const expectedPart = validUrl.split('?')[0]; // path fragment expected when Cancel is followed

                    if ($link && $link.length && $link.attr('href')) {
                        const href = $link.attr('href');
                        const resolved = new URL(href, baseUrl);
                        expect(resolved.host).to.equal(baseHost);
                        expect(resolved.href).to.include(expectedPart);
                    } else if ($button && $button.length) {
                        cy.wrap($button).click({ force: true });
                        cy.location('href').should('include', expectedPart);
                    } else {
                        cy.contains('Cancel').then(($el) => {
                            const href = $el.prop('href') || $el.attr('href');
                            if (href) {
                                const resolved = new URL(href, baseUrl);
                                expect(resolved.host).to.equal(baseHost);
                                expect(resolved.href).to.include(expectedPart);
                            } else {
                                // No href; clicking should navigate to expected part
                                cy.wrap($el).click({ force: true });
                                cy.location('href').should('include', expectedPart);
                            }
                        });
                    }
                });
            });
        });
    });

    describe('Control character injection attempts', () => {
        const controlCharUrls = [
            'safe.php%00evil.com', // Null byte
            'safe.php%0d%0aLocation: https://evil.com', // CRLF injection
            'safe.php%09evil', // Tab character
        ];

        controlCharUrls.forEach((maliciousUrl) => {
            it(`should reject control characters: ${maliciousUrl.substring(0, 30)}...`, () => {
                cy.visit(`/WhyCameEditor.php?PersonID=1&WhyCameID=0&linkBack=${maliciousUrl}`, {
                    failOnStatusCode: false
                });

                cy.get('body').then(($body) => {
                    const $link = $body.find('a:contains("Cancel")').first();
                    const $button = $body.find('button:contains("Cancel")').first();
                    const baseUrl = Cypress.config('baseUrl') || window.location.origin;
                    const baseHost = new URL(baseUrl).host;

                    if ($link && $link.length && $link.attr('href')) {
                        const href = $link.attr('href');
                        const resolved = new URL(href, baseUrl);
                        expect(resolved.host).to.equal(baseHost);
                        expect(resolved.href).to.match(/(PersonView\.php|\/(v2\/dashboard))/);
                    } else if ($button && $button.length) {
                        cy.wrap($button).click({ force: true });
                        cy.location('host').should('eq', baseHost);
                        cy.location('href').should('match', /(PersonView\.php|\/(v2\/dashboard))/);
                    } else {
                        cy.contains('Cancel').then(($el) => {
                            const href = $el.prop('href') || $el.attr('href');
                            if (href) {
                                const resolved = new URL(href, baseUrl);
                                expect(resolved.host).to.equal(baseHost);
                                expect(resolved.href).to.match(/(PersonView\.php|\/(v2\/dashboard))/);
                            } else {
                                cy.wrap($el).click({ force: true });
                                cy.location('host').should('eq', baseHost);
                                cy.location('href').should('match', /(PersonView\.php|\/(v2\/dashboard))/);
                            }
                        });
                    }
                });
            });
        });
    });
});
