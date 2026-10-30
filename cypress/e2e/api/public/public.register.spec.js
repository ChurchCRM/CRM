/// <reference types="cypress" />

describe("API Public Registration", () => {
    // The families the tests register, so after() can remove them and their
    // members again (#9769) — a public registration is a real family+person
    // write and used to be left behind on every run.
    const registeredFamilyIds = [];

    after(() => {
        cy.cleanupFamilies(registeredFamilyIds);
    });

    const memberEmailsOf = (familyId) =>
        cy
            .makePrivateAdminAPICall("GET", "/api/families/self-register")
            .then((resp) => resp.body.families.find((f) => f.Id === familyId).MemberEmails);

    it("Should allow family registration without authentication", () => {
        const testFamily = {
            Name: "Cypress Test Family",
            Address1: "123 Test Street",
            Address2: "",
            City: "Testville",
            State: "TS",
            Country: "US",
            Zip: "12345",
            HomePhone: "(555) 123-4567",
            Email: "test@example.com",
            people: [
                {
                    firstName: "John",
                    lastName: "Tester",
                    gender: 1,
                    role: 1,
                    email: "john@example.com",
                    cellPhone: "(555) 987-6543",
                    homePhone: "",
                    workPhone: "",
                    birthday: "01/15/1980",
                    hideAge: false
                }
            ]
        };

        cy.request({
            method: "POST",
            url: "/api/public/register/family",
            body: testFamily,
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.have.property('Id');
            registeredFamilyIds.push(resp.body.Id);
            expect(resp.body.Name).to.eq(testFamily.Name);
            expect(resp.body.Address1).to.eq(testFamily.Address1);
            expect(resp.body.Email).to.eq("john@example.com");
            memberEmailsOf(resp.body.Id).should("deep.equal", ["john@example.com"]);
        });
    });

    it("Saves the family Email on the registering person when they give none", () => {
        const name = `Cypress Family Email ${Date.now()}`;
        cy.request({
            method: "POST",
            url: "/api/public/register/family",
            body: {
                Name: name,
                Address1: "1 Test Street",
                City: "Testville",
                State: "TS",
                Country: "US",
                Zip: "12345",
                Email: "household@example.com",
                people: [
                    { firstName: "Ruth", lastName: "Registrant", gender: 2, role: 1, email: "" },
                    { firstName: "Boaz", lastName: "Registrant", gender: 1, role: 2, email: "" },
                ],
            },
        }).then((resp) => {
            registeredFamilyIds.push(resp.body.Id);
            expect(resp.body.Email).to.eq("household@example.com");
            memberEmailsOf(resp.body.Id).should("deep.equal", ["household@example.com"]);
        });
    });

    it("Saves the family Email on a head of household when a child is listed first", () => {
        const name = `Cypress Child First ${Date.now()}`;
        cy.request({
            method: "POST",
            url: "/api/public/register/family",
            body: {
                Name: name,
                Address1: "1 Test Street",
                City: "Testville",
                State: "TS",
                Country: "US",
                Zip: "12345",
                Email: "household@example.com",
                people: [
                    { firstName: "Obed", lastName: "Registrant", gender: 1, role: 3, email: "" },
                    { firstName: "Ruth", lastName: "Registrant", gender: 2, role: 1, email: "" },
                ],
            },
        }).then((resp) => {
            registeredFamilyIds.push(resp.body.Id);
            expect(resp.body.Email, "family recipient").to.eq("household@example.com");
        });
    });

    it("Refuses a malformed family Email and saves nothing", () => {
        const name = `Cypress Bad Email ${Date.now()}`;
        cy.request({
            method: "POST",
            url: "/api/public/register/family",
            body: {
                Name: name,
                Email: "not-an-email",
                people: [{ firstName: "Ruth", lastName: "Registrant", gender: 2, role: 1, email: "" }],
            },
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(400);
            cy.makePrivateAdminAPICall("GET", "/api/families/self-register")
                .its("body.families")
                .then((families) => expect(families.map((f) => f.Name)).not.to.include(name));
        });
    });

    it("Should return validation error for invalid family data", () => {
        const invalidFamily = {
            Name: "", // Empty name should fail validation
            Address1: "",
            City: "",
            State: "",
            Country: "",
            Zip: "",
            HomePhone: "",
            Email: "",
            people: []
        };

        cy.request({
            method: "POST",
            url: "/api/public/register/family",
            body: invalidFamily,
            failOnStatusCode: false
        }).then((resp) => {
            // Should return 400 Bad Request for validation errors
            expect(resp.status).to.be.oneOf([400, 401]);
            expect(resp.body).to.have.property('error');
        });
    });
});
