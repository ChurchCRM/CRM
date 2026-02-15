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

                // Check that the Cancel button does NOT have the malicious URL
                cy.get('input[name="Cancel"]').then(($btn) => {
                    const onclick = $btn.attr('onclick');
                    
                    // The onclick should NOT contain the malicious URL
                    // It should either have a safe relative URL or the fallback
                    expect(onclick).to.not.include('evil.com');
                    expect(onclick).to.not.include('javascript:');
                    expect(onclick).to.not.include('data:');
                    expect(onclick).to.not.include('vbscript:');
                    expect(onclick).to.not.match(/\/\//); // No protocol-relative
                    
                    // Should contain the fallback (PersonView.php)
                    expect(onclick).to.include('PersonView.php');
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

                cy.get('input[name="Cancel"]').then(($btn) => {
                    const onclick = $btn.attr('onclick');
                    
                    // The onclick should contain the valid URL (escaped)
                    // Note: some characters may be HTML-escaped
                    const expectedPart = validUrl.split('?')[0]; // Just check the path part
                    expect(onclick).to.include(expectedPart);
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

                cy.get('input[name="Cancel"]').then(($btn) => {
                    const onclick = $btn.attr('onclick');
                    
                    // Should use fallback, not the malicious URL
                    expect(onclick).to.include('PersonView.php');
                    expect(onclick).to.not.include('evil.com');
                    expect(onclick).to.not.include('Location:');
                });
            });
        });
    });
});
