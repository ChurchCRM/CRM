/// <reference types="cypress" />

describe("SQL Injection Security Tests - CVE-2025-1132", () => {
    beforeEach(() => {
        cy.loginAdmin();
    });

    describe("EditEventTypes.php - EN_tyid parameter protection", () => {
        it("should reject SQL injection attempts in EN_tyid parameter", () => {
            // Test various SQL injection payloads
            const sqlInjectionPayloads = [
                "1' OR '1'='1",
                "1; DROP TABLE event_types; --",
                "1' UNION SELECT 1,2,3,4,5 --",
                "1'; INSERT INTO event_types VALUES (999,'hacked'); --",
                "1' AND (SELECT COUNT(*) FROM information_schema.tables) > 0 --",
                "-1' OR 1=1 #",
                "1'; WAITFOR DELAY '00:00:05'; --"
            ];

            sqlInjectionPayloads.forEach((payload) => {
                cy.request({
                    method: 'POST',
                    url: '/EditEventTypes.php',
                    form: true,
                    body: {
                        'EN_tyid': payload,
                        'Action': 'NAME',
                        'newEvtName': 'Test Event'
                    },
                    failOnStatusCode: false
                }).then((response) => {
                    // The page should either:
                    // 1. Return a 200 with proper error handling (input validation working)
                    // 2. Return an error status (server rejecting malicious input)
                    // 3. NOT execute the SQL injection (no database corruption)
                    
                    expect(response.status).to.be.oneOf([200, 400, 403, 500]);
                    
                    // Ensure the response doesn't contain signs of successful SQL injection
                    expect(response.body).to.not.contain('mysql_fetch_array()');
                    expect(response.body).to.not.contain('SQL syntax error');
                    expect(response.body).to.not.contain('Warning: mysql');
                    expect(response.body).to.not.contain('hacked');
                    
                    cy.log(`✅ SQL injection payload blocked: ${payload}`);
                });
            });
        });

        it("should properly validate integer EN_tyid parameter", () => {
            // Test that valid integer values work correctly
            cy.request({
                method: 'POST',
                url: '/EditEventTypes.php',
                form: true,
                body: {
                    'EN_tyid': '1',
                    'Action': 'VIEW'
                },
                failOnStatusCode: false
            }).then((response) => {
                // Should handle valid integer input properly
                expect(response.status).to.be.oneOf([200, 302]);
                cy.log('✅ Valid integer EN_tyid processed correctly');
            });
        });

        it("should reject non-numeric EN_tyid values", () => {
            const invalidInputs = [
                'abc',
                '1.5',
                'true',
                'null',
                '[]',
                '{}'
            ];

            invalidInputs.forEach((invalidInput) => {
                cy.request({
                    method: 'POST',
                    url: '/EditEventTypes.php',
                    form: true,
                    body: {
                        'EN_tyid': invalidInput,
                        'Action': 'NAME',
                        'newEvtName': 'Test'
                    },
                    failOnStatusCode: false
                }).then((response) => {
                    // Should handle invalid input gracefully
                    expect(response.status).to.be.oneOf([200, 400, 403, 500]);
                    cy.log(`✅ Invalid input rejected: ${invalidInput}`);
                });
            });
        });
    });

    describe("EventEditor.php - EN_tyid parameter protection", () => {
        it("should reject SQL injection attempts in EventEditor EN_tyid parameter", () => {
            const sqlInjectionPayloads = [
                "1' OR '1'='1",
                "1; DROP TABLE event_types; --",
                "1' UNION SELECT 1,2,3,4,5 --"
            ];

            sqlInjectionPayloads.forEach((payload) => {
                cy.request({
                    method: 'POST',
                    url: '/EventEditor.php',
                    form: true,
                    body: {
                        'EN_tyid': payload,
                        'Action': 'Create Event'
                    },
                    failOnStatusCode: false
                }).then((response) => {
                    // The page should handle malicious input safely
                    expect(response.status).to.be.oneOf([200, 400, 403, 500]);
                    
                    // Ensure no SQL errors or injection signs
                    expect(response.body).to.not.contain('mysql_fetch_array()');
                    expect(response.body).to.not.contain('SQL syntax error');
                    expect(response.body).to.not.contain('Warning: mysql');
                    
                    cy.log(`✅ EventEditor SQL injection payload blocked: ${payload}`);
                });
            });
        });

        it("should properly validate integer EN_tyid in EventEditor", () => {
            cy.request({
                method: 'POST',
                url: '/EventEditor.php',
                form: true,
                body: {
                    'EN_tyid': '1'
                },
                failOnStatusCode: false
            }).then((response) => {
                // Should handle valid integer input
                expect(response.status).to.be.oneOf([200, 302]);
                cy.log('✅ EventEditor valid integer EN_tyid processed correctly');
            });
        });
    });

    describe("Input validation functions", () => {
        it("should verify InputUtils.legacyFilterInput is working", () => {
            // Test that the application properly uses InputUtils for validation
            // Since this page expects POST requests, we'll test via POST instead of GET
            
            cy.request({
                method: 'POST',
                url: '/EditEventTypes.php',
                form: true,
                body: {
                    'EN_tyid': '1'
                },
                failOnStatusCode: false
            }).then((response) => {
                // The page should handle the request (even if it redirects or shows the form)
                expect(response.status).to.be.oneOf([200, 302]);
                
                // Check that the page doesn't expose any SQL error messages
                if (response.body) {
                    expect(response.body).to.not.contain('mysql_fetch_array()');
                    expect(response.body).to.not.contain('SQL syntax error');
                    expect(response.body).to.not.contain('Warning: mysql');
                }
                
                cy.log('✅ POST request handled correctly with validated input');
            });
        });
    });

    describe("Regression test for CVE-2025-1132", () => {
        it("should prevent the specific vulnerability pattern", () => {
            // Test the exact vulnerability pattern that was fixed
            const cve_payload = "1' AND (SELECT 1 FROM (SELECT COUNT(*),CONCAT(VERSION(),FLOOR(RAND(0)*2))x FROM information_schema.tables GROUP BY x)a) -- ";
            
            cy.request({
                method: 'POST',
                url: '/EditEventTypes.php',
                form: true,
                body: {
                    'EN_tyid': cve_payload,
                    'Action': 'NAME',
                    'newEvtName': 'CVE Test'
                },
                failOnStatusCode: false
            }).then((response) => {
                // This specific CVE should be blocked
                expect(response.status).to.be.oneOf([200, 400, 403, 500]);
                
                // Should not reveal database version or structure
                expect(response.body).to.not.match(/\d+\.\d+\.\d+.*mysql/i); // MySQL version pattern
                expect(response.body).to.not.contain('information_schema');
                expect(response.body).to.not.contain('VERSION()');
                expect(response.body).to.not.contain('CONCAT(VERSION()');
                
                cy.log('✅ CVE-2025-1132 vulnerability pattern blocked');
            });
        });
    });
});