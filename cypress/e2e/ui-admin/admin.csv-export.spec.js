describe("CSV Export Page", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("/CSVExport.php");
    });

    it("should load the page with correct title", () => {
        cy.contains("CSV Export").should("be.visible");
    });

    it("should display Field Selection card with pill toggles", () => {
        cy.contains("Field Selection").should("be.visible");

        // Verify pills container exists
        cy.get(".form-selectgroup-pills").first().within(() => {
            // Verify all standard field pills are present
            cy.contains(".form-selectgroup-label", "Title").should("be.visible");
            cy.contains(".form-selectgroup-label", "First Name").should("be.visible");
            cy.contains(".form-selectgroup-label", "Last Name").should("not.exist"); // Last Name is required, not a pill
            cy.contains(".form-selectgroup-label", "Middle Name").should("be.visible");
            cy.contains(".form-selectgroup-label", "Suffix").should("be.visible");
            cy.contains(".form-selectgroup-label", "Address 1").should("be.visible");
            cy.contains(".form-selectgroup-label", "City").should("be.visible");
            cy.contains(".form-selectgroup-label", "State").should("be.visible");
            cy.contains(".form-selectgroup-label", "Zip").should("be.visible");
            cy.contains(".form-selectgroup-label", "Country").should("be.visible");
            cy.contains(".form-selectgroup-label", "Home Phone").should("be.visible");
            cy.contains(".form-selectgroup-label", "Email").should("be.visible");
            cy.contains(".form-selectgroup-label", "Gender").should("be.visible");
        });

        // Verify pre-checked fields
        cy.get('.form-selectgroup-input[name="FirstName"]').should("be.checked");
        cy.get('.form-selectgroup-input[name="Address1"]').should("be.checked");
        cy.get('.form-selectgroup-input[name="City"]').should("be.checked");
        cy.get('.form-selectgroup-input[name="State"]').should("be.checked");
        cy.get('.form-selectgroup-input[name="Zip"]').should("be.checked");
        cy.get('.form-selectgroup-input[name="Country"]').should("be.checked");

        // Verify unchecked fields
        cy.get('.form-selectgroup-input[name="Title"]').should("not.be.checked");
        cy.get('.form-selectgroup-input[name="Envelope"]').should("not.be.checked");
        // Second family address (#9743) is opt-in, so existing exports are unchanged.
        cy.get('.form-selectgroup-input[name="SecondAddress"]').should("not.be.checked");
    });

    it("should append the second-address columns only when SecondAddress is requested", () => {
        cy.request({
            method: "POST",
            url: "/CSVCreateFile.php",
            form: true,
            body: {
                FirstName: 1,
                Address1: 1,
                City: 1,
                State: 1,
                Zip: 1,
                Country: 1,
                SecondAddress: 1,
                Source: "all",
                Gender: 0,
                Format: "Default",
                Submit: "Create File",
            },
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("text/csv");
            const headerRow = response.body.split("\n")[0];
            expect(headerRow).to.include("Second Address 1");
            expect(headerRow).to.include("Second Address 2");
            expect(headerRow).to.include("Second City");
            expect(headerRow).to.include("Second State");
            expect(headerRow).to.include("Second Zip");
            expect(headerRow).to.include("Second Country");
            expect(headerRow).to.include("Mailing Address");
        });

        // Without the opt-in the header row is unchanged.
        cy.request({
            method: "POST",
            url: "/CSVCreateFile.php",
            form: true,
            body: {
                FirstName: 1,
                Address1: 1,
                City: 1,
                State: 1,
                Zip: 1,
                Country: 1,
                Source: "all",
                Gender: 0,
                Format: "Default",
                Submit: "Create File",
            },
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.split("\n")[0]).to.not.include("Second Address 1");
        });
    });

    it("exports the second address and mailing flag for each member of the family", () => {
        // The export reads fam_Second* from the row its own query already joined
        // (review on #9801: no per-person family lookup), so the values must
        // still come out right for every member of one family.
        const stamp = String(Cypress._.random(0, 1e6));
        const familyName = "CsvSecondAddr" + stamp;
        const members = ["Exporta" + stamp, "Exportb" + stamp];

        cy.visit("/FamilyEditor.php");
        cy.contains("Family Info");
        cy.get("#FamilyName").type(familyName);
        cy.get('input[name="Address1"]').type("11 Primary Street");
        cy.get('input[name="City"]').clear().type("Springfield");
        cy.get('select[name="State"]').select("IL", { force: true });
        cy.get("#secondAddressToggle").click();
        cy.get("#SecondAddress1").type("PO Box 1204");
        cy.get("#SecondCity").type("Othertown");
        cy.get("#SecondState").select("IL", { force: true });
        cy.get("#SecondZip").type("62998");
        cy.get("#SecondIsMailing").should("not.be.disabled").check();
        cy.get('input[name="FirstName1"]').type(members[0]);
        cy.get('select[name="Classification1"]').select("1", { force: true });
        cy.get('input[name="FirstName2"]').type(members[1]);
        cy.get('select[name="Classification2"]').select("1", { force: true });
        cy.get('button[name="FamilySubmit"]').click();
        cy.location("pathname").should("include", "/people/family/");

        cy.location("pathname").then((pathname) => {
            const familyId = Number(pathname.split("/").pop());

            // The export page always posts the four "to" dates as today; when a
            // request leaves one out, CSVCreateFile.php adds "<= NULL" for it and
            // exports nobody, so send them the way the form does.
            const today = new Date().toISOString().slice(0, 10);

            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    FirstName: 1,
                    Address1: 1,
                    City: 1,
                    SecondAddress: 1,
                    Source: "all",
                    Gender: 0,
                    MembershipDate2: today,
                    BirthDate2: today,
                    AnniversaryDate2: today,
                    EnterDate2: today,
                    Format: "Default",
                    Submit: "Create File",
                },
            }).then((response) => {
                expect(response.status).to.eq(200);
                const rows = response.body
                    .split("\n")
                    .filter((line) => members.some((name) => line.includes(name)));
                expect(rows, "one row per member").to.have.length(members.length);
                rows.forEach((row) => {
                    expect(row).to.include("PO Box 1204");
                    expect(row).to.include("Othertown");
                    expect(row).to.include("62998");
                    expect(row).to.include(",Yes");
                });
            });

            cy.request({
                method: "DELETE",
                url: `/api/family/${familyId}?deleteMembers=true`,
                failOnStatusCode: false,
            });
        });
    });

    it("should allow toggling field pills on and off", () => {
        cy.contains(".form-selectgroup-label", "Title").click();
        cy.get('.form-selectgroup-input[name="Title"]').should("be.checked");

        cy.contains(".form-selectgroup-label", "Title").click();
        cy.get('.form-selectgroup-input[name="Title"]').should("not.be.checked");
    });

    it("should display Filters card with form controls", () => {
        cy.contains("Filters").should("be.visible");

        // Records to export dropdown
        cy.get('select[name="Source"]').should("be.visible");
        cy.get('select[name="Source"]').find("option").should("have.length", 2);

        // Classification multi-select
        cy.get('select[name="Classification[]"]').should("be.visible");
        cy.get('select[name="Classification[]"]').find("option").should("have.length.at.least", 1);

        // Family Role multi-select
        cy.get('select[name="FamilyRole[]"]').should("be.visible");
        cy.get('select[name="FamilyRole[]"]').find("option").should("have.length.at.least", 1);

        // Gender dropdown
        cy.get('select[name="Gender"]').should("be.visible");
        cy.get('select[name="Gender"]').find("option").should("have.length", 3);

        // Group Membership multi-select
        cy.get('select[name="GroupID[]"]').should("be.visible");

        // Date range inputs
        cy.get("#MembershipDate1").should("be.visible");
        cy.get("#MembershipDate2").should("be.visible");
        cy.get("#BirthdayDate1").should("be.visible");
        cy.get("#BirthdayDate2").should("be.visible");
    });

    it("should display Output Method card with format options", () => {
        cy.contains("Output Method").should("be.visible");

        cy.get('select[name="Format"]').should("be.visible");
        cy.get('select[name="Format"]').find("option").should("have.length", 3);
        cy.get('select[name="Format"] option[value="Default"]').should("exist");
        cy.get('select[name="Format"] option[value="Rollup"]').should("exist");
        cy.get('select[name="Format"] option[value="AddToCart"]').should("exist");

        // Skip incomplete address checkbox
        cy.get("#SkipIncompleteAddr").should("be.visible").and("not.be.checked");

        // Submit button
        cy.get('input[name="Submit"]').should("be.visible");
    });

    it("should not contain ChMeetings export section", () => {
        cy.contains("ChMeetings Export").should("not.exist");
    });
});

