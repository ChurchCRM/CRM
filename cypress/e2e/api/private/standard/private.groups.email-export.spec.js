/// <reference types="cypress" />

describe("API Private Groups Email Export", () => {
    describe("Admin Access", () => {
        it("GET /api/groups/sundayschool/export/email returns CSV attachment", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/groups/sundayschool/export/email",
                null,
                200
            ).then((resp) => {
                // Verify CSV content-type header
                expect(resp.headers["content-type"]).to.match(/text\/?csv/);

                // Verify content-disposition attachment with dated filename
                expect(resp.headers["content-disposition"]).to.match(
                    /attachment;\s*filename="?EmailExport-.*\.csv"?/
                );

                // Body should be a string (CSV text)
                const body = resp.body;
                expect(body).to.be.a("string");

                // Verify CSV header row contains expected columns
                expect(body).to.include("CRM ID");
                expect(body).to.include("FirstName");
                expect(body).to.include("LastName");
                expect(body).to.include("Email");
            });
        });

        it("CSV body contains data rows with valid structure", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/groups/sundayschool/export/email",
                null,
                200
            ).then((resp) => {
                // Parse CSV properly — handle quoted fields that may contain commas/newlines
                const parseCsvRow = (row) => {
                    const cols = [];
                    let current = "";
                    let inQuotes = false;
                    for (let i = 0; i < row.length; i++) {
                        const ch = row[i];
                        if (inQuotes) {
                            if (ch === '"' && row[i + 1] === '"') { current += '"'; i++; }
                            else if (ch === '"') { inQuotes = false; }
                            else { current += ch; }
                        } else {
                            if (ch === '"') { inQuotes = true; }
                            else if (ch === ",") { cols.push(current); current = ""; }
                            else { current += ch; }
                        }
                    }
                    cols.push(current);
                    return cols;
                };

                const lines = resp.body.trim().replace(/\r\n/g, "\n").split("\n");
                // At least a header row
                expect(lines.length).to.be.at.least(1);

                // Header row should have at least 4 columns (CRM ID, FirstName, LastName, Email)
                const headerCols = parseCsvRow(lines[0]);
                expect(headerCols.length).to.be.at.least(4);

                // If there are data rows, verify they have the same column count
                if (lines.length > 1) {
                    const dataCols = parseCsvRow(lines[1]);
                    expect(dataCols.length).to.equal(headerCols.length);
                }
            });
        });
    });

    describe("Authorization - Non-Admin Users", () => {
        it("Non-admin user without ManageGroups permission is denied", () => {
            cy.makePrivateNoFinanceAPICall(
                "GET",
                "/api/groups/sundayschool/export/email",
                null,
                [401, 403, 500]
            );
        });
    });
});

describe("API Private Groups Sunday School Export", () => {
    describe("Admin Access", () => {
        it("GET /api/groups/sundayschool/export/classlist returns CSV attachment", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/groups/sundayschool/export/classlist",
                null,
                200
            ).then((resp) => {
                // Verify CSV content-type header
                expect(resp.headers["content-type"]).to.match(/text\/?csv/);

                // Verify content-disposition attachment with dated filename
                expect(resp.headers["content-disposition"]).to.match(
                    /attachment;\s*filename="?SundaySchool-.*\.csv"?/
                );

                // Body should be a string (CSV text)
                const body = resp.body;
                expect(body).to.be.a("string");

                // Verify CSV header row contains expected columns
                expect(body).to.include("Class");
                expect(body).to.include("Role");
                expect(body).to.include("First Name");
                expect(body).to.include("Last Name");
                expect(body).to.include("Birth Date");
                expect(body).to.include("Dad Name");
                expect(body).to.include("Mom Name");
            });
        });

        it("CSV body contains rows with 15 columns", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/groups/sundayschool/export/classlist",
                null,
                200
            ).then((resp) => {
                // Parse CSV properly — handle quoted fields that may contain commas
                const parseCsvRow = (row) => {
                    const cols = [];
                    let current = "";
                    let inQuotes = false;
                    for (let i = 0; i < row.length; i++) {
                        const ch = row[i];
                        if (inQuotes) {
                            if (ch === '"' && row[i + 1] === '"') { current += '"'; i++; }
                            else if (ch === '"') { inQuotes = false; }
                            else { current += ch; }
                        } else {
                            if (ch === '"') { inQuotes = true; }
                            else if (ch === ",") { cols.push(current); current = ""; }
                            else { current += ch; }
                        }
                    }
                    cols.push(current);
                    return cols;
                };

                const lines = resp.body.trim().replace(/\r\n/g, "\n").split("\n");
                expect(lines.length).to.be.at.least(1);

                // Header row should have exactly 15 columns
                const headerCols = parseCsvRow(lines[0]);
                expect(headerCols.length).to.equal(15);
            });
        });
    });

    describe("Authorization - Non-Admin Users", () => {
        it("Non-admin user without ManageGroups permission is denied", () => {
            cy.makePrivateNoFinanceAPICall(
                "GET",
                "/api/groups/sundayschool/export/classlist",
                null,
                [401, 403, 500]
            );
        });
    });
});
