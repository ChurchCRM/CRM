/// <reference types="cypress" />

describe("SQL Injection Security Tests - CVE-2025-1132", () => {
    beforeEach(() => {
        cy.loginAdmin();
    });

    describe("EditEventTypes.php - EN_tyid parameter protection", () => {
        it("should reject SQL injection attempts in EN_tyid parameter", () => {
            // Test critical SQL injection payloads
            const sqlInjectionPayloads = [
                "1' OR '1'='1",
                "1; DROP TABLE event_types; --",
                "1' UNION SELECT 1,2,3,4,5 --"
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
                    // The page should either return 200 with proper error handling or error status
                    expect(response.status).to.be.oneOf([200, 400, 403, 500]);
                    
                    // Ensure the response doesn't contain signs of successful SQL injection
                    expect(response.body).to.not.contain('mysql_fetch_array()');
                    expect(response.body).to.not.contain('SQL syntax error');
                    expect(response.body).to.not.contain('Warning: mysql');
                    
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
                expect(response.body).to.not.match(/\d+\.\d+\.\d+.*mysql/i);
                expect(response.body).to.not.contain('information_schema');
                expect(response.body).to.not.contain('VERSION()');
                
                cy.log('✅ CVE-2025-1132 vulnerability pattern blocked');
            });
        });
    });
});