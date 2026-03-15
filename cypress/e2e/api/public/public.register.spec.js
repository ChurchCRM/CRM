/// <reference types="cypress" />

describe("API Public Registration", () => {
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
            expect(resp.body.Name).to.eq(testFamily.Name);
            expect(resp.body.Address1).to.eq(testFamily.Address1);
            expect(resp.body.Email).to.eq(testFamily.Email);
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