describe("CSV Export Authorization (GHSA-4vj2-gm78-3q63)", () => {
    it("should allow admin users to submit CSV export form", () => {
        cy.setupAdminSession();

        // Should be able to visit the form
        cy.visit("/CSVExport.php");
        cy.contains("CSV Export").should("be.visible");

        // Should be able to submit the form (POST to CSVCreateFile.php)
        cy.request({
            method: "POST",
            url: "/CSVCreateFile.php",
            body: {
                Title: 1,
                FirstName: 1,
                Address1: 1,
                City: 1,
                State: 1,
                Zip: 1,
                Country: 1,
                Email: 1,
                Source: "all",
                Gender: 0,
                Format: "Default",
                Submit: "Create File"
            }
        }).then((response) => {
            // Should succeed with 200 and CSV content-type
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("text/csv");
        });
    });

    it("should deny unauthenticated access to CSVExport.php", () => {
        // Attempt to access CSVExport.php without authentication
        cy.visit("/CSVExport.php", { failOnStatusCode: false });

        // Should be redirected to login (or show auth error)
        cy.url().should("include", "/session/begin");
    });

    it("should deny unauthenticated POST to CSVCreateFile.php", () => {
        // Attempt to POST to CSVCreateFile.php without authentication
        cy.request({
            method: "POST",
            url: "/CSVCreateFile.php",
            body: {
                Title: 1,
                FirstName: 1,
                Address1: 1,
                City: 1,
                State: 1,
                Zip: 1,
                Country: 1,
                Email: 1,
                Source: "all",
                Gender: 0,
                Format: "Default",
                Submit: "Create File"
            },
            failOnStatusCode: false,
            followRedirect: false
        }).then((response) => {
            // Should get 302 redirect to login or 401 Unauthorized
            expect([302, 401]).to.include(response.status);
        });
    });
});

describe("CSV Export Authorization — Standard Users", () => {
    beforeEach(() => {
        cy.setupStandardSession();
    });

    it("should deny non-admin users access to CSVExport.php", () => {
        // Should not be able to visit the form
        cy.visit("/CSVExport.php", { failOnStatusCode: false });

        // Should be redirected to access-denied page
        cy.url().should("include", "/v2/access-denied");
    });

    it("should deny non-admin users POST to CSVCreateFile.php", () => {
        // Attempt to POST to CSVCreateFile.php as non-admin
        cy.request({
            method: "POST",
            url: "/CSVCreateFile.php",
            form: true,
            body: {
                Title: 1,
                FirstName: 1,
                Address1: 1,
                City: 1,
                State: 1,
                Zip: 1,
                Country: 1,
                Email: 1,
                Source: "all",
                Gender: 0,
                Format: "Default",
                Submit: "Create File"
            },
            failOnStatusCode: false,
            followRedirect: false
        }).then((response) => {
            // Should get 302 redirect (security redirect)
            expect(response.status).to.eq(302);
        });
    });
});
