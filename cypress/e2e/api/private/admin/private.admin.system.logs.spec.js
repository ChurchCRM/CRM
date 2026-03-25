/// <reference types="cypress" />

describe("API Private Admin System Logs", () => {
    it("Set log level to WARNING", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/logs/loglevel",
            { value: "300" },
            200,
        );
    });

    it("Set log level to ERROR", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/logs/loglevel",
            { value: "400" },
            200,
        );
    });

    it("Set log level to INFO", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/logs/loglevel",
            { value: "200" },
            200,
        );
    });

    it("Reject invalid log level - string", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/logs/loglevel",
            { value: "invalid" },
            400,
        );
    });

    it("Reject invalid log level - empty", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/logs/loglevel",
            {},
            400,
        );
    });

    it("Get log file content - reject path traversal", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/logs/test..log",
            null,
            400,
        );
    });

    it("Get log file content - reject non-log extension", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/logs/config.php",
            null,
            400,
        );
    });

    it("Get log file content - return 404 for non-existent", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/logs/nonexistent-file.log",
            null,
            404,
        );
    });

    it("Read actual log file and validate JSON response structure", () => {
        // Try to read a log file with a common naming pattern
        // The API should return valid JSON whether the file exists or not
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/logs/test-api.log",
            null,
            [200, 404],  // Accept either success or not found
        ).then((response) => {
            // Regardless of whether file exists, response should be well-formed
            expect(response.status).to.be.oneOf([200, 404]);
            
            if (response.status === 200) {
                // If file was found, validate JSON structure
                expect(response.body).to.be.an('object');
                expect(response.body).to.have.property('success').that.equals(true);
                expect(response.body).to.have.property('lines').that.is.an('array');
                expect(response.body).to.have.property('count').that.is.a('number');
                expect(response.body.count).to.equal(response.body.lines.length);
                
                // Verify log lines are properly formatted (non-empty strings)
                if (response.body.count > 0) {
                    response.body.lines.forEach((line) => {
                        expect(line).to.be.a('string');
                        expect(line.length).to.be.greaterThan(0);
                    });
                }
            }
        });
    });

    it("Delete log file - reject path traversal", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/admin/api/system/logs/test..log",
            null,
            400,
        );
    });

    it("Delete log file - reject non-log extension", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/admin/api/system/logs/config.php",
            null,
            400,
        );
    });

    it("Delete log file - return 404 for non-existent", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/admin/api/system/logs/nonexistent-file.log",
            null,
            404,
        );
    });

    it("Delete all logs", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/admin/api/system/logs",
            null,
            200,
        );
    });

    it("Download actual log file - verify attachment headers and content", () => {
        // Create a temporary log file to ensure the endpoint has something to return
        const filename = 'test-download.log';
        const filePath = `src/logs/${filename}`;
        cy.exec(`printf 'test-download-line1\nline2\n' > ${filePath}`);

        const url = `/admin/api/system/logs/${encodeURIComponent(filename)}/download`;

        // Use the private admin API helper to perform the GET request
        cy.makePrivateAdminAPICall(
            "GET",
            url,
            null,
            200,
        ).then((resp) => {
            // Validate headers for a file attachment
            expect(resp.status).to.equal(200);
            const cd = resp.headers['content-disposition'] || resp.headers['Content-Disposition'];
            expect(cd, 'Content-Disposition present').to.be.a('string');
            expect(cd.toLowerCase()).to.include('attachment');
            expect(cd).to.match(/filename\s*=\s*"?.+"?/);

            // Content-Length header should be present and body non-empty
            const cl = resp.headers['content-length'] || resp.headers['Content-Length'];
            expect(cl, 'Content-Length header present').to.match(/\d+/);
            expect(resp.body && resp.body.length, 'body non-empty').to.be.greaterThan(10);
        }).then(() => {
            // Cleanup the temporary log file
            return cy.exec(`rm -f ${filePath}`);
        });
    });
});